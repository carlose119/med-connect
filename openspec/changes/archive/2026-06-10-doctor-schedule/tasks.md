# Tasks: Doctor Schedule Management

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 800–950 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | 3 stacked PRs |
| Delivery strategy | single-pr-default |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Validation Rules + Policies | PR 1 → main | Rules, Policy classes + unit tests. Standalone. |
| 2 | Filament Resources | PR 2 → main | Both schedule + override resources with query scoping. Depends on PR 1 policies. |
| 3 | Feature Tests | PR 3 → main | Spec scenarios + Filament CRUD tests. Depends on PR 2 resources. |

## Phase 1: Validation Rules

- [x] 1.1 Create `app/Rules/ScheduleDurationPositive.php` — int > 0 validation
- [x] 1.2 Create `app/Rules/ScheduleEndAfterStart.php` — end_time > start_time comparison
- [x] 1.3 Unit tests: duration boundary values (0, -1, 1, 30)
- [x] 1.4 Unit tests: end<start fails, end=start fails, end>start passes

## Phase 2: Policies

- [x] 2.1 Create `app/Policies/DoctorSchedulePolicy.php` — admin: all, doctor: own records
- [x] 2.2 Create `app/Policies/DoctorScheduleOverridePolicy.php` — same pattern
- [x] 2.3 Register both in `app/Providers/AppServiceProvider.php` via `Gate::policy()`
- [x] 2.4 Policy unit tests: admin true, doctor-own true, doctor-other false

## Phase 3: DoctorSchedule Filament Resource

- [x] 3.1 Create `app/Filament/Resources/DoctorSchedules/Schemas/DoctorScheduleForm.php` — day_of_week select, time pickers, duration, active toggle
- [x] 3.2 Create `app/Filament/Resources/DoctorSchedules/Tables/DoctorSchedulesTable.php` — columns + day_of_week / is_active filters
- [x] 3.3 Create `app/Filament/Resources/DoctorSchedules/Pages/` — List, Create, Edit pages
- [x] 3.4 Create `app/Filament/Resources/DoctorSchedules/DoctorScheduleResource.php` — model, form/table/pages wiring, `getEloquentQuery()` doctor scoping

## Phase 4: DoctorScheduleOverride Filament Resource

- [x] 4.1 Create `app/Filament/Resources/DoctorScheduleOverrides/Schemas/DoctorScheduleOverrideForm.php` — date, type select, nullable times, reason textarea
- [x] 4.2 Create `app/Filament/Resources/DoctorScheduleOverrides/Tables/DoctorScheduleOverridesTable.php` — columns + type / date range filters
- [x] 4.3 Create `app/Filament/Resources/DoctorScheduleOverrides/Pages/` — List, Create, Edit pages
- [x] 4.4 Create `app/Filament/Resources/DoctorScheduleOverrides/DoctorScheduleOverrideResource.php` — model, form/table/pages wiring, query scoping

## Phase 5: Feature Tests

- [x] 5.1 Test: inactive schedule rule produces no slots (add to existing `DoctorAvailabilityServiceTest`)
- [x] 5.2 Test: block override excludes a time range (not just full-day)
- [x] 5.3 Test: non-positive duration rejected via Rule integration
- [x] 5.4 Test: end before start rejected via Rule integration
- [x] 5.5 Test: nullable times for full-day override accepted
- [x] 5.6 Test: schedule CRUD via Filament (create, edit, toggle active, delete)
- [x] 5.7 Test: override CRUD via Filament (create block, create extra, delete)
- [x] 5.8 Test: authorization — doctor cannot access another doctor's schedules
