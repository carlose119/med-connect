# Design: Patient Mobile App

**Change**: patient-mobile-app
**Mode**: openspec

---

## Technical Approach

Replace the placeholder `MainNavigator` with a bottom-tab navigator (`TabNavigator`) housing four native-stack navigators, one per tab. Each service hits `/api/v1/` endpoints via the existing `apiClient`. The Bearer token flows automatically through the request interceptor — no new auth plumbing. PDF opens in the device browser via `Linking.openURL()`; the axios interceptor appends the token to the URL as a query param.

**Stack**: Expo 56, React Navigation 7 (bottom-tabs + native-stack), TypeScript, Axios, react-hook-form + Zod, StyleSheet.

---

## Architecture Decisions

### Decision: Bottom Tabs with Nested Stacks

**Choice**: Bottom-tab bar with a `createBottomTabNavigator()` containing four nested `createNativeStackNavigator()` instances (CitasStack, HistorialStack, RecetasStack, ProfileStack).

**Alternatives considered**: Single flat stack with custom tab buttons; `@react-navigation/drawer`.

**Rationale**: React Navigation's `createBottomTabNavigator` handles tab state, icon labels, and `unmountOnBlur: false` natively. Nested stacks preserve per-tab navigation history when switching tabs — critical for the doctor-browse flow (list → detail → availability → book). Drawer was rejected as it adds gesture complexity not needed for 4 tabs.

---

### Decision: PDF via Device Browser (Not In-App)

**Choice**: `Linking.openURL(prescriptionPdfUrl)` opens the PDF in the device browser. The URL includes the auth token as a query param so the interceptor can inject it.

**Alternatives considered**: Download to cache with `expo-file-system` + `expo-sharing`; in-app PDF viewer (react-native-pdf).

**Rationale**: Spec says in-app rendering is out of scope. The device browser handles auth natively via the URL approach (no extra packages, no file system permissions). Rollback plan uses the expo-sharing fallback if the URL approach fails in production.

---

### Decision: One Service File Per Domain

**Choice**: `doctorService`, `appointmentService`, `medicalHistoryService`, `prescriptionService` — one file each.

**Alternatives considered**: A single `api/` service with all endpoints; a `services/index.ts` barrel.

**Rationale**: Mirrors the existing `authService` pattern. Each file is independently testable. Barrel files add indirection; a monolith service file creates merge conflicts as the team grows.

---

## Data Flow

```
User interaction
     │
     ▼
Screen component ──→ Service (apiClient.get/post) ──→ API /api/v1/*
     │                   │
     │              apiClient (axios)
     │                   │          │
     │         Request interceptor  Response interceptor
     │         (Bearer token)       (401 → refresh → retry)
     │
◀────┘
Screen renders { loading | data | error | empty }
```

```
Tab switching (unmountOnBlur: false)
     │
     ▼
Tab A stack (preserved)  │  Tab B stack (active)
     │                   │
     └───── NavigationContainer ─────────────┘
```

---

## Shared Infrastructure Changes

### `src/services/apiClient.ts` (Modify)

- Line 106: Change refresh URL from `/api/auth/refresh` → `/api/v1/auth/refresh`
- Line 87: Update the `url?.includes` guard to match the new path
- `responseType` handling: for PDF requests, services pass `{ responseType: 'blob' }` as a config override — `apiClient` itself is unchanged

### `src/services/authService.ts` (Modify)

- Line 16: `/api/auth/login` → `/api/v1/auth/login`
- Line 27: `/api/auth/register` → `/api/v1/auth/register`
- Line 39: `/api/auth/logout` → `/api/v1/auth/logout`
- Line 48: `/api/auth/me` → `/api/v1/auth/me`

### `src/contexts/AuthContext.tsx` (No changes)

Already calls `authService` methods. No path logic lives here.

---

## New Types

### `src/types/doctor.ts`

```typescript
export interface Doctor {
  id: number;
  name: string;
  specialty: Specialty;
  bio?: string;
  registration_number?: string;
}

export interface Specialty {
  id: number;
  name: string;
}

export interface DoctorAvailability {
  start_time: string; // ISO8601
  end_time: string;   // ISO8601
}
```

### `src/types/appointment.ts`

