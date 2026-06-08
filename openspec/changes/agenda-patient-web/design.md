# Design: Agenda Patient Web

## Technical Approach

Blade + Livewire frontend at `/patient/*` served via Laravel web guard session auth. Livewire components talk directly to existing Eloquent models/Services (no API calls). Route file `routes/web-patient.php` loaded from `bootstrap/app.php`. Reuses User, Patient, Doctor, Appointment, Specialty models and `DoctorAvailabilityService`.

## Architecture Decisions

### Decision: Route Loading (Laravel 11 style)

| Option | Tradeoff | Decision |
|--------|----------|----------|
| `AppServiceProvider::boot()` | Works but routes not cached | ❌ |
| `withRouting(then: ...)` in `bootstrap/app.php` | Cache-friendly, single entry point | ✅ |

Add `then:` callback to existing `withRouting()` in `bootstrap/app.php`. Loads `routes/web-patient.php` after main web routes with the `web` middleware group.

### Decision: Auth Flow

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Filament auth reuse | Panel already configured, wrong roles | ❌ |
| Standard `Auth::attempt()` | Simple, proven, web guard native | ✅ |

`AuthController` handles login + registration. Registration creates User (`role=patient`) + Patient profile in a DB transaction. Uses `Auth::guard('web')->attempt()` — no Sanctum tokens for web sessions.

### Decision: Booking Race Condition Guard

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Optimistic locking | Retry logic needed, more complex | ❌ |
| `lockForUpdate()` in transaction | Blocks concurrent writes, simple | ✅ |

`BookAppointment` Livewire component wraps slot creation in a DB transaction with `Appointment::where('doctor_id', $id)->where('start_time', $slot)->lockForUpdate()` to prevent double-booking.

### Decision: Cancellation Window

Server-side assertion in `CancelAppointmentController`: `$appointment->start_time > now()->addHours(24)`. The 24h rule comes from `AGENTS.md`. Rejected requests return a `409 Conflict` with a user-facing error message. State transition uses `$appointment->state->transitionTo(Cancelled::class)`.

### Decision: Livewire Full-Page Components

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Section/partial components | More routes, fragmented state | ❌ |
| Full-page Livewire components | Single route per view, self-contained | ✅ |

`Dashboard`, `DoctorList`, `BookAppointment` as full-page Livewire components. `DoctorList` uses Livewire query-string binding for the specialty filter.

## Data Flow

```
Visitor → /patient/register → AuthController → User+Patient (DB) → Session → /patient/dashboard
Visitor → /patient/login    → AuthController → Auth::attempt()  → Session → /patient/dashboard

Patient (auth) → /patient/dashboard    → Dashboard component → Appointment::with('doctor.user')->where(...)
               → /patient/doctors      → DoctorList component → Doctor::with('specialty', 'user')->get()
               → /patient/doctors/{d}/book → BookAppointment comp → DoctorAvailabilityService::slots()
               → POST book (Livewire)  → Transaction + lockForUpdate → Appointment created
               → POST cancel           → CancelAppointmentController → state→cancelled
```

### Booking Flow Detail

