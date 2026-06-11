---
change: patient-mobile-app
status: spec
created: 2026-06-11
---

# Patient Mobile — Navigation Structure

## Bottom Tab Structure

Four tabs fixed at the bottom. Tab switching MUST preserve each tab's nested stack state (`unmountOnBlur: false`).

| Tab | Label | Icon | Root Screen |
|-----|-------|------|-------------|
| Citas | Citas | calendar | AppointmentsScreen |
| Historial | Historial | clipboard | MedicalHistoryScreen |
| Recetas | Recetas | pill | PrescriptionsScreen |
| Perfil | Perfil | user | ProfileScreen |

---

## Stacks Per Tab

### Tab: Citas (CitasStack — `CitasStack`)

```
AppointmentsScreen          ← root (upcoming + past segmented list)
  └── DoctorListScreen       ← "Buscar doctor" CTA
        └── DoctorDetailScreen
              └── DoctorAvailabilityScreen
                    └── BookAppointmentScreen
```

Route names: `Appointments`, `DoctorList`, `DoctorDetail`, `DoctorAvailability`, `BookAppointment`

Navigation flow:
- AppointmentsScreen has a "Buscar doctor" button → pushes `DoctorList`
- DoctorList item → pushes `DoctorDetail`
- DoctorDetail "Ver disponibilidad" → pushes `DoctorAvailability`
- DoctorAvailability slot selection → pushes `BookAppointment`
- BookAppointment success → pop to root (AppointmentsScreen) with refresh

---

### Tab: Historial (HistorialStack — `HistorialStack`)

```
MedicalHistoryScreen         ← root (timeline)
  └── MedicalNoteDetailScreen
```

Route names: `MedicalHistory`, `MedicalNoteDetail`

Navigation flow:
- MedicalHistoryScreen item tap → pushes `MedicalNoteDetail`
- Back button → pops to timeline

---

### Tab: Recetas (RecetasStack — `RecetasStack`)

```
PrescriptionsScreen          ← root (list)
  └── PrescriptionDetailScreen
```

Route names: `Prescriptions`, `PrescriptionDetail`

Navigation flow:
- PrescriptionsScreen item tap → pushes `PrescriptionDetail`
- Back button → pops to list

---

### Tab: Perfil (ProfileStack — `ProfileStack`)

```
ProfileScreen                ← root only
```

Route name: `Profile`

No nested screens under Perfil.

---

## Navigator Hierarchy

```
AppNavigator (AuthNavigator | TabNavigator)
  ├── AuthNavigator (login/Register flow)
  └── TabNavigator (bottom tabs)
        ├── CitasStack     (nested native-stack)
        ├── HistorialStack (nested native-stack)
        ├── RecetasStack   (nested native-stack)
        └── ProfileStack   (nested native-stack)
```

`MainNavigator.tsx` is replaced entirely by `TabNavigator`. The root `AppNavigator` switches between `AuthNavigator` (unauthenticated) and `TabNavigator` (authenticated) based on `AuthContext`.

---

## Key Navigation Config

| Setting | Value | Reason |
|---------|-------|--------|
| `unmountOnBlur` | `false` on every tab | Preserve stack state when switching tabs |
| `headerShown` | `true` per stack screen | Native back button + title |
| Initial route | `CitasStack` | Appointments are the primary patient action |
| Screen options | `presentation: 'card'` | Standard iOS/Android card presentation |