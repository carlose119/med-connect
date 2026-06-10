# Tasks: Prescriptions

## Phase 1: Foundation

### 1.1 PrescriptionPolicy
- [x] `app/Policies/PrescriptionPolicy.php`
  - `viewAny`: doctor + admin (patients use the scoped index in controller)
  - `view`: `$user->isAdmin() || $user->doctor?->id === $prescription->doctor_id`
  - `create`: doctor auth (no ownership check — controller validates appointment belongs to doctor)
  - `update`: same as view (doctor owns or admin)
  - `delete`: `false` — prescriptions are never deleted
- [x] Register in `AuthServiceProvider::policies`
- [x] `tests/Unit/Policies/PrescriptionPolicyTest.php`
  - Doctor viewing own prescription → allowed
  - Doctor viewing another's prescription → 403
  - Admin viewing any prescription → allowed
  - Patient → viewAny returns false (patients go through scoped controller index)
  - `delete` → always denied

### 1.2 Actions
- [x] `app/Actions/Medical/GeneratePrescriptionCodeAction.php`
  - Format: `RX-{year}-{6 random digits}` (zero-padded)
  - Loop: generate → attempt save → catch `QueryException` with unique violation → retry (max 5 attempts)
  - Extract year from `issued_at` (default: current year)
  - **Unit test**: format regex `^RX-\d{4}-\d{6}$`, retry counter increments on mock collision, max 5 attempts then throws
- [x] `app/Traits/GeneratesPrescriptionCode.php` *(added as per PR 1 scope)*
  - Static `generateUniqueCode()`: returns `RX-{year}-{6 random digits}` via `random_int(100000, 999999)`
  - Simple trait — no DB lookup; uniqueness enforced by DB constraint at write time
  - **Unit test**: format regex, year check, 100-call uniqueness, digit range validation
- [x] `tests/Unit/Traits/GeneratesPrescriptionCodeTest.php` *(TDD — RED first, GREEN confirmed)*
- [x] `app/Actions/Medical/IssuePrescriptionAction.php`
  - Accepts `Appointment $appointment, Doctor $doctor, array $data` where `$data` contains `items[]`
  - `DB::transaction()`: create `Prescription` → bulk insert `PrescriptionItem` rows
  - `prescription_id` set implicitly via `items[].prescription_id` fill
  - Returns `Prescription` with items loaded
  - **Unit test**: 3 items → 1 prescription row + 3 item rows; `unique_code` set; `doctor_id` from param; `patient_id` from appointment
- [x] `app/Actions/Medical/CancelPrescriptionAction.php`
  - Accepts `Prescription $prescription, string $reason`
  - Sets `status = 'cancelled'`, `cancellation_reason = $reason`
  - Returns updated `Prescription`
  - **Unit test**: status + reason set, no other fields mutated

### 1.3 PrescriptionItemFactory
- [x] Verify existing `database/factories/PrescriptionItemFactory.php` covers all fields (`name`, `dosage`, `frequency`, `duration`, `position`)
- [x] Add `forPrescription()` relationship method if not present

## Phase 2: API

### 2.1 PrescriptionController additions
- [x] `store(CreatePrescriptionRequest $request, IssuePrescriptionAction $action): JsonResponse`
  - Authorize via `Gate::authorize('create', Prescription::class)`
  - Validate appointment belongs to authenticated doctor
  - Validate appointment `state === 'completed'` (custom rule or inline check)
  - Call `$action($appointment, $doctor, $request->validated())`
  - Return `PrescriptionResource` with 201
  - Catch unique constraint violation → 409 Conflict with `{ "error": { "code": "UNIQUE_CODE_COLLISION", "message": "..." } }`
- [x] `show(Prescription $prescription): PrescriptionResource`
  - `Gate::authorize('view', $prescription)`
  - Load `items` relation
  - Return `PrescriptionResource`
- [x] `update(UpdatePrescriptionRequest $request, Prescription $prescription, CancelPrescriptionAction $action): JsonResponse`
  - `Gate::authorize('update', $prescription)`
  - Call `$action($prescription, $request->validated()['cancellation_reason'])`
  - Return `PrescriptionResource` with 200

### 2.2 Requests
- [x] `app/Http/Requests/Api/CreatePrescriptionRequest.php`
  - `appointment_id`: required, exists in `appointments`
  - `items`: required, array, min 1
  - `items.*.name`: required, string, max 255
  - `items.*.dosage`: nullable, string, max 128
  - `items.*.frequency`: nullable, string, max 128
  - `items.*.duration`: nullable, string, max 128
  - With the validated data, the controller handles appointment + state checks
- [x] `app/Http/Requests/Api/UpdatePrescriptionRequest.php`
  - `status`: required, `in:cancelled`
  - `cancellation_reason`: required, string, max 1000

### 2.3 PrescriptionResource extension
- [x] Update `app/Http/Resources/Api/PrescriptionResource.php`
  - Add `doctor` sub-object: `['id' => int, 'name' => string]`
  - Add `cancellation_reason` field
  - Add `items` nested collection: `PrescriptionItemResource::collection($rx->items)`
  - Use `withRelationships()` to eager-load items in `store/show/update`
