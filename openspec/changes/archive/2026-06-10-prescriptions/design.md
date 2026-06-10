# Design: Prescriptions

## Technical Approach

Issue prescriptions via a Sanctum-protected API (`POST /api/prescriptions`) and manage them in Filament. Both surfaces share the same domain actions (`IssuePrescriptionAction`, `CancelPrescriptionAction`). The prescription is the aggregate root; items are persisted as separate rows within a DB transaction. Immutable items — corrections trigger a new prescription.

## Architecture Decisions

### Decision: Items managed as part of Prescription, not a separate endpoint

**Choice**: Items are created alongside the prescription in `store()` and returned nested in `PrescriptionResource`. No separate `/prescription-items` controller.
**Alternatives considered**: Standalone `PrescriptionItemController` with separate CRUD routes.
**Rationale**: Items are append-only and semantically part of the prescription. A flat `store()` with `items[]` array keeps the API surface minimal and matches the `MedicalNotesRelationManager` pattern already in the codebase.

### Decision: Unique code generated server-side, not provided by client

**Choice**: `PrescriptionController::store()` generates `RX-{year}-{6-digit}` format before the DB write. The `unique_code` is not accepted from the request.
**Alternatives considered**: Client submits the code.
**Rationale**: Guarantees collision resistance. The DB unique constraint on `unique_code` acts as the last line of defence; the generation loop retries on collision (max 5 attempts).

### Decision: Cancellation is a status transition, not a delete

**Choice**: `PUT /api/prescriptions/{id}` with `{ "status": "cancelled", "cancellation_reason": "..." }`. No delete route.
**Alternatives considered**: `DELETE /api/prescriptions/{id}` with soft-delete.
**Rationale**: Medical records must be preserved. `cancelled` is a terminal state — once set it cannot be reversed. Matches the `medical_notes` append-only philosophy in the approved PRD.

### Decision: Appointment must be `completed` before prescription issuance

**Choice**: `IssuePrescriptionAction` validates `appointment.state === 'completed'` before persisting.
**Alternatives considered**: Allow prescriptions for any non-cancelled appointment state.
**Rationale**: A prescription is a medical record of treatment already delivered. Allowing it for pending/confirmed appointments creates inconsistency with the medical record model.

## Data Flow

```
POST /api/prescriptions
  → CreatePrescriptionRequest (validates: appointment completed, items present)
  → PrescriptionPolicy::create (doctor auth, owns appointment)
  → IssuePrescriptionAction
      DB::transaction:
        Prescription::create([appointment_id, doctor_id, patient_id, unique_code, issued_at, status=active])
        foreach items as position:
          PrescriptionItem::create([prescription_id, name, dosage, frequency, duration, position])
  → PrescriptionResource::collection([with: items]) → 201

PUT /api/prescriptions/{id}
  → UpdatePrescriptionRequest (validates: status=cancelled + cancellation_reason required)
  → PrescriptionPolicy::update (owned or admin)
  → CancelPrescriptionAction (status=cancelled, cancellation_reason set)
  → PrescriptionResource → 200

GET /api/prescriptions/{id}
  → PrescriptionPolicy::view (owned or admin)
  → PrescriptionResource (with items loaded) → 200
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Policies/PrescriptionPolicy.php` | Create | Authorisation: viewAny/create (doctor), view/update/delete (owned or admin) |
| `app/Actions/Medical/IssuePrescriptionAction.php` | Create | Transaction: create prescription + items |
| `app/Actions/Medical/CancelPrescriptionAction.php` | Create | Set status=cancelled + reason |
| `app/Actions/Medical/GeneratePrescriptionCodeAction.php` | Create | `RX-{year}-{6-digit}` generation with collision retry |
| `app/Http/Controllers/Api/PrescriptionController.php` | Modify | Add `store`, `show`, `update` methods |
| `app/Http/Requests/Api/CreatePrescriptionRequest.php` | Create | Validates appointment completed, items array |
| `app/Http/Requests/Api/UpdatePrescriptionRequest.php` | Create | Validates cancellation status + reason |
| `app/Http/Resources/Api/PrescriptionResource.php` | Modify | Extend with `items` nested, `cancellation_reason`, `doctor` sub-object |
| `routes/api.php` | Modify | Add `POST /prescriptions`, `GET /prescriptions/{id}`, `PUT /prescriptions/{id}` |
| `app/Filament/Resources/ClinicalRecords/PrescriptionResource.php` | Create | Filament resource under Clinical group |
| `app/Filament/Resources/ClinicalRecords/Tables/PrescriptionsTable.php` | Create | Table: patient, date, item count, status badge |
| `app/Filament/Resources/ClinicalRecords/Schemas/PrescriptionForm.php` | Create | Form for create (no edit) |
| `app/Filament/Resources/ClinicalRecords/RelationManagers/PrescriptionItemsRelationManager.php` | Create | View + Create actions; no edit/delete |
| `app/Filament/Resources/ClinicalRecords/Pages/ListPrescriptions.php` | Create | List page |
| `app/Filament/Resources/ClinicalRecords/Pages/ViewPrescription.php` | Create | View page with relation manager |
| `database/factories/PrescriptionItemFactory.php` | Create | Factory for items (already exists — verify completeness) |
| `tests/Unit/PrescriptionPolicyTest.php` | Create | Unit: policy authorisations |
| `tests/Unit/IssuePrescriptionActionTest.php` | Create | Unit: action creates prescription + N items |
| `tests/Unit/GeneratePrescriptionCodeActionTest.php` | Create | Unit: code format + collision retry |
| `tests/Feature/Api/PrescriptionTest.php` | Create | Feature: full CRUD, 409 collision, 422 validation |

