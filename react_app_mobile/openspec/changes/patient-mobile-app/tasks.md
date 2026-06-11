# patient-mobile-app — Tasks

## Phase 1: Foundation (PR 1 — COMPLETED)

- [x] 1.1 apiClient.ts with auth interceptors
- [x] 1.2 Token storage (biometric + secure storage)
- [x] 1.3 Auth context
- [x] 1.4 Auth types
- [x] 1.5 Login screen
- [x] 1.6 Register screen
- [x] 1.7 Auth navigator

## Phase 2: Types (PR 1 — COMPLETED)

- [x] 2.1 doctor.ts
- [x] 2.2 appointment.ts
- [x] 2.3 medical-history.ts
- [x] 2.4 prescription.ts

## Phase 3: Services (PR 2 — IN PROGRESS)

- [x] 3.1 doctorService.ts
- [x] 3.2 appointmentService.ts
- [x] 3.3 medicalHistoryService.ts
- [x] 3.4 prescriptionService.ts

## Phase 4: UI Components (PR 2 — IN PROGRESS)

- [x] 4.1 Button.tsx
- [x] 4.2 Card.tsx
- [x] 4.3 LoadingSpinner.tsx
- [x] 4.4 EmptyState.tsx
- [x] 4.5 ErrorBanner.tsx
- [x] 4.6 SlotChip.tsx
- [x] 4.7 AppointmentCard.tsx
- [x] 4.8 DoctorCard.tsx
- [x] 4.9 StatusBadge.tsx

## Phase 5: Navigation (PR 3 — PENDING)

- [ ] 5.1 TabNavigator (Home, Appointments, History, Prescriptions, Profile)
- [ ] 5.2 MainNavigator (Auth + Tab)
- [ ] 5.3 HomeScreen
- [ ] 5.4 Navigation types

## Phase 6: Screens (PR 3/4 — PENDING)

- [ ] 6.1 DoctorListScreen
- [ ] 6.2 DoctorDetailScreen
- [ ] 6.3 AppointmentBookingScreen
- [ ] 6.4 AppointmentListScreen
- [ ] 6.5 AppointmentDetailScreen
- [ ] 6.6 MedicalHistoryListScreen
- [ ] 6.7 MedicalNoteDetailScreen
- [ ] 6.8 PrescriptionListScreen
- [ ] 6.9 PrescriptionDetailScreen
- [ ] 6.10 ProfileScreen

## Phase 7: Integrations (PR 5 — PENDING)

- [ ] 7.1 Deep link handling
- [ ] 7.2 Push notifications
- [ ] 7.3 Biometric auth screen

## Review Workload Forecast

- **Estimated total lines**: ~1800 (13 services + 9 components + 10 screens + navigators)
- **400-line budget risk**: High
- **Chained PRs recommended**: Yes
- **Decision needed before apply**: Yes
- **Resolved delivery strategy**: stacked-to-main (5 PRs)

### Chain Strategy

| PR | Scope | Target |
|----|-------|--------|
| 1 | Foundation (API + types) | main |
| 2 | Services + UI Components | main |
| 3 | Navigation + HomeScreen | main |
| 4 | Feature Screens | main |
| 5 | Integrations | main |

### PR Boundaries

- PR 1: ~800 lines (API client, auth, screens, types)
- PR 2: ~400 lines (4 services + 9 components)
- PR 3: ~400 lines (navigators + home screen)
- PR 4: ~400 lines (doctor, appointment, history, prescription screens)
- PR 5: ~200 lines (deep links, push, biometric)