```typescript
export type AppointmentState = 'pending' | 'confirmed' | 'completed' | 'cancelled' | 'no_show';

export interface Appointment {
  id: number;
  doctor: Doctor;
  start_time: string;  // ISO8601
  end_time: string;
  state: AppointmentState;
  notes?: string;
}

export interface AppointmentCreate {
  doctor_id: number;
  start_time: string; // ISO8601 — the slot start
}
```

### `src/types/medical-history.ts`

```typescript
export interface MedicalHistory {
  id: number;
  date: string;         // ISO date
  type: 'note' | 'attachment';
  title: string;        // e.g. "Nota clínica"
  doctor_name: string;
  note_id?: number;     // present if type === 'note'
}

export interface MedicalNote {
  id: number;
  symptoms: string;
  diagnosis: string;
  treatment: string;
  notes?: string;
  doctor_name: string;
  created_at: string;
}
```

### `src/types/prescription.ts`

```typescript
export type PrescriptionStatus = 'active' | 'used' | 'expired';

export interface Prescription {
  id: number;
  unique_code: string;
  doctor_name: string;
  created_at: string;
  status: PrescriptionStatus;
}

export interface PrescriptionDetail extends Prescription {
  items: PrescriptionItem[];
}

export interface PrescriptionItem {
  id: number;
  medication: string;
  dosage: string;
  frequency: string;
  duration?: string;
  instructions?: string;
}
```

---

## New Services

### `src/services/doctorService.ts`

```typescript
import apiClient from './apiClient';
import type { Doctor, DoctorAvailability } from '../types/doctor';

export async function getDoctors(specialtyId?: number): Promise<Doctor[]> {
  const params = specialtyId ? { specialty_id: specialtyId } : undefined;
  const response = await apiClient.get<{ data: Doctor[] }>('/api/v1/doctors', { params });
  return response.data.data;
}

export async function getDoctor(id: number): Promise<Doctor> {
  const response = await apiClient.get<{ data: Doctor }>(`/api/v1/doctors/${id}`);
  return response.data.data;
}

export async function getAvailability(doctorId: number, date: string, tz: string): Promise<DoctorAvailability[]> {
  const response = await apiClient.get<DoctorAvailability[]>(`/api/v1/doctors/${doctorId}/availability`, {
    params: { date, tz },
  });
  return response.data;
}
```

### `src/services/appointmentService.ts`

```typescript
import apiClient from './apiClient';
import type { Appointment, AppointmentCreate } from '../types/appointment';

export async function getAppointments(): Promise<{ upcoming: Appointment[]; past: Appointment[] }> {
  const response = await apiClient.get<{ upcoming: Appointment[]; past: Appointment[] }>('/api/v1/appointments');
  return response.data;
}

export async function bookAppointment(data: AppointmentCreate): Promise<Appointment> {
  const response = await apiClient.post<{ data: Appointment }>('/api/v1/appointments', data);
  return response.data.data;
}

export async function cancelAppointment(id: number): Promise<void> {
  await apiClient.delete(`/api/v1/appointments/${id}`);
}
```

### `src/services/medicalHistoryService.ts`

```typescript
import apiClient from './apiClient';
import type { MedicalHistory, MedicalNote } from '../types/medical-history';

export async function getHistory(): Promise<MedicalHistory[]> {
  const response = await apiClient.get<{ data: MedicalHistory[] }>('/api/v1/medical-history');
  return response.data.data;
}

export async function getNoteDetail(id: number): Promise<MedicalNote> {
  const response = await apiClient.get<{ data: MedicalNote }>(`/api/v1/medical-history/notes/${id}`);
  return response.data.data;
}
```

### `src/services/prescriptionService.ts`

```typescript
import apiClient from './apiClient';
import type { Prescription, PrescriptionDetail } from '../types/prescription';

export async function getPrescriptions(): Promise<Prescription[]> {
  const response = await apiClient.get<{ data: Prescription[] }>('/api/v1/prescriptions');
  return response.data.data;
}

export async function getPrescriptionDetail(id: number): Promise<PrescriptionDetail> {
  const response = await apiClient.get<{ data: PrescriptionDetail }>(`/api/v1/prescriptions/${id}`);
  return response.data.data;
}

// Returns the full PDF URL — Linking.openURL() handles it in device browser.
// The request interceptor appends Bearer token to all requests automatically.
export function getPrescriptionPdfUrl(id: number): string {
  const base = String(
    (globalThis as { process?: { env?: Record<string, string> } })
      .process?.env?.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000',
  );
  return `${base}/api/v1/prescriptions/${id}/pdf`;
}
```

