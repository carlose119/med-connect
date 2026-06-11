# Design: Doctor Web Portal

## Technical Approach

Add two Filament custom pages inside the existing `/doctor` panel. No separate portal, no new routes. Walk-in flow: register patient → redirect to consultation → history check → notes → complete → prescribe.

## Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Build inside Filament vs separate portal | Filament custom pages | Doctor already authenticated at `/doctor`; no need for separate login |
| Walk-in patient registration | `DB::transaction(User::create + Patient::create)` | Consistent with patient portal pattern |
| Medical history creation | `firstOrCreate` | 1:1 constraint; avoid duplicate |
| Walk-in appointment | `start_time = now()`, `state = 'pending'` | No slot needed; doctor handles time |
| Prescription issuance | `IssuePrescriptionAction` | Reuse existing action; already validates `completed` state |
| Navigation | Sidebar link "Paciente sin Cita" | Visible, accessible from any page |

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Filament/Doctor/Pages/RegisterWalkInPatient.php` | Create | Walk-in patient registration form |
| `app/Filament/Doctor/Pages/WalkInConsultation.php` | Create | Full consultation flow |
| `app/Providers/Filament/DoctorPanelProvider.php` | Modify | Register pages + sidebar nav |

## Data Flow

```
Doctor panel → "Paciente sin Cita" sidebar link
    → RegisterWalkInPatient (form)
        → DB::transaction(User + Patient)
        → Redirect /doctor/consultation/{patient_id}
    → WalkInConsultation (patient_id param)
        → MedicalHistory::firstOrCreate(patient_id)
        → Appointment::create(start_time=now, state='pending')
        → MedicalNote::create (append-only)
        → AppointmentTransition::complete
        → IssuePrescriptionAction
```

## Interface Details

### RegisterWalkInPatient
- Form: name, identification_number, email, phone, birth_date, gender
- Submit: validate → check duplicate ID → DB::transaction → redirect

### WalkInConsultation
- Mount: receive patient_id → firstOrCreate history → firstOrCreate appointment
- Notes form: symptoms, physical_exam, diagnosis, treatment_notes
- Actions: Save Note, Confirm Appointment, Complete Consultation, Issue Prescription

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Feature | Walk-in registration, consultation flow, prescription | Filament HTTP tests |
| Integration | Full walk-in from register to prescription | Feature test |

## Migration / Rollout

No database migration needed — all tables already exist.