## Interfaces / Contracts

### CreatePrescriptionRequest

```php
// Required: appointment_id (exists, completed), items (array, min 1)
// Optional: none (items are the payload)
[
  'appointment_id' => 'integer|exists:appointments,id',
  'items'          => 'array|min:1',
  'items.*.name'   => 'required|string|max:255',
  'items.*.dosage' => 'nullable|string|max:128',
  'items.*.frequency' => 'nullable|string|max:128',
  'items.*.duration'  => 'nullable|string|max:128',
]
// Custom: appointment must be in 'completed' state
// Custom: authenticated doctor must own the appointment
```

### UpdatePrescriptionRequest

```php
[
  'status'              => 'required|string|in:cancelled',
  'cancellation_reason' => 'required|string|max:1000',
]
```

### PrescriptionResource (JSON)

```php
[
  'id'                  => int,
  'appointment_id'      => int,
  'doctor'              => ['id' => int, 'name' => string],
  'patient_id'          => int,
  'unique_code'         => string,         // e.g. "RX-2026-482917"
  'issued_at'           => string,          // ISO 8601
  'status'              => 'active'|'cancelled',
  'cancellation_reason' => string|null,
  'items'               => [                // nested, ordered by position
    ['id', 'name', 'dosage', 'frequency', 'duration', 'position'],
  ],
]
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `PrescriptionPolicy` — doctor scopes to own, admin sees all, patient sees own | Direct policy method calls with mocked users |
| Unit | `IssuePrescriptionAction` — creates 1 prescription + N items in one tx | Action call + DB assertions |
| Unit | `GeneratePrescriptionCodeAction` — format `RX-YYYY-NNNNNN`, retry on collision | Mock DB unique-exception, assert retry <= 5 |
| Feature | `POST /api/prescriptions` — 201, 409 (collision), 422 (bad appointment), 403 | HTTP assertions with `actingAs` |
| Feature | `GET /api/prescriptions/{id}` — 200 with nested items | HTTP + DB row count |
| Feature | `PUT /api/prescriptions/{id}` — 200 cancelled, 422 missing reason | HTTP assertions |
| Feature | Items as rows: `items[3]` → 3 DB rows, ordered by `position` | DB count + order assertion |
| Feature | Appointment must be completed — 422 for non-completed | HTTP 422 assertion |
| Feature | Unique code collision → 409 | DB seed + HTTP 409 |
| Feature | Filament list page renders, scoping works | Filament test suite assertions |
| Feature | PrescriptionItemsRelationManager: view + create only | Relation manager assertions |

## Migration / Rollout

No migration required — the table schema already exists (`2026_06_01_000011`, `2026_06_01_000012`) with the correct column types, FKs, and unique constraints.

**Feature flag strategy**: None needed. The feature is additive and can be released in one PR.

**Rollout**: API routes are additive (new endpoints). Filament resource is additive. Existing `GET /api/prescriptions` index remains unchanged — no breaking change.

## Open Questions

- [ ] Should `unique_code` be scannable/typed from the mobile app? If so, the 6-digit random may need a checksum or barcode encoding (future iteration — do not block v1).
- [ ] Do cancelled prescriptions require a reason to be mandatory? The spec says yes for the `cancelled` status transition; the `UpdatePrescriptionRequest` enforces it. Confirm this is the intended behaviour.
- [ ] Should prescriptions be visible to the patient via the API? The current `index()` scoping allows patient to see their own prescriptions. Verify this is the intended access pattern.