---

## Navigation Design

### `src/navigation/TabNavigator.tsx` (Create)

```typescript
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNativeStackNavigator } from '@react-navigation/native-stack';

// Import all screen components (created in apply phase)
import AppointmentsScreen from '../screens/main/appointments/AppointmentsScreen';
import DoctorListScreen from '../screens/main/appointments/DoctorListScreen';
import DoctorDetailScreen from '../screens/main/appointments/DoctorDetailScreen';
import DoctorAvailabilityScreen from '../screens/main/appointments/DoctorAvailabilityScreen';
import BookAppointmentScreen from '../screens/main/appointments/BookAppointmentScreen';
import MedicalHistoryScreen from '../screens/main/history/MedicalHistoryScreen';
import MedicalNoteDetailScreen from '../screens/main/history/MedicalNoteDetailScreen';
import PrescriptionsScreen from '../screens/main/prescriptions/PrescriptionsScreen';
import PrescriptionDetailScreen from '../screens/main/prescriptions/PrescriptionDetailScreen';
import ProfileScreen from '../screens/main/profile/ProfileScreen';

// Param list types
export type CitasStackParamList = {
  Appointments: undefined;
  DoctorList: undefined;
  DoctorDetail: { doctorId: number };
  DoctorAvailability: { doctorId: number; doctorName: string };
  BookAppointment: { doctorId: number; startTime: string };
};

export type HistorialStackParamList = {
  MedicalHistory: undefined;
  MedicalNoteDetail: { noteId: number };
};

export type RecetasStackParamList = {
  Prescriptions: undefined;
  PrescriptionDetail: { prescriptionId: number };
};

export type ProfileStackParamList = {
  Profile: undefined;
};

// Stack creators
const Tab = createBottomTabNavigator();
const CitasStackNav = createNativeStackNavigator<CitasStackParamList>();
const HistorialStackNav = createNativeStackNavigator<HistorialStackParamList>();
const RecetasStackNav = createNativeStackNavigator<RecetasStackParamList>();
const ProfileStackNav = createNativeStackNavigator<ProfileStackParamList>();

// Tab screen options
const tabScreenOptions = {
  tabBarActiveTintColor: '#1a73e8',
  tabBarInactiveTintColor: '#999',
  tabBarStyle: { borderTopWidth: 1, borderTopColor: '#eee' },
  headerShown: false,
};

// Inner stack navigators (each owns its header)
function CitasStack() {
  return (
    <CitasStackNav.Navigator screenOptions={{ presentation: 'card' }}>
      <CitasStackNav.Screen name="Appointments" component={AppointmentsScreen} options={{ title: 'Mis Citas' }} />
      <CitasStackNav.Screen name="DoctorList" component={DoctorListScreen} options={{ title: 'Doctores' }} />
      <CitasStackNav.Screen name="DoctorDetail" component={DoctorDetailScreen} options={({ route }) => ({ title: route.params.doctorId ? 'Doctor' : '' })} />
      <CitasStackNav.Screen name="DoctorAvailability" component={DoctorAvailabilityScreen} options={{ title: 'Disponibilidad' }} />
      <CitasStackNav.Screen name="BookAppointment" component={BookAppointmentScreen} options={{ title: 'Confirmar Cita' }} />
    </CitasStackNav.Navigator>
  );
}

function HistorialStack() {
  return (
    <HistorialStackNav.Navigator screenOptions={{ presentation: 'card' }}>
      <HistorialStackNav.Screen name="MedicalHistory" component={MedicalHistoryScreen} options={{ title: 'Historial Clínico' }} />
      <HistorialStackNav.Screen name="MedicalNoteDetail" component={MedicalNoteDetailScreen} options={{ title: 'Nota Clínica' }} />
    </HistorialStackNav.Navigator>
  );
}

function RecetasStack() {
  return (
    <RecetasStackNav.Navigator screenOptions={{ presentation: 'card' }}>
      <RecetasStackNav.Screen name="Prescriptions" component={PrescriptionsScreen} options={{ title: 'Recetas' }} />
      <RecetasStackNav.Screen name="PrescriptionDetail" component={PrescriptionDetailScreen} options={{ title: 'Detalle de Receta' }} />
    </RecetasStackNav.Navigator>
  );
}

function ProfileStack() {
  return (
    <ProfileStackNav.Navigator screenOptions={{ presentation: 'card' }}>
      <ProfileStackNav.Screen name="Profile" component={ProfileScreen} options={{ title: 'Mi Perfil' }} />
    </ProfileStackNav.Navigator>
  );
}

export default function TabNavigator() {
  return (
    <Tab.Navigator screenOptions={tabScreenOptions}>
      <Tab.Screen name="CitasTab" component={CitasStack} options={{ title: 'Citas', tabBarIcon: calendarIcon }} />
      <Tab.Screen name="HistorialTab" component={HistorialStack} options={{ title: 'Historial', tabBarIcon: clipboardIcon }} />
      <Tab.Screen name="RecetasTab" component={RecetasStack} options={{ title: 'Recetas', tabBarIcon: pillIcon }} />
      <Tab.Screen name="PerfilTab" component={ProfileStack} options={{ title: 'Perfil', tabBarIcon: userIcon }} />
    </Tab.Navigator>
  );
}
```

