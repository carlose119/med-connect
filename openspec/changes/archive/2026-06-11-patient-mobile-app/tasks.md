---
change: patient-mobile-app
status: tasks
created: 2026-06-11
---

# Tasks: Patient Mobile App

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated new files | ~33 (10 screens, 4 services, 4 types, 8 components, navigation, package.json) |
| Estimated modified files | ~4 (apiClient.ts, authService.ts, MainNavigator.tsx, package.json) |
| Estimated total lines | ~1100–1300 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | 5 chained PRs (see work units below) |
| Delivery strategy | ask-on-risk |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: stacked-to-main
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | API paths + type definitions | PR 1 | apiClient.ts, authService.ts, 4 type files. Base = main |
| 2 | Services + UI components | PR 2 | 4 service files, 8 UI components. Base = main (independent of PR 1) |
| 3 | Navigation scaffold | PR 3 | TabNavigator.tsx, replace MainNavigator.tsx. Base = main |
| 4 | Citas Stack (5 screens) | PR 4 | All 5 screens under `appointments/`. Base = PR 3 (uses TabNavigator) |
| 5 | Historial + Recetas + Profile (5 screens) | PR 5 | All remaining screens. Base = PR 4 |

**Alternative: Feature Branch Chain** — PR 1→2→3→4→5 all target `feature/patient-mobile` (tracker branch); only the tracker merges to main. Best if team wants coordinated release and rollback control per slice.

---

## Phase 1: API Path Fix (Critical — must come first)

- [x] 1.1 Update `apiClient.ts` refresh URL: `/api/auth/refresh` → `/api/v1/auth/refresh` (line ~106)
- [x] 1.2 Update `apiClient.ts` refresh path guard: `url?.includes('/api/auth/refresh')` → `/api/v1/auth/refresh`
- [x] 1.3 Update `authService.ts` login path: `/api/auth/login` → `/api/v1/auth/login` (line ~16)
- [x] 1.4 Update `authService.ts` register path: `/api/auth/register` → `/api/v1/auth/register` (line ~27)
- [x] 1.5 Update `authService.ts` logout path: `/api/auth/logout` → `/api/v1/auth/logout` (line ~39)
- [x] 1.6 Update `authService.ts` me path: `/api/auth/me` → `/api/v1/auth/me` (line ~48)

---

## Phase 2: Type Definitions (Foundation)

- [x] 2.1 Create `src/types/doctor.ts` with `Doctor`, `Specialty`, `DoctorAvailability` interfaces
- [x] 2.2 Create `src/types/appointment.ts` with `AppointmentState`, `Appointment`, `AppointmentCreate`
- [x] 2.3 Create `src/types/medical-history.ts` with `MedicalHistory`, `MedicalNote` interfaces
- [x] 2.4 Create `src/types/prescription.ts` with `PrescriptionStatus`, `Prescription`, `PrescriptionDetail`, `PrescriptionItem`

---

## Phase 3: Service Layer (Foundation)

- [x] 3.1 Create `src/services/doctorService.ts` with `getDoctors`, `getDoctor`, `getAvailability` (all v1 paths)
- [x] 3.2 Create `src/services/appointmentService.ts` with `getAppointments`, `bookAppointment`, `cancelAppointment`
- [x] 3.3 Create `src/services/medicalHistoryService.ts` with `getHistory`, `getNoteDetail`
- [x] 3.4 Create `src/services/prescriptionService.ts` with `getPrescriptions`, `getPrescriptionDetail`, `getPrescriptionPdfUrl`

---

## Phase 4: UI Components (Foundation)

