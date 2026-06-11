# Exploration: Patient Mobile App — React Native Expo

**Change**: patient-mobile-app
**Date**: 2026-06-11
**Mode**: openspec
**Author**: sdd-explore sub-agent

---

## Current State

The mobile app has authentication complete (Login + Register screens, AuthContext, tokenStorage, biometric auth) and a placeholder MainNavigator that shows only a welcome HomeScreen with no navigation structure.

**What's done:**
- `src/screens/auth/LoginScreen.tsx` ✅
- `src/screens/auth/RegisterScreen.tsx` ✅
- `src/services/apiClient.ts` ✅ (needs path update to `/api/v1/`)
- `src/services/authService.ts` ✅ (needs path update to `/api/v1/`)
- `src/services/biometricService.ts` ✅
- `src/services/tokenStorage.ts` ✅
- `src/contexts/AuthContext.tsx` ✅
- `src/navigation/AppNavigator.tsx` ✅
- `src/navigation/AuthNavigator.tsx` ✅
- `src/navigation/MainNavigator.tsx` — placeholder HomeScreen only

**Stack**: Expo 56, React 19, React Navigation 7 (native-stack), Axios, react-hook-form + Zod, AsyncStorage.

---

## Affected Areas

### Files to CREATE (new):

| Path | Purpose |
|------|---------|
| `src/types/doctor.ts` | `Doctor`, `Specialty`, `DoctorAvailability` interfaces |
| `src/types/appointment.ts` | `Appointment`, `AppointmentCreate` interfaces |
| `src/types/medical-history.ts` | `MedicalHistory`, `MedicalNote` interfaces |
| `src/types/prescription.ts` | `Prescription`, `PrescriptionItem` interfaces |
| `src/services/doctorService.ts` | `GET /api/v1/doctors`, `GET /api/v1/specialties`, `GET /api/v1/doctors/{id}/availability` |
| `src/services/appointmentService.ts` | `GET/POST/DELETE /api/v1/appointments` |
| `src/services/medicalHistoryService.ts` | `GET /api/v1/medical-history`, `GET /api/v1/medical-history/notes` |
| `src/services/prescriptionService.ts` | `GET /api/v1/prescriptions`, `GET /api/v1/prescriptions/{id}/pdf` |
| `src/screens/main/DoctorListScreen.tsx` | List doctors with specialty filter |
| `src/screens/main/DoctorDetailScreen.tsx` | Doctor profile + availability trigger |
| `src/screens/main/DoctorAvailabilityScreen.tsx` | Show available slots for a doctor |
| `src/screens/main/BookAppointmentScreen.tsx` | Create appointment flow |
| `src/screens/main/AppointmentsScreen.tsx` | Upcoming + past appointments |
| `src/screens/main/MedicalHistoryScreen.tsx` | Timeline of medical history + notes |
| `src/screens/main/MedicalNoteDetailScreen.tsx` | Single note detail view |
| `src/screens/main/PrescriptionsScreen.tsx` | List prescriptions |
| `src/screens/main/PrescriptionDetailScreen.tsx` | Prescription detail + PDF download |
| `src/screens/main/ProfileScreen.tsx` | User profile + logout button |
| `src/navigation/MainNavigator.tsx` | Replace with bottom-tab + stack navigators |
| `src/navigation/TabNavigator.tsx` | Bottom tab bar (new) |

### Files to MODIFY (existing):

| Path | Change |
|------|--------|
| `src/services/apiClient.ts` | Update base path already correct (`EXPO_PUBLIC_API_URL`), but `refresh` endpoint path needs update to `/api/v1/auth/refresh` |
| `src/services/authService.ts` | Update all paths from `/api/auth/*` to `/api/v1/auth/*` |
| `src/navigation/MainNavigator.tsx` | Replace placeholder with real navigation |
| `package.json` | Add `@react-navigation/bottom-tabs` dependency |
| `app.json` | Add deep linking scheme if needed |

---

## Key UX Decisions

### 1. Navigation Structure: Bottom Tab Bar vs Stack