### `src/navigation/MainNavigator.tsx` (Replace existing)

Replace the entire file content to render `<TabNavigator />`. The `AuthContext` switch in `AppNavigator` is already wired — no change needed there.

### Tab navigation config

| Setting | Value |
|---------|-------|
| `unmountOnBlur` | `false` (set on each `Tab.Screen`, not on inner stacks) |
| Initial route | `CitasTab` |
| `presentation` | `card` for all inner stacks |

---

## Screen Inventory

### `src/screens/main/appointments/AppointmentsScreen.tsx`

**Purpose**: Show segmented list of upcoming and past appointments.
**Data**: `getAppointments()` → `{ upcoming, past }` on mount.
**Key components**: `SectionList` (sections: upcoming/past), `AppointmentCard`, `ErrorBanner`, `EmptyState`.
**Nav params**: None. "Buscar doctor" button pushes `DoctorList`.

### `src/screens/main/appointments/DoctorListScreen.tsx`

**Purpose**: Browse doctors with optional specialty filter.
**Data**: `getDoctors(specialtyId?)` on mount and on specialty chip tap.
**Key components**: FlatList of `DoctorCard`, specialty `Chip` row at top, `LoadingSpinner`, `ErrorBanner`.
**Nav params**: None. Card tap pushes `DoctorDetail` with `{ doctorId }`.

### `src/screens/main/appointments/DoctorDetailScreen.tsx`

**Purpose**: Show doctor profile with specialty, bio, and action button.
**Data**: `getDoctor(doctorId)` on mount.
**Key components**: Doctor header (avatar placeholder, name, specialty), bio text, "Ver disponibilidad" button.
**Nav params**: `{ doctorId: number }`. Button pushes `DoctorAvailability` with `{ doctorId, doctorName }`.

### `src/screens/main/appointments/DoctorAvailabilityScreen.tsx`

**Purpose**: Select a date then pick a time slot.
**Data**: `getAvailability(doctorId, date, timezone)` on date change. Date picker uses a `DatePicker` component (native or modal — platform-specific).
**Key components**: `DatePicker`, `SectionList` grouped by date, `SlotChip` for each slot, `EmptyState` when no slots.
**Nav params**: `{ doctorId: number; doctorName: string }`. Slot tap pushes `BookAppointment` with `{ doctorId, startTime }`.

### `src/screens/main/appointments/BookAppointmentScreen.tsx`

**Purpose**: Confirm and submit the booking.
**Data**: No fetch — uses nav params (doctor, slot). `bookAppointment()` on confirm.
**Key components**: Summary card (doctor name, date, time), confirm `Button`, `LoadingSpinner` during submit, error `Alert` for 409.
**Nav params**: `{ doctorId: number; startTime: string }`. Success → pop to root (`Appointments`) with `params = { refresh: true }`.

### `src/screens/main/history/MedicalHistoryScreen.tsx`

