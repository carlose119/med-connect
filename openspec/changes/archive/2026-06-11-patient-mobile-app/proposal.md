# Proposal: Patient Mobile App

**Change**: patient-mobile-app
**Date**: 2026-06-11
**Mode**: openspec
**Author**: sdd-propose sub-agent

---

## Intent

The mobile app currently has authentication only. This change builds the full patient-facing feature surface: browse doctors, book appointments, view medical history and notes, view prescriptions with PDF access, and manage the patient profile. It completes the mobile app gap identified in the PRD (RF-2.2, RF-2.3, RF-3.1, RF-3.2, RF-3.4, RF-4.3) and aligns the mobile stack to the `/api/v1/` routes already provisioned in the Laravel backend.

---

## Scope

### In Scope
- **10 screens** across 4 bottom tabs: Citas, Historial, Recetas, Perfil
- **Navigation restructure**: Replace placeholder `MainNavigator` with `@react-navigation/bottom-tabs` + nested native stacks per tab
- **5 new service files**: `doctorService`, `appointmentService`, `medicalHistoryService`, `prescriptionService`
- **4 new type files**: `doctor.ts`, `appointment.ts`, `medical-history.ts`, `prescription.ts`
- **API path migration**: Update `authService.ts` and `apiClient.ts` from `/api/` to `/api/v1/`
- Bottom tab bar with per-tab navigation state preservation

### Out of Scope
- Backend changes (routes/api.php already has v1 paths)
- Doctor panel or admin panel screens
- Push notifications or background sync
- Appointment cancellation flow (patient can only view, not cancel from mobile in v1)
- In-app PDF rendering (PDF opens in device browser)

---

## Capabilities

> Contract with sdd-spec: the backend is already spec-complete for all touched domains. The mobile app is a new consumer. No new capabilities are introduced; no existing spec behavior changes.

### New Capabilities
None — backend capabilities already exist in specs. Mobile implements patient-facing read/write consumers.

### Modified Capabilities
None — no backend behavior changes.

---

## Approach

**Navigation**: Bottom tab bar with 4 tabs. Each tab owns a native stack. Doctor browsing (list → detail → availability → book) lives as a sub-stack under Citas. Tab switching preserves per-tab navigation state.

**Booking UX**: Fetch available slots from `GET /api/v1/doctors/{id}/availability`. Render as time-chips grouped by date header (SectionList). User picks date first, then time slot, then confirms. No native date picker.

**Prescription PDF**: Open PDF URL via `Linking.openURL()` in the device browser. No file system access, no extra packages.

**Phased delivery**: (1) API path fix → (2) bottom tabs scaffold → (3) Citas stack (doctors + appointments) → (4) Historial stack → (5) Recetas stack → (6) Profile tab.

---

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `react_app_mobile/src/services/apiClient.ts` | Modified | Fix refresh endpoint path to `/api/v1/` |
| `react_app_mobile/src/services/authService.ts` | Modified | Update all auth paths to `/api/v1/` |
| `react_app_mobile/src/navigation/MainNavigator.tsx` | Modified | Replace placeholder with real navigation |
| `react_app_mobile/src/navigation/TabNavigator.tsx` | New | Bottom tab bar with 4 tabs |
| `react_app_mobile/src/services/doctorService.ts` | New | Doctor list, specialties, availability |
| `react_app_mobile/src/services/appointmentService.ts` | New | List, create, view appointments |
| `react_app_mobile/src/services/medicalHistoryService.ts` | New | Medical history and notes |
| `react_app_mobile/src/services/prescriptionService.ts` | New | Prescription list and PDF |
| `react_app_mobile/src/types/` | New | 4 type files for domain models |
| `react_app_mobile/src/screens/main/` | New | 10 patient-facing screens |
| `package.json` | Modified | Add `@react-navigation/bottom-tabs` |

---

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| API v1 path migration breaks existing auth | Medium | Apply first as a standalone task before screens |
| Availability endpoint shape differs from exploration | Low | Design phase inspects the controller/resource; graceful fallback to empty state |
| PDF endpoint requires auth blob handling | Low | Design phase confirms response type; axios `responseType: 'blob'` if needed |
| Tab navigation state reset on switch | Low | Use `unmountOnBlur: false` and proper navigator container config |

---

## Rollback Plan

- **Before Phase 1**: Commit current state of `apiClient.ts`, `authService.ts`, `MainNavigator.tsx`
- **After Phase 1**: Verify auth calls succeed with v1 paths before proceeding to screens
- **Per-phase commit**: Each phase is a separate, reviewable commit with a clear rollback point
- **If tab navigation breaks**: Revert `MainNavigator.tsx` to placeholder, screens remain unlinked but code is safe
- **If PDF fails**: Fall back to a download-to-cache + share approach (adds `expo-file-system` + `expo-sharing`)

---

## Dependencies

- Laravel backend routes at `/api/v1/` are already provisioned — no backend work needed
- `@react-navigation/bottom-tabs` must be added to `package.json` — run `npm install` before apply
- Auth token interceptor already in `apiClient.ts` — no new auth plumbing needed

---

## Success Criteria

- [ ] Auth calls (login, register, refresh) work with `/api/v1/` paths
- [ ] Patient can browse doctors with specialty filter
- [ ] Patient can view doctor availability and book an appointment
- [ ] Patient can view upcoming and past appointments (segmented)
- [ ] Patient can view medical history timeline and note details
- [ ] Patient can view prescriptions and open PDF in browser
- [ ] Patient can view and manage their profile
- [ ] Bottom tab bar switches tabs without losing nested stack state
- [ ] All screens handle loading, error, and empty states
- [ ] No new backend endpoints required