```
[DoctorList] → click "Book" → [BookAppointment: select date]
                              → Livewire calls DoctorAvailabilityService::slots($doctorId, $date)
                              → renders available slots
                              → patient selects slot + confirms
                              → DB transaction {
                                  lockForUpdate on doctor+slot
                                  check not taken
                                  check 2h anticipation
                                  check no patient overlap
                                  INSERT appointment (state=pending)
                                }
                              → redirect to /patient/dashboard
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `bootstrap/app.php` | Modify | Add `then:` callback to load `routes/web-patient.php` |
| `routes/web-patient.php` | Create | Patient web routes with guest/auth middleware groups |
| `app/Http/Controllers/Patient/AuthController.php` | Create | Register + login handling via `Auth::guard('web')` |
| `app/Http/Controllers/Patient/ProfileController.php` | Create | Patient profile view/edit |
| `app/Http/Controllers/Patient/CancelAppointmentController.php` | Create | Cancel with 24h window check |
| `app/Livewire/Patient/Dashboard.php` | Create | Full-page: upcoming appointments list |
| `app/Livewire/Patient/DoctorList.php` | Create | Full-page: doctor browsing with specialty filter |
| `app/Livewire/Patient/BookAppointment.php` | Create | Full-page: slot viewing + booking flow |
| `resources/views/layouts/patient.blade.php` | Create | Patient-specific layout (Tailwind) |
| `resources/views/patient/dashboard.blade.php` | Create | Dashboard page |
| `resources/views/patient/doctors.blade.php` | Create | Doctor listing with Livewire component |
| `resources/views/patient/book.blade.php` | Create | Booking page with Livewire component |
| `resources/views/patient/profile.blade.php` | Create | Profile page |
| `resources/views/patient/auth/login.blade.php` | Create | Login form |
| `resources/views/patient/auth/register.blade.php` | Create | Registration form |
| `tests/Feature/Patient/PatientRegistrationTest.php` | Create | Registration scenarios |
| `tests/Feature/Patient/PatientAuthTest.php` | Create | Auth scenarios |
| `tests/Feature/Patient/PatientDashboardTest.php` | Create | Dashboard scenarios |
| `tests/Feature/Patient/DoctorListingTest.php` | Create | Doctor listing + filter scenarios |
| `tests/Feature/Patient/AppointmentBookingTest.php` | Create | Booking + race condition scenarios |
| `tests/Feature/Patient/AppointmentCancellationTest.php` | Create | Cancellation window scenarios |

## Interfaces / Contracts

No new PHP interfaces. The Livewire components use the same Eloquent models and `DoctorAvailabilityService` as the API layer.

Route pattern:
```php
// routes/web-patient.php
Route::prefix('patient')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'createLogin'])->name('patient.login');
        Route::post('login', [AuthController::class, 'storeLogin']);
        Route::get('register', [AuthController::class, 'createRegister'])->name('patient.register');
        Route::post('register', [AuthController::class, 'storeRegister']);
    });

    Route::middleware('auth')->group(function () {
        Route::get('dashboard', Dashboard::class)->name('patient.dashboard');
        Route::get('doctors', DoctorList::class)->name('patient.doctors');
        Route::get('doctors/{doctor}/book', BookAppointment::class)->name('patient.book');
        Route::post('appointments/{appointment}/cancel', [CancelAppointmentController::class, '__invoke'])->name('patient.cancel');
        Route::get('profile', [ProfileController::class, 'edit'])->name('patient.profile');
        Route::post('profile', [ProfileController::class, 'update']);
    });
});
```

Livewire component signatures:
```php
class Dashboard extends Component // route: /patient/dashboard
class DoctorList extends Component // route: /patient/doctors (?specialty=...)
class BookAppointment extends Component // route: /patient/doctors/{doctor}/book
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Feature | Registration (happy + duplicate) | `POST /patient/register`, assert redirect + DB row |
| Feature | Login (valid + invalid) | `POST /patient/login`, assert session + redirect |
| Feature | Dashboard (with + without appointments) | Authenticated `GET /patient/dashboard` |
| Feature | Doctor listing (all + filtered) | `GET /patient/doctors`, assert visibility |
| Feature | Booking (happy + race condition) | `DoctorAvailabilityService` slots → `POST` book |
| Feature | Cancellation (inside + outside window) | Factory appointment → cancel → assert state |

Pest feature tests using `uses(RefreshDatabase::class)`. Create fixtures via `User::factory()->patient()->create()` + `Patient::factory()->for($user)->create()`. Auth via `$this->actingAs($user)`. Each test file covers one REQ from the spec (7 files for 7 REQs, 14 scenarios).

## Migration / Rollout

No migration required. All new code — no schema changes, no data migration. Rollback: remove `then:` callback from `bootstrap/app.php` and delete new files.

## Open Questions

- [ ] None — all decisions resolved in the proposal + orchestrator briefing.