**Purpose**: Timeline of medical history entries.
**Data**: `getHistory()` on mount.
**Key components**: `FlatList` of `HistoryItem` (date, type icon, title, doctor name), `EmptyState` ("Aún no tienes historial clínico"), `ErrorBanner`.
**Nav params**: None. Item tap pushes `MedicalNoteDetail` with `{ noteId }`.

### `src/screens/main/history/MedicalNoteDetailScreen.tsx`

**Purpose**: Read-only display of a medical note.
**Data**: `getNoteDetail(noteId)` on mount.
**Key components**: Fields rendered in a `Card` (symptoms, diagnosis, treatment, doctor, date). No edit controls per spec.
**Nav params**: `{ noteId: number }`.

### `src/screens/main/prescriptions/PrescriptionsScreen.tsx`

**Purpose**: List all prescriptions.
**Data**: `getPrescriptions()` on mount.
**Key components**: `FlatList` of `PrescriptionRow` (code, doctor, date, status badge), `EmptyState` ("Aún no tienes recetas"), `ErrorBanner`.
**Nav params**: None. Row tap pushes `PrescriptionDetail` with `{ prescriptionId }`.

### `src/screens/main/prescriptions/PrescriptionDetailScreen.tsx`

**Purpose**: Show prescription items + PDF button.
**Data**: `getPrescriptionDetail(prescriptionId)` on mount.
**Key components**: `Card` with items list (medication, dosage, frequency), "Ver PDF" `Button` calling `Linking.openURL(getPrescriptionPdfUrl(id))`.
**Nav params**: `{ prescriptionId: number }`.

### `src/screens/main/profile/ProfileScreen.tsx`

**Purpose**: Display patient profile and logout.
**Data**: `user` from `AuthContext` (already loaded), no API call needed.
**Key components**: Profile card (name, email, registration date from user), "Cerrar sesión" `Button` calling `logout()` then navigating to `AuthNavigator`.
**Nav params**: None.

---

## Reusable UI Components

All components live in `src/components/`. Style follows the pattern from `LoginScreen.tsx` (borderRadius: 10, shadowOpacity: 0.1, primary color `#1a73e8`).

| Component | File | Props | Notes |
|-----------|------|-------|-------|
| `Button` | `Button.tsx` | `title`, `onPress`, `loading?`, `variant?` ('primary' \| 'secondary' \| 'danger') | Disabled state during loading |
| `Card` | `Card.tsx` | `children`, `style?` | White bg, borderRadius 12, shadow |
| `LoadingSpinner` | `LoadingSpinner.tsx` | `size?`, `color?` | Wraps `ActivityIndicator` |
| `EmptyState` | `EmptyState.tsx` | `message`, `icon?` | Centered, muted text |
| `ErrorBanner` | `ErrorBanner.tsx` | `message`, `onRetry` | Red background, retry button |
| `SlotChip` | `SlotChip.tsx` | `time: string`, `selected: boolean`, `onPress` | Tappable time chip for availability |
| `AppointmentCard` | `AppointmentCard.tsx` | `appointment: Appointment`, `onCancel?` | Shows state badge, cancel button (24h guard) |
| `DoctorCard` | `DoctorCard.tsx` | `doctor: Doctor`, `onPress` | Name, specialty, bio excerpt |
| `StatusBadge` | `StatusBadge.tsx` | `status: string` | Color-coded pill (pending=amber, confirmed=green, etc.) |

---

## File Inventory

