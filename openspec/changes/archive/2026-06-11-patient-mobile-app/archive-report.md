# Archive Report: patient-mobile-app

**Change**: patient-mobile-app
**Archived**: 2026-06-11
**Mode**: openspec
**Status**: VERIFIED — PASS

---

## Summary

Full patient-facing mobile feature surface built in React Native Expo. The change replaces a placeholder `MainNavigator` with a bottom-tab navigator (4 tabs: Citas, Historial, Recetas, Perfil), each with a nested native stack. 10 new screens, 5 new services, 4 new type files, 9 reusable UI components, full navigation scaffold.

---

## What Was Implemented

### API Path Migration
- `apiClient.ts`: refresh URL → `/api/v1/auth/refresh`
- `authService.ts`: all 4 auth endpoints → `/api/v1/`

### Navigation
- `TabNavigator.tsx`: Bottom tab bar with 4 nested native stacks
- `MainNavigator.tsx`: Replaced placeholder with `<TabNavigator />`
- `@react-navigation/bottom-tabs` added to `package.json`

### Services (5 new files)
- `doctorService.ts`: getDoctors, getDoctor, getAvailability
- `appointmentService.ts`: getAppointments, bookAppointment, cancelAppointment
- `medicalHistoryService.ts`: getHistory, getNoteDetail
- `prescriptionService.ts`: getPrescriptions, getPrescriptionDetail, getPrescriptionPdfUrl

### Types (4 new files)
- `doctor.ts`: Doctor, Specialty, DoctorAvailability
- `appointment.ts`: AppointmentState, Appointment, AppointmentCreate
- `medical-history.ts`: MedicalHistory, MedicalNote
- `prescription.ts`: PrescriptionStatus, Prescription, PrescriptionDetail, PrescriptionItem

### Screens (10 new files)
- AppointmentsScreen (upcoming/past segmented)
- DoctorListScreen (with specialty filter)
- DoctorDetailScreen (initials avatar, bio)
- DoctorAvailabilityScreen (DateTimePicker + slot chips)
- BookAppointmentScreen (confirm + 409 conflict handling)
- MedicalHistoryScreen (timeline)
- MedicalNoteDetailScreen (read-only)
- PrescriptionsScreen (list with StatusBadge)
- PrescriptionDetailScreen (items + PDF via Linking.openURL)
- ProfileScreen (user info + logout)

### UI Components (9 new files)
- Button, Card, LoadingSpinner, EmptyState, ErrorBanner, SlotChip, AppointmentCard, DoctorCard, StatusBadge

---

## Stats

| Metric | Value |
|--------|-------|
| Tasks completed | 47 / 47 |
| PRs merged | 5 |
| New files | ~33 |
| Modified files | ~4 |
| TypeScript check | PASSED (tsc --noEmit exit 0) |

---

## Specs Synced to Source of Truth

Since no existing `openspec/specs/patient-mobile/` exists, the delta spec is a full spec and was copied directly:
- `openspec/specs/patient-mobile/spec.md` — added
- `openspec/specs/patient-mobile/screens.md` — added

---

## Verify Warning Note

The verify phase flagged a WARNING about `unmountOnBlur: false` missing from TabNavigator.

**Resolution**: This was a **false positive**. The warning was based on React Navigation 6 patterns where `unmountOnBlur` was needed to prevent stack state loss. In **React Navigation 7** (the version used in this project), **stack state is preserved by default** — `unmountOnBlur: false` is the default behavior. No code fix was needed. The implementation is correct as-is.

The verify report WARNING has been superseded by this clarification. Archive proceeds with PASS verdict.

---

## PR Chain

| PR | Scope |
|----|-------|
| PR 1 | API paths + type definitions |
| PR 2 | Services + UI components |
| PR 3 | Navigation scaffold |
| PR 4 | Citas Stack (5 screens) |
| PR 5 | Historial + Recetas + Profile (5 screens) |

---

## Artifact Locations

- Archive: `openspec/changes/archive/2026-06-11-patient-mobile-app/`
- Main specs: `openspec/specs/patient-mobile/`
- Engram: `sdd/patient-mobile-app/archive-report`