**Option A — Bottom Tab Bar (4 tabs):**
- Tab 1: **Citas** (AppointmentsScreen)
- Tab 2: **Historial** (MedicalHistoryScreen)
- Tab 3: **Recetas** (PrescriptionsScreen)
- Tab 4: **Perfil** (ProfileScreen)
- Doctor browsing lives inside the Citas tab as a stack (DoctorListScreen → DoctorDetailScreen → DoctorAvailabilityScreen → BookAppointmentScreen)

**Option B — All Stack (no tabs):**
- MainNavigator stack with Home at root, push all screens from there
- User must go back through Home to switch between sections

**Recommendation: Option A (Bottom Tab Bar)**
- Industry standard for patient apps (similar to Health, Spotify, banking apps)
- Faster section switching — no back navigation needed to switch between appointments and history
- Each tab has its own stack, preserving navigation state per tab
- Doctor browsing (listing → detail → availability → booking) naturally fits as a sub-stack under Citas
- Profile/logout accessible from any screen via the Profile tab

**Effort: Medium** — requires installing `@react-navigation/bottom-tabs`, restructuring MainNavigator.

---

### 2. Prescription PDF Viewing

**Option A — Download + Share (expo-file-system + expo-sharing):**
- Call `GET /api/v1/prescriptions/{id}/pdf`
- Save to cache with expo-file-system
- Open with expo-sharing (Share sheet)

**Option B — Open in Browser:**
- Use `expo-linking` to open the PDF URL in the device's default browser
- Browser handles PDF rendering natively

**Option C — In-app WebView:**
- Use `react-native-webview` to render PDF inside the app
- Requires installing `react-native-webview` package

**Recommendation: Option B (Open in Browser)**
- Simplest implementation — one `Linking.openURL()` call
- No extra dependencies, no file system permissions
- PDF renders in browser (native PDF viewer on both iOS and Android)
- User can share from browser natively
- Backend serves PDF with proper `Content-Disposition` header

**Risk**: User leaves app context. If this is unacceptable, fall back to Option A (download + share).

**Effort: Low** — no new packages needed.

---

### 3. Date/Time Picker for Booking

**Option A — Native picker (expo-date-picker or custom):**
- Use `DateTimePicker` component from `@react-native-community/datetimepicker`
- Shows platform-native wheel picker for time selection

**Option B — Custom slot grid (calendar + time slots):**
- Fetch availability slots from `GET /api/v1/doctors/{id}/availability`
- Render as a grid of selectable time chips grouped by date
- User picks date first, then available time slot

**Recommendation: Option B (Custom slot grid)**
- Matches the backend's slot-based availability model (fetched from the API)
- Reduces invalid selections — users can only pick existing available slots
- Better UX: shows "unavailable" state clearly, no form validation for booking conflicts
- Calendar view lets user browse future availability

**Effort: Medium** — custom component, but the data model is already provided by the API.

---

### 4. Doctor Availability Display

**Option A — Time slot chips:**
- Available slots shown as tappable chips (e.g., "09:00", "09:30")
- Grouped by date, scrollable

**Option B — Time picker list:**
- List of available times as rows
- Select row → confirmation modal

**Recommendation: Option A (Time slot chips)**
- More compact, shows more options at once
- Natural fit for the slot model
- Easier to tap with larger touch targets
- Grouping by date with date headers keeps context clear

**Effort: Low** — straightforward FlatList with SectionList grouping by date.

---

## API Response Shapes (from backend resources)

### Doctor
```json
{
  "id": 1,
  "user_id": 1,
  "specialty_id": 1,
  "license_number": "...",
  "bio": "...",
  "specialty": { "id": 1, "name": "...", "slug": "..." },
  "user": { "id": 1, "name": "...", "email": "..." }
}
```

### Appointment
```json
{
  "id": 1,
  "doctor_id": 1,
  "patient_id": 1,
  "state": "pending",
  "start_time": "2026-06-15T09:00:00-03:00",
  "end_time": "2026-06-15T09:30:00-03:00",
  "notes": null,
  "cancellation_reason": null,
  "doctor": { "id": 1, "name": "Dr. ..." },
  "patient": { "id": 1, "name": "..." },
  "created_at": "2026-06-11T10:00:00-03:00"
}
```

