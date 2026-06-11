# Proposal: Doctor Web Portal

## Intent

Solve RF-3.1: doctors need to register walk-in patients, create medical histories, write notes, and issue prescriptions without a pre-booked appointment. The Filament doctor panel at `/doctor` has appointment management but lacks walk-in patient registration and quick consultation flow.

## Scope

### In Scope
- Walk-in patient registration inside the Filament doctor panel (User + Patient in 1 transaction)
- Walk-in consultation page: create medical history + write notes + complete appointment + issue prescription
- Navigation link in doctor panel sidebar for "Paciente sin Cita"

### Out of Scope
- Separate Livewire portal (not needed — Filament handles it)
- Mobile app changes
- Admin panel modifications
- API route changes beyond what's needed for Filament pages

## Capabilities

### New Capabilities
- `doctor-portal`: walk-in patient management inside the Filament doctor panel

### Modified Capabilities
- `clinical-records`: walk-in patient registration and history creation from the doctor panel
- `prescriptions`: walk-in prescription issuance for completed appointments

## Approach

**Filament custom pages** — Add two custom pages to the existing doctor panel:
1. `RegisterWalkInPatient.php` — register patient, redirect to consultation
2. `WalkInConsultation.php` — full consultation flow with notes, complete, and prescription

Reuse existing models (User, Patient, MedicalHistory, MedicalNote, Prescription, Appointment) and existing actions (IssuePrescriptionAction, CompleteAppointmentTransition). No new routes or API endpoints needed — Filament pages interact directly with models.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Filament/Doctor/Pages/RegisterWalkInPatient.php` | New | Walk-in patient registration |
| `app/Filament/Doctor/Pages/WalkInConsultation.php` | New | Consultation flow |
| `app/Providers/Filament/DoctorPanelProvider.php` | Modified | Register pages + sidebar nav |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Duplicate patient registration | Low | Check `identification_number` before creating |
| Medical history 1:1 duplicate | Low | Use `firstOrCreate` pattern |
| Prescription before completion | Low | Guard checks `state instanceof Completed` |

## Rollback Plan
- Delete `app/Filament/Doctor/Pages/RegisterWalkInPatient.php`
- Delete `app/Filament/Doctor/Pages/WalkInConsultation.php`
- Revert `DoctorPanelProvider.php` to remove page registrations and navigation
- No DB migration needed

## Success Criteria

- [ ] Doctor sees "Paciente sin Cita" link in panel sidebar
- [ ] Doctor can register a walk-in patient (User + Patient)
- [ ] Doctor can create medical history for walk-in patient
- [ ] Doctor can write consultation notes
- [ ] Doctor can complete the walk-in appointment
- [ ] Doctor can issue prescription for completed walk-in
- [ ] All existing tests still pass