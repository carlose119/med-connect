# Proposal: Agenda Patient Web

## Intent

Patients need a self-service web portal to register, log in, browse doctors, view available slots, and book/cancel appointments. Currently all patient operations are API-only (18 Sanctum routes). This change builds the first-party web frontend for patients, reusing the existing Service/Model layer.

## Scope

### In Scope
- Patient registration + login via Laravel web guard
- Patient dashboard (upcoming appointments)
- Doctor listing page with specialty filtering
- Slot viewing + appointment booking flow
- Patient-facing appointment cancellation
- Patient profile view (basic info)

### Out of Scope
- Prescription refills, medical notes, doctor messaging
- Payment/billing
- Password reset, email notifications
- Mobile app

## Capabilities

### New Capabilities
- `patient-web`: Patient web frontend — registration, login, dashboard, doctor listing, slot viewing, booking, cancellation, profile view via Blade + Livewire

### Modified Capabilities
None — existing specs (agenda, users-roles, doctor-schedule) remain unchanged. Backend contracts stay the same; only the web UI layer is added.

## Approach

Blade + Livewire frontend under `/patient` path. New web controllers at `app/Http/Controllers/Patient/`, Livewire components at `app/Livewire/Patient/`, views at `resources/views/patient/`. Routes in `routes/web-patient.php` (loaded in `RouteServiceProvider`). Reuses existing models (User, Patient, Doctor, Appointment), Policies, and slot services directly — no API calls, Livewire talks to Eloquent. Session-based auth via web guard.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `routes/web-patient.php` | New | Patient web routes |
| `app/Http/Controllers/Patient/` | New | Web controllers |
| `app/Livewire/Patient/` | New | Livewire components |
| `resources/views/patient/` | New | Blade views |
| `tests/Feature/Patient/` | New | Pest feature tests |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Scope creep | Med | Tight v1 scope; out-of-scope listed explicitly |
| Session + Sanctum auth confusion | Low | Clear middleware groups; separate route file |
| First Blade+Livewire patterns | Med | Document conventions; mirror Filament Livewire patterns |
| No browser testing patterns yet | Low | Use Laravel HTTP tests for initial coverage |

## Rollback Plan

Remove `routes/web-patient.php` from `RouteServiceProvider`, delete new controllers/components/views. Existing API routes and admin panel stay untouched — no rollback needed on their side.

## Dependencies

- Laravel Livewire (already available via Filament dependency)
- Pest (already installed)

## Success Criteria

- [ ] Patient registers and logs in via `/patient/login`
- [ ] Patient sees upcoming appointments on dashboard
- [ ] Patient browses doctors and views available slots
- [ ] Patient books an appointment (DB row, state = pending)
- [ ] Patient cancels own appointment
- [ ] Existing 18 API routes + admin panel continue working
- [ ] Full test suite green (existing + new tests)
