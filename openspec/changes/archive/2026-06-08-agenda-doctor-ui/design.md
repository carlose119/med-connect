# Design: Agenda Doctor UI

## Technical Approach

Read-only `DoctorAppointmentResource` under `app/Filament/Resources/DoctorAppointments/` registered via `discoverResources()` in `DoctorPanelProvider` — same glob path as admin. Resource queries scoped to `auth()->user()->doctor->appointments()`. Four custom table actions delegate to existing Transition classes. Date filters via Filament's `SelectFilter`. Only `ListRecords` page (no create/edit).

## Architecture Decisions

### Decision: Resource namespace

| Option | Tradeoff | Decision |
|--------|----------|----------|
| `app/Filament/Doctor/Resources/` | New top-level branch, needs separate glob | ❌ |
| `app/Filament/Resources/DoctorAppointments/` | Same glob path as admin, prefix avoids collision | ✅ |

**Rationale**: Under `app/Filament/Resources/` so `discoverResources(in: app_path('Filament/Resources'), for: self::class)` works unchanged. `DoctorAppointments` prefix avoids confusion with a future admin `AppointmentsResource`.

### Decision: Panel access gating

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Separate namespace per panel | Clean but forces duplicate discovery paths | ❌ |
| `canAccess()` checks `Filament::getCurrentPanel()->getId()` | Single namespace, runtime check | ✅ |

**Rationale**: `discoverResources()` discovers ALL resources under the path. `DoctorAppointmentResource` overrides `canAccess()` to return `true` only for the `doctor` panel, hiding nav from admin. No Shield role needed — doctor panel login already gates by `role=doctor`.

### Decision: Table actions as inline `Action::make()`

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Dedicated Action class per transition | Reusable but boilerplate-heavy for 4 actions | ❌ |
| Inline `Action::make()` in table | Direct, 5 lines each | ✅ |

**Rationale**: Each action is a `visible()` check + closure calling `$record->state->transitionTo(TransitionClass::class, auth()->user())`. Transition classes already enforce actor authorization. Cancel needs a Filament form for the reason (fillable on model pre-transition).

### Decision: Date filters as `SelectFilter`

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Custom date range picker | Richer UX but complex defaults | ❌ |
| `SelectFilter` with 3 options | Simple, matches spec exactly | ✅ |

**Rationale**: Three mutually exclusive options (Today, Upcoming, Past) map directly to a `SelectFilter` over `start_time`. Default to Upcoming (fits doctor workflow — see upcoming appointments first).

## Data Flow

```
Doctor Panel (/doctor)
  └→ DoctorAppointmentResource
      └→ ListDoctorAppointments (ListRecords)
          └→ DoctorAppointmentsTable
              ├→ getEloquentQuery()
              │   └→ auth()->user()->doctor->appointments()
              │       ->with(['patient.user', 'state'])
              ├→ columns: date, time, patient name, status badge
              ├→ filters: Today | Upcoming (default) | Past
              └→ record actions:
                  ├→ Confirm   → $record->state->transitionTo(Confirmed::class, $user)
                  ├→ Complete  → $record->state->transitionTo(Completed::class, $user)
                  ├→ Cancel    → $record->cancellation_reason = $data['reason'];
                  │               $record->state->transitionTo(Cancelled::class, $user)
                  └→ NoShow    → $record->state->transitionTo(NoShow::class, $user)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Providers/Filament/DoctorPanelProvider.php` | Modify | Add `->discoverResources(in: app_path('Filament/Resources'), for: self::class)` |
| `app/Filament/Resources/DoctorAppointments/DoctorAppointmentResource.php` | Create | Model binding, `getEloquentQuery()`, `canAccess()`, `getPages()` |
| `app/Filament/Resources/DoctorAppointments/Pages/ListDoctorAppointments.php` | Create | Extends `ListRecords`, no header actions |
| `app/Filament/Resources/DoctorAppointments/Tables/DoctorAppointmentsTable.php` | Create | Columns, date filters, 4 transition actions with visibility |
| `tests/Feature/Doctor/DoctorAppointmentUiTest.php` | Create | Feature tests |

## Interfaces / Contracts

All transition classes accept `(Appointment $model, ?User $actor = null)`. No new contracts — existing state machine handles authorization:

```php
// Each action calls:
$record->state->transitionTo(Confirmed::class, auth()->user());
```

For Cancel, the `cancellation_reason` is set on the model before the transition since the existing `CancelAppointmentTransition` does not handle it internally.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Feature | Doctor sees own appointments only | `get('/doctor')` → assert own appointment visible, other doctor's not |
| Feature | Each transition action | POST to action endpoint → assert state column changed |
| Feature | Actions hidden for invalid states | Assert Confirm absent on confirmed appointment, etc. |
| Feature | Date filters scope correctly | Assert Today/Upcoming/Past return correct rows |
| Feature | Cancel records reason | Assert `cancellation_reason` set in DB after action |
| Feature | Admin panel unchanged | Admin `AppointmentResource` still works, not affected |

## Migration / Rollout

No migration required. Additive UI only — existing admin panel, API routes, and patient flows untouched. Can be deployed independently.

## Open Questions

None.
