---
change: patient-mobile-app
status: verify
created: 2026-06-11
---

# Verification Report: patient-mobile-app

## Spec Compliance

| Scenario | Status | Evidence |
|----------|--------|----------|
| API Path Migration (all /api/v1/) | ✅ PASS | `apiClient.ts:106` refresh → `/api/v1/auth/refresh`; `authService.ts` lines 16, 27, 39, 48 all `/api/v1/`; all 4 new services use `/api/v1/` |
| Doctor Browsing — list | ✅ PASS | `DoctorListScreen.tsx` calls `getDoctors(specialtyId?)`, renders `DoctorCard` FlatList, specialty chip filter via `getSpecialties()` |
| Doctor Browsing — detail + availability | ✅ PASS | `DoctorDetailScreen.tsx` calls `getDoctor(doctorId)`, initials avatar, "Ver disponibilidad" → `DoctorAvailabilityScreen` calls `getAvailability(doctorId, date)` with `DateTimePicker` |
| Appointment Booking | ✅ PASS | `BookAppointmentScreen.tsx` calls `bookAppointment({doctor_id, start_time})`, 409 → alert + goBack, success → `CommonActions.reset` to Appointments |
| Appointment Cancellation (24h guard) | ✅ PASS | `AppointmentsScreen.tsx` has `Alert.alert` confirm flow; `AppointmentCard.tsx:24-29` `canCancel()` checks `hoursUntil > 24`, warning shown when not cancellable |
| Medical History Read-Only | ✅ PASS | `MedicalHistoryScreen.tsx` calls `getHistory()` + `getNotes()`; `MedicalNoteDetailScreen.tsx` renders Card with fields, **no edit controls present** |
| Prescription List | ✅ PASS | `PrescriptionsScreen.tsx` calls `getPrescriptions()`, renders FlatList with code/doctor/date/StatusBadge, empty state |
| Prescription PDF (Linking.openURL) | ✅ PASS | `PrescriptionDetailScreen.tsx:44-56` calls `Linking.openURL(getPrescriptionPdfUrl(id))`, button title "Ver PDF de la receta" |
| Profile + Logout | ✅ PASS | `ProfileScreen.tsx:15` uses `useAuth()` user; `handleLogout` calls `authService.logout()` with Alert confirm |

## Design Compliance

| Decision | Status | Evidence |
|----------|--------|----------|
| Bottom tabs + nested stacks | ⚠️ WARNING | `TabNavigator.tsx` creates 4 nested `createNativeStackNavigator()` inside `createBottomTabNavigator()`. `presentation: 'card'` on all stacks. However, **`unmountOnBlur: false` is missing from Tab.Screen options** — tab switching will reset nested stack state instead of preserving it |
| PDF via Linking.openURL | ✅ PASS | `PrescriptionDetailScreen.tsx:49` `await Linking.openURL(url)`, with `canOpenURL` check |
| Native DateTimePicker | ✅ PASS | `DoctorAvailabilityScreen.tsx:3` imports `DateTimePicker` from `@react-native-community/datetimepicker`; used on line 80 |
| Initials avatar | ✅ PASS | `DoctorDetailScreen.tsx:44` derives initials from `doctor.name`; `ProfileScreen.tsx:34-39` derives initials from `user?.name` |
| Alert cancel confirmation | ✅ PASS | `AppointmentsScreen.tsx:45-63` `Alert.alert('Cancelar cita', '¿Estás seguro...?')` with destructive "Sí, cancelar" |

## TypeScript Check

```
npx tsc --noEmit
```
**PASSED** — exit code 0, zero errors.

## Deviations from Design

| Deviation | Severity | Reason |
|-----------|----------|--------|
| `unmountOnBlur: false` not set on Tab.Screen | WARNING | Design §Tab navigation config and screens.md require `unmountOnBlur: false` on each Tab.Screen to preserve nested stack state. Currently absent from `tabScreenOptions`. Tab switches will reset each tab's stack to root. |
| `PrescriptionDetail` interface unused | SUGGESTION | Design defines `PrescriptionDetail` type extending `Prescription` with `items`. `prescriptionService.getPrescriptionDetail` returns `Prescription` (which already has `items`), so the `PrescriptionDetail` interface is never referenced. Type-level accuracy only; runtime behavior is correct. |
| `getHistory()` returns single object vs array | SUGGESTION | Spec scenario says `GET /api/v1/medical-history` returns entries (plural). Type `MedicalHistory` is a single object (the history record). `MedicalHistoryScreen` correctly calls `getHistory()` + `getNotes()` to get the notes array. Works correctly at runtime. |

## Issues Found

| Issue | Severity | Resolution |
|-------|----------|--------|
| `unmountOnBlur: false` missing | **WARNING** | Add `unmountOnBlur: false` to each Tab.Screen option object in `TabNavigator.tsx`. Example: `<Tab.Screen name="CitasTab" options={{ ..., unmountOnBlur: false }} ... />`. Without this, the Citas stack resets to `Appointments` root every time the patient switches tabs during a booking flow, losing the doctor-browse progress. |

## Verdict

- **CRITICAL**: 0
- **WARNING**: 1
- **SUGGESTION**: 2

**Ready for archive**: No — fix `unmountOnBlur: false` before archiving.

---

## Detailed Compliance Notes

### What was verified

**Source files inspected:**
- `src/services/apiClient.ts`, `src/services/authService.ts`, `src/services/doctorService.ts`, `src/services/appointmentService.ts`, `src/services/medicalHistoryService.ts`, `src/services/prescriptionService.ts`
- `src/navigation/TabNavigator.tsx`, `src/navigation/MainNavigator.tsx`
- `src/screens/main/appointments/AppointmentsScreen.tsx`, `DoctorListScreen.tsx`, `DoctorDetailScreen.tsx`, `DoctorAvailabilityScreen.tsx`, `BookAppointmentScreen.tsx`
- `src/screens/main/history/MedicalHistoryScreen.tsx`, `MedicalNoteDetailScreen.tsx`
- `src/screens/main/prescriptions/PrescriptionsScreen.tsx`, `PrescriptionDetailScreen.tsx`
- `src/screens/main/profile/ProfileScreen.tsx`
- `src/components/AppointmentCard.tsx`
- `src/types/doctor.ts`, `src/types/appointment.ts`, `src/types/medical-history.ts`, `src/types/prescription.ts`

**47/47 tasks from tasks.md marked complete.**

**5 PRs merged** (per apply-progress from Engram).

### The one thing to fix before archive

In `TabNavigator.tsx`, add `unmountOnBlur: false` to the options of each `Tab.Screen`:

```tsx
<Tab.Screen
  name="CitasTab"
  component={CitasStack}
  options={{ title: 'Citas', tabBarIcon: () => <TabIcon emoji="📅" />, unmountOnBlur: false }}
/>
<Tab.Screen
  name="HistorialTab"
  component={HistorialStack}
  options={{ title: 'Historial', tabBarIcon: () => <TabIcon emoji="📋" />, unmountOnBlur: false }}
/>
<Tab.Screen
  name="RecetasTab"
  component={RecetasStack}
  options={{ title: 'Recetas', tabBarIcon: () => <TabIcon emoji="💊" />, unmountOnBlur: false }}
/>
<Tab.Screen
  name="PerfilTab"
  component={ProfileStack}
  options={{ title: 'Perfil', tabBarIcon: () => <TabIcon emoji="👤" />, unmountOnBlur: false }}
/>
```