### MedicalHistory
```json
{
  "id": 1,
  "patient_id": 1,
  "primary_doctor_id": 1,
  "opened_at": "2024-01-01T...",
  "notes_count": 5
}
```

### MedicalNote
```json
{
  "id": 1,
  "medical_history_id": 1,
  "doctor": { "id": 1, "name": "Dr. ..." },
  "symptoms": "...",
  "physical_exam": "...",
  "diagnosis": "...",
  "treatment_notes": "...",
  "corrects_note_id": null,
  "created_at": "2026-01-15T..."
}
```

### Prescription
```json
{
  "id": 1,
  "appointment_id": 1,
  "doctor_id": 1,
  "doctor": { "id": 1, "name": "Dr. ..." },
  "patient_id": 1,
  "unique_code": "RX-2026-0001",
  "issued_at": "2026-06-11T...",
  "status": "active",
  "cancellation_reason": null,
  "items": [
    { "id": 1, "medicine": "...", "dosage": "...", "frequency": "...", "duration": "...", "instructions": "..." }
  ]
}
```

---

## Screen Inventory

| Screen | Route | Parent | Description |
|--------|-------|--------|-------------|
| DoctorListScreen | DoctorList | Tab: Citas | List all doctors, filter by specialty |
| DoctorDetailScreen | DoctorDetail | DoctorList stack | Doctor profile + "Ver disponibilidad" CTA |
| DoctorAvailabilityScreen | DoctorAvailability | DoctorDetail stack | Slot grid for a specific doctor |
| BookAppointmentScreen | BookAppointment | DoctorAvailability stack | Confirm + POST appointment |
| AppointmentsScreen | Appointments | Tab: Citas root | Segmented list (upcoming / past) |
| MedicalHistoryScreen | MedicalHistory | Tab: Historial | Timeline of history entries + notes list |
| MedicalNoteDetailScreen | MedicalNoteDetail | MedicalHistory stack | Full note detail |
| PrescriptionsScreen | Prescriptions | Tab: Recetas | List of prescriptions |
| PrescriptionDetailScreen | PrescriptionDetail | Prescriptions stack | Detail + PDF download button |
| ProfileScreen | Profile | Tab: Perfil | User info + logout |

---

## Risks

1. **API v1 path migration**: authService and apiClient use old `/api/` paths. Must update to `/api/v1/` before building new screens, otherwise all calls fail.
2. **Availability endpoint shape unknown**: The backend `GET /api/v1/doctors/{id}/availability` response format was not inspected — need to check the controller/resource before implementing the slot picker.
3. **Refresh endpoint path**: `apiClient.ts` line 106 hardcodes `/api/auth/refresh` — needs update to `/api/v1/auth/refresh`.
4. **PDF endpoint requires auth header**: Must ensure the PDF download call (likely returns a blob) is handled by axios with the Bearer token interceptor — may need response type configuration.
5. **Navigation state per tab**: React Navigation 7 bottom tabs need proper configuration to preserve stack state per tab. Without it, switching tabs resets nested navigation.
6. **No existing UI components**: No shared UI library. Need to establish a basic component set (Button, Card, LoadingSpinner, EmptyState, ErrorState) before building screens, or copy patterns from LoginScreen.

---

## Readiness for Proposal

**Yes — ready for sdd-propose.**

Clarification needed before sdd-design:
1. Confirm availability endpoint response shape (slot list vs time range parsing)
2. Confirm if PDF endpoint returns blob or redirect URL
3. Confirm whether doctor list supports filtering/pagination (for scalability)

None of these are blockers — reasonable defaults can be chosen in the design phase.

---

## Recommended Change Order

1. **sdd-propose** → scope, intent, rollback
2. **sdd-spec** → detailed requirements for all 10 screens
3. **sdd-design** → navigation architecture, component inventory, API contract
4. **sdd-tasks** → break into phases (auth-fix → doctors → appointments → history → prescriptions → profile)
5. **sdd-apply** → implement per phase, starting with the API path fixes
6. **sdd-verify** → end-to-end testing of each feature
7. **sdd-archive** → merge deltas into main spec