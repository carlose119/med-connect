# Tasks — doctor-web-portal

**Delivery strategy**: `size:exception` (single PR, maintainer-approved)
**Testing mode**: Standard
**Mode**: openspec

## Review Workload Forecast
- **400-line budget risk**: Medium — estimated ~450-500 lines across all phases
- **Chained PRs recommended**: No
- **Decision needed before apply**: Yes — `size:exception` granted by orchestrator

## Phase 1: Walk-in Registration Page

- [x] 1.1 Create `app/Filament/Doctor/Pages/RegisterWalkInPatient.php` — Filament page with form: name, identification_number, email, phone, birth_date, gender. On submit: `DB::transaction(User::create + Patient::create)`. Redirect to consultation page with patient_id.
- [x] 1.2 Register the page in `DoctorPanelProvider.php`:
  ```php
  ->pages([
      ...existing pages...
      Pages\RegisterWalkInPatient::route('/register-walk-in'),
  ])
  ```
- [x] 1.3 Add sidebar navigation link "Paciente sin Cita" → `/register-walk-in` in `DoctorPanelProvider.php`

## Phase 2: Walk-in Consultation Page

- [x] 2.1 Create `app/Filament/Doctor/Pages/WalkInConsultation.php` — receives patient_id from query param. Mount: check/create MedicalHistory, create walk-in appointment (start_time=now(), state='pending', end_time=now()->addMinutes(30)). Form for medical notes (symptoms, physical_exam, diagnosis, treatment_notes). Actions: "Guardar Nota" (creates MedicalNote), "Completar Consulta" (transitions appointment confirmed→completed), "Emitir Receta" (shows prescription items form, issues via IssuePrescriptionAction).
- [x] 2.2 Register the page in `DoctorPanelProvider.php`:
  ```php
  ->pages([
      ...existing...
      Pages\WalkInConsultation::route('/consultation/{patient_id}'),
  ])
  ```

## Phase 3: Testing

- [x] 3.1 Write `tests/Feature/Doctor/WalkInRegistrationTest.php` — test walk-in patient registration through Filament page
- [x] 3.2 Write `tests/Feature/Doctor/WalkInConsultationTest.php` — test full consultation flow: register → consultation → notes → complete → prescribe
- [x] 3.3 Run full Pest suite and confirm all pass