- [x] 4.1 Create `src/components/Button.tsx` with `title`, `onPress`, `loading?`, `variant?` ('primary'|'secondary'|'danger')
- [x] 4.2 Create `src/components/Card.tsx` with `children`, `style?` (white bg, borderRadius 12, shadow)
- [x] 4.3 Create `src/components/LoadingSpinner.tsx` wrapping `ActivityIndicator`
- [x] 4.4 Create `src/components/EmptyState.tsx` with `message`, `icon?`
- [x] 4.5 Create `src/components/ErrorBanner.tsx` with `message`, `onRetry`
- [x] 4.6 Create `src/components/SlotChip.tsx` with `time`, `selected`, `onPress`
- [x] 4.7 Create `src/components/AppointmentCard.tsx` with `appointment`, `onCancel?` (24h guard on cancel)
- [x] 4.8 Create `src/components/DoctorCard.tsx` with `doctor`, `onPress`
- [x] 4.9 Create `src/components/StatusBadge.tsx` with `status` (color-coded: pending=amber, confirmed=green, etc.)

---

## Phase 5: Navigation

- [x] 5.1 Create `src/navigation/TabNavigator.tsx` with 4 tab screens (Citas, Historial, Recetas, Perfil), each containing a nested native stack. `unmountOnBlur: false` on each `Tab.Screen`. Icons: calendar, clipboard, pill, user.
- [x] 5.2 Replace `src/navigation/MainNavigator.tsx` to render `<TabNavigator />`
- [x] 5.3 Add `@react-navigation/bottom-tabs` to `package.json` and run `npm install`

---

## Phase 6: Citas Stack (5 screens)

- [x] 6.1 Create `src/screens/main/appointments/AppointmentsScreen.tsx` — segmented SectionList (upcoming/past), `AppointmentCard`, "Buscar doctor" button → `DoctorList`
- [x] 6.2 Create `src/screens/main/appointments/DoctorListScreen.tsx` — FlatList of `DoctorCard`, specialty filter chips, `getDoctors(specialtyId?)`
- [x] 6.3 Create `src/screens/main/appointments/DoctorDetailScreen.tsx` — Doctor profile header (initials avatar), bio, "Ver disponibilidad" → `DoctorAvailability`
- [x] 6.4 Create `src/screens/main/appointments/DoctorAvailabilityScreen.tsx` — `@react-native-community/datetimepicker` date selector, `getAvailability(doctorId, date, tz)`, `SectionList` grouped by date with `SlotChip`
- [x] 6.5 Create `src/screens/main/appointments/BookAppointmentScreen.tsx` — Summary card, `bookAppointment()` on confirm, 409 → "Este horario ya no está disponible", success → pop to root with refresh param

---

## Phase 7: Historial Stack (2 screens)

- [x] 7.1 Create `src/screens/main/history/MedicalHistoryScreen.tsx` — `FlatList` of timeline entries, `getHistory()`, EmptyState "Aún no tienes historial clínico", tap → `MedicalNoteDetail`
- [x] 7.2 Create `src/screens/main/history/MedicalNoteDetailScreen.tsx` — Read-only `Card` with symptoms, diagnosis, treatment, doctor, date. No edit controls.

---

## Phase 8: Recetas Stack (2 screens)

- [x] 8.1 Create `src/screens/main/prescriptions/PrescriptionsScreen.tsx` — `FlatList` of `PrescriptionRow` (code, doctor, date, `StatusBadge`), `getPrescriptions()`, EmptyState "Aún no tienes recetas"
- [x] 8.2 Create `src/screens/main/prescriptions/PrescriptionDetailScreen.tsx` — `Card` with `PrescriptionItem` list, "Ver PDF" button calling `Linking.openURL(getPrescriptionPdfUrl(id))`

---

## Phase 9: Profile Tab

- [x] 9.1 Create `src/screens/main/profile/ProfileScreen.tsx` — Profile card from `AuthContext` user (name, email, registration date), "Cerrar sesión" button → `logout()` → navigate to `AuthNavigator`

---

## Notes

- Avatar: initials placeholder (first letter of name) — no external image in v1
- Cancel confirmation: `Alert.alert` with two buttons ("Cancelar", "Sí, cancelar")
- DatePicker: native `@react-native-community/datetimepicker`
- PDF: `Linking.openURL()` in device browser — interceptor appends Bearer token automatically
- 24h cancel window: check `start_time - now > 24h` before showing cancel button
- 409 booking conflict: show error message, refresh availability slots
- Style conventions: borderRadius 10, shadowOpacity 0.1, primary `#1a73e8` (from LoginScreen pattern)