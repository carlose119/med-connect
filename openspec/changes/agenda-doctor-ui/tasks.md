# Tasks: Agenda Doctor UI

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~200–300 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Single PR (main) |
| Delivery strategy | ask-always |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

## Phase 0: Foundation

- [x] 0.1 Modify `app/Providers/Filament/DoctorPanelProvider.php` — add `->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')` to share the admin resource discovery path
- [x] 0.2 Create `app/Filament/Resources/DoctorAppointments/DoctorAppointmentResource.php` — set `$model = Appointment::class`, override `getEloquentQuery()` to scope `auth()->user()->doctor->appointments()`, add `canAccess()` gating on panel `id === 'doctor'`, `getPages()` returning only `index` page

## Phase 1: Core UI (List, Table, Actions)

- [x] 1.1 Create `app/Filament/Resources/DoctorAppointments/Pages/ListDoctorAppointments.php` — extend `ListRecords`, no header actions (read-only), id: `index`
- [x] 1.2 Create `app/Filament/Resources/DoctorAppointments/Tables/DoctorAppointmentsTable.php` — columns: date (`start_time`), time (`start_time`–`end_time`), patient name (`patient.user.name`), status badge; `SelectFilter` with Today/Upcoming(default)/Past on `start_time`; four `recordActions` each calling `$record->state->transitionTo(...)` with `visible()` gating by current state

## Phase 2: Testing & Verification

- [x] 2.1 Write `tests/Feature/Doctor/DoctorAppointmentUiTest.php` — RED: doctor sees own appointments only, each action visible in correct state, actions hidden for invalid states, date filters scope correctly, Cancel stores `cancellation_reason`, admin panel unchanged
- [x] 2.2 Run `vendor/bin/pest --filter=DoctorAppointmentUi` — GREEN (all 6/6 passing)
- [x] 2.3 Run full test suite + verify admin `/admin` and API routes are unaffected
