# Tasks: Agenda Patient Web

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~800-900 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Foundation + Auth) → PR 2 (Dashboard + Doctors) → PR 3 (Booking + Cancellation) |
| Delivery strategy | ask-always |
| Chain strategy | stacked-to-main |

Decision needed before apply: Yes (resolved: stacked-to-main)
Chained PRs recommended: Yes (resolved: stack to main for all slices)
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Foundation + Auth: routes, layout, AuthController, registration + login | PR 1 | Base: main. 7 tasks, ~250 lines. Includes PatientRegistrationTest + PatientAuthTest |
| 2 | Dashboard + Doctors: Dashboard Livewire, DoctorList, ProfileController | PR 2 | Base: main (independent of PR 1 via route file). 6 tasks, ~300 lines |
| 3 | Booking + Cancellation: BookAppointment + CancelAppointmentController | PR 3 | Base: main (independent of PR 1/2 via route file). 5 tasks, ~350 lines |

## Phase 0: Foundation + Auth (Slice 1 — ~250 lines)

- [x] 0.1 Write RED test: `tests/Feature/Patient/PatientRegistrationTest.php` — happy + duplicate email
- [x] 0.2 Write RED test: `tests/Feature/Patient/PatientAuthTest.php` — valid + invalid login
- [x] 0.3 Modify `bootstrap/app.php`: add `then:` callback loading `routes/web-patient.php`
- [x] 0.4 Create `routes/web-patient.php` with guest (login/register) and auth (dashboard) groups
- [x] 0.5 Create `resources/views/layouts/patient.blade.php` — Tailwind layout with nav
- [x] 0.6 Create `app/Http/Controllers/Patient/AuthController.php` + login/register views — GREEN
- [x] 0.7 Run `vendor/bin/pest tests/Feature/Patient/` — all 4 GREEN (14 assertions)

## Phase 1: Dashboard + Doctors (Slice 2 — ~300 lines)

- [x] 1.1 Write RED test: `tests/Feature/Patient/PatientDashboardTest.php` — with/without appointments
- [x] 1.2 Write RED test: `tests/Feature/Patient/DoctorListingTest.php` — all doctors + specialty filter
- [x] 1.3 Create `app/Livewire/Patient/Dashboard.php` + `resources/views/patient/dashboard.blade.php` — GREEN
- [x] 1.4 Create `app/Livewire/Patient/DoctorList.php` + `resources/views/patient/doctors.blade.php` with specialty filter — GREEN
- [x] 1.5 Create `app/Http/Controllers/Patient/ProfileController.php` + `resources/views/patient/profile.blade.php` — GREEN
- [x] 1.6 Run `vendor/bin/pest tests/Feature/Patient/` — all 10 GREEN (48 assertions)

## Phase 2: Booking + Cancellation (Slice 3 — ~350 lines)

- [ ] 2.1 Write RED test: `tests/Feature/Patient/AppointmentBookingTest.php` — happy path + race condition
- [ ] 2.2 Write RED test: `tests/Feature/Patient/AppointmentCancellationTest.php` — inside/outside 24h window
- [ ] 2.3 Create `app/Livewire/Patient/BookAppointment.php` + `resources/views/patient/book.blade.php` with `lockForUpdate` transaction — GREEN
- [ ] 2.4 Create `app/Http/Controllers/Patient/CancelAppointmentController.php` with 24h assertion — GREEN
- [ ] 2.5 Run `php artisan test --filter=AppointmentBooking|AppointmentCancellation` — all green

## Phase 3: Housekeeping (All Slices)

- [ ] 3.1 Run full test suite — all existing + new tests green, API routes untouched
- [ ] 3.2 Mark tasks complete in tasks.md