| File | Action | Description |
|------|--------|-------------|
| `src/navigation/TabNavigator.tsx` | Create | Bottom tab bar with 4 tab screens |
| `src/navigation/MainNavigator.tsx` | Modify | Replace placeholder with `TabNavigator` |
| `src/services/apiClient.ts` | Modify | Fix refresh path to `/api/v1/auth/refresh` |
| `src/services/authService.ts` | Modify | Update all 4 auth paths to `/api/v1/` |
| `src/services/doctorService.ts` | Create | Doctor list, detail, availability |
| `src/services/appointmentService.ts` | Create | Appointments list, book, cancel |
| `src/services/medicalHistoryService.ts` | Create | History timeline, note detail |
| `src/services/prescriptionService.ts` | Create | Prescription list, detail, PDF URL |
| `src/types/doctor.ts` | Create | Doctor, Specialty, DoctorAvailability |
| `src/types/appointment.ts` | Create | Appointment, AppointmentCreate, AppointmentState |
| `src/types/medical-history.ts` | Create | MedicalHistory, MedicalNote |
| `src/types/prescription.ts` | Create | Prescription, PrescriptionDetail, PrescriptionItem |
| `src/components/Button.tsx` | Create | Reusable button with loading state |
| `src/components/Card.tsx` | Create | Reusable card container |
| `src/components/LoadingSpinner.tsx` | Create | ActivityIndicator wrapper |
| `src/components/EmptyState.tsx` | Create | Empty list placeholder |
| `src/components/ErrorBanner.tsx` | Create | Error with retry action |
| `src/components/SlotChip.tsx` | Create | Time slot chip for availability |
| `src/components/AppointmentCard.tsx` | Create | Appointment list item |
| `src/components/DoctorCard.tsx` | Create | Doctor list item |
| `src/components/StatusBadge.tsx` | Create | Status pill badge |
| `src/screens/main/appointments/AppointmentsScreen.tsx` | Create | Appointments tab root |
| `src/screens/main/appointments/DoctorListScreen.tsx` | Create | Doctor list with filter |
| `src/screens/main/appointments/DoctorDetailScreen.tsx` | Create | Doctor profile |
| `src/screens/main/appointments/DoctorAvailabilityScreen.tsx` | Create | Date + slot picker |
| `src/screens/main/appointments/BookAppointmentScreen.tsx` | Create | Booking confirmation |
| `src/screens/main/history/MedicalHistoryScreen.tsx` | Create | History timeline |
| `src/screens/main/history/MedicalNoteDetailScreen.tsx` | Create | Note read-only view |
| `src/screens/main/prescriptions/PrescriptionsScreen.tsx` | Create | Prescription list |
| `src/screens/main/prescriptions/PrescriptionDetailScreen.tsx` | Create | Prescription detail + PDF |
| `src/screens/main/profile/ProfileScreen.tsx` | Create | Profile + logout |
| `package.json` | Modify | Add `@react-navigation/bottom-tabs` |

---

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| **Unit** | Service functions (mock `apiClient`) | `vitest` + `axios-mock-adapter`. Test each method: success response parsing, error normalization, 409 conflict handling for booking |
| **Unit** | Type definitions | TypeScript compile check via `tsc --noEmit` |
| **Integration** | Screen components (mock services) | React Native Testing Library. Mount screen, simulate user action, assert navigation call or UI state |
| **Key scenarios** | Booking conflict (409) | Service returns 409 → screen shows "Este horario ya no está disponible" |
| **Key scenarios** | Cancel window (24h guard) | Appointment with `start_time` < 24h → cancel button hidden |
| **Key scenarios** | Empty states | API returns `[]` → `EmptyState` renders with correct message |
| **Key scenarios** | Auth redirect | No token on tab screen → navigation to `AuthNavigator` (mock `isAuthenticated: false`) |
| **E2E** | Full booking flow | Expo Go + Detox or Appium: login → browse doctor → select slot → confirm → see in appointments |

---

## Migration / Rollout

**No migration required.** This is a net-new feature surface. No existing data model changes. The API v1 path migration is a find-replace in two service files — no data migration.

**Rollout**: Phased per the proposal:
1. API path fix (authService + apiClient) — isolated, testable in minutes
2. Tab scaffold + types + services — architectural foundation
3. Citas stack (5 screens)
4. Historial stack (2 screens)
5. Recetas stack (2 screens)
6. Profile tab

Each phase is a separate, reviewable commit. The rollback plan in the proposal covers reverts at each phase boundary.

---

## Open Questions

- [ ] **DatePicker**: Use a native modal date picker (`@react-native-community/datetimepicker`) or a custom bottom-sheet date selector? Native is simpler; custom gives more control over UX. Decision needed before apply.
- [ ] **Doctor avatar**: The API returns no avatar URL. Use a `View` with initials placeholder (first letter of name) or a generic icon. No external image dependency in v1.
- [ ] **Cancel confirmation**: Alert dialog or dedicated confirmation screen? Spec says "patient taps Cancel and confirms" — an `Alert.alert` with two buttons is sufficient for v1.