- [x] `app/Http/Resources/Api/PrescriptionItemResource.php` (new)
  - Fields: `id`, `name`, `dosage`, `frequency`, `duration`, `position`

### 2.4 API Routes
- [x] `routes/api.php`
  - `POST /api/prescriptions` → `PrescriptionController@store`
  - `GET /api/prescriptions/{prescription}` → `PrescriptionController@show`
  - `PUT /api/prescriptions/{prescription}` → `PrescriptionController@update`
  - All under the existing `auth:sanctum` group

### 2.5 API Feature Tests
- [x] `tests/Feature/Api/PrescriptionTest.php`
  - `POST /api/prescriptions` → 201 with prescription + 3 items (3 DB rows)
  - `POST /api/prescriptions` with appointment not completed → 422
  - `POST /api/prescriptions` with doctor not owning appointment → 403
  - `POST /api/prescriptions` with duplicate unique_code → 409 Conflict
  - `POST /api/prescriptions` with missing items → 422 VALIDATION_ERROR
  - `GET /api/prescriptions/{id}` → 200 with nested items array
  - `GET /api/prescriptions/{id}` → 403 for non-owner doctor
  - `PUT /api/prescriptions/{id}` → 200 with status=cancelled
  - `PUT /api/prescriptions/{id}` without reason → 422
  - Items ordered by position (ascending)

## Phase 3: Filament

### 3.1 PrescriptionResource
- [x] `app/Filament/Resources/ClinicalRecords/PrescriptionResource.php`
  - `$model = Prescription::class`
  - `$navigationIcon = Heroicon::OutlinedClipboardDocumentCheck`
  - `$navigationGroup = 'Clinical'`
  - `$navigationSort = 2`
  - `canCreate()`: false (read-only — prescriptions issued via API)
  - `canEdit()`: false, `canDelete()`: false
  - `getEloquentQuery()`: scope to `$user->doctor?->id` for doctors; admin sees all
  - `getRelations()`: returns `[PrescriptionItemsRelationManager::class]`
  - `getPages()`: `index` + `view` (no create/edit)

### 3.2 PrescriptionForm + PrescriptionsTable
- [x] `app/Filament/Resources/ClinicalRecords/Schemas/PrescriptionForm.php`
  - Read-only: patient_name, doctor_name, unique_code, issued_at, status, cancellation_reason (conditional), items_count
  - All fields disabled + `dehydrated(false)` — no form submissions
- [x] `app/Filament/Resources/ClinicalRecords/Tables/PrescriptionsTable.php`
  - Columns: patient name, doctor name, unique_code (mono), issued_at, status badge (success=active, danger=cancelled), items count badge
  - Filters: status select, date range
  - `recordActions([])` — read-only

### 3.3 PrescriptionItemsRelationManager
- [x] `app/Filament/Resources/ClinicalRecords/RelationManagers/PrescriptionItemsRelationManager.php`
  - `getRelationshipName()`: `'items'`
  - `canCreate()`: false, `canEdit()`: false, `canDelete()`: false
  - `table()`: TextColumns for position, name, dosage, frequency, duration — all read-only
  - No recordActions, no headerActions

### 3.4 Pages
- [x] `app/Filament/Resources/ClinicalRecords/Pages/ListPrescriptions.php`
  - Extends `ListRecords`, uses `PrescriptionsTable`
- [x] `app/Filament/Resources/ClinicalRecords/Pages/ViewPrescription.php`
  - Extends `ViewRecord`, renders read-only form + PrescriptionItemsRelationManager

### 3.5 Feature Tests
- [x] `tests/Feature/Filament/PrescriptionResourceTest.php` — 11 tests: model binding, nav config, canCreate/Edit/Delete false, relation manager registered, pages registered, list renders, view renders
- [x] `tests/Feature/Filament/PrescriptionScopingTest.php` — 2 tests: doctor sees own, admin sees all
- [x] `tests/Feature/Filament/PrescriptionItemsRelationManagerTest.php` — 5 tests: relationship name, items count, position ordering, no create/edit/delete actions

## Review Workload Estimate

**Files created/modified: 20**
**Estimated total lines (additions + deletions): ~1,400**

| Work unit | Files | Est. lines |
|-----------|-------|-----------|
| Phase 1 (Policy + Actions) | 6 | ~250 |
| Phase 2 (API) | 7 | ~450 |
| Phase 3 (Filament) | 7 | ~700 |
| **Total** | **20** | **~1,400** |

**Decision needed before apply: Yes**
**Chained PRs recommended: Yes**
**400-line budget risk: High**

The 1,400-line estimate exceeds the 400-line review budget by 3.5×. Recommend splitting into **3 chained PRs**:

| PR | Scope | Est. lines |
|----|-------|-----------|
| PR 1 | Phase 1: Policy + Actions + code generation | ~250 |
| PR 2 | Phase 2: API controller + requests + resources + routes + tests | ~450 |
| PR 3 | Phase 3: Filament resource + relation manager + pages + tests | ~700 |

Each PR is self-contained, testable, and has a clear rollback (drop tables/state in migration-down). The `Prescription` model is already in place, so PR 1 only adds policy and action classes.