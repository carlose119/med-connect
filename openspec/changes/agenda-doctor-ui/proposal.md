# Proposal: Agenda Doctor UI

## Intent

Doctors need to see their upcoming appointments and manage them from the existing `/doctor` Filament panel. Currently only the admin panel and API routes can perform state transitions. This gives doctors direct access to their appointments without leaving Filament.

## Scope

### In Scope
- "My Appointments" navigation item in the `/doctor` panel
- Read-only AppointmentResource with table (date, time, patient name, status)
- Custom table actions: Confirm, Complete, Cancel, NoShow — using existing Transition classes
- Date-based filters: Today, Upcoming, Past
- `discoverResources()` added to `DoctorPanelProvider` for doctor-specific resources

### Out of Scope
- Creating appointments from doctor panel (patients book via API)
- Editing appointment details (time/doctor changes)
- Admin-level "all appointments" view
- Patient notifications on transition
- Bulk actions or batch transitions

## Capabilities

### New Capabilities
- `agenda-doctor-panel`: Doctor's Filament view of their own appointments with state machine transition actions.

### Modified Capabilities
None — no spec-level behavior changes. The state machine already requires the assigned doctor to transition. This adds a UI layer on top of existing transitions.

## Approach

Read-only `AppointmentResource` registered under a new `app/Filament/Doctor/Resources/` namespace. `DoctorPanelProvider` gains `discoverResources(in: app_path('Filament/Doctor/Resources'), for: 'App\Filament\Doctor\Resources')`. Each table action wraps an existing Transition class (`ConfirmAppointmentTransition`, etc.) in a Filament `Action` that calls `$record->state->transitionTo(...)`. Queries scoped to `auth()->user()->doctor->appointments()`. Follows existing pattern of extracted table/schema classes.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Providers/Filament/DoctorPanelProvider.php` | Modified | Add `discoverResources()` |
| `app/Filament/Doctor/Resources/AppointmentResource.php` | New | Read-only resource, actions, navigation |
| `app/Filament/Doctor/Resources/AppointmentResource/Tables/` | New | Extracted table schema |
| `app/Filament/Doctor/Resources/AppointmentResource/Pages/` | New | List page only (no create/edit) |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| First doctor-specific resource — no path convention established | Low | Follow existing `app/Filament/Resources/` pattern exactly |
| Actions must use existing Transition classes, not bypass | Low | Each action calls `transitionTo()` with the correct Transition class |
| Only list page — Filament expects create/edit by default | Low | Set `canCreate(false)`, disable edit page explicitly |

## Rollback Plan

1. Remove `discoverResources()` line from `DoctorPanelProvider.php`
2. Delete `app/Filament/Doctor/Resources/AppointmentResource.php` and subdirectories
3. No DB changes to roll back — read-only UI only

## Dependencies

- `agenda` spec — state machine contracts already defined
- `users-roles` spec — `/doctor` panel already exists with access control
- Existing `ConfirmAppointmentTransition`, `CompleteAppointmentTransition`, `CancelAppointmentTransition`, `MarkNoShowAppointmentTransition` classes

## Success Criteria

- [ ] Doctor sees their appointments in `/doctor` panel, scoped to their own appointments
- [ ] Doctor can confirm a pending appointment
- [ ] Doctor can complete a confirmed appointment
- [ ] Doctor can cancel an appointment (any allowed state)
- [ ] Doctor can mark a no-show on a confirmed appointment
- [ ] Existing admin `AppointmentResource` and API routes unchanged
- [ ] Full test suite green
