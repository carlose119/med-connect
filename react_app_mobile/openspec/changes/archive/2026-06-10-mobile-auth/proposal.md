# Proposal: Mobile Authentication Flow

## Intent

The `react_app_mobile` Expo app is a blank shell with no auth. Patients need to self-register and log in to access appointment booking, clinical records, and prescriptions. This change builds the complete auth layer: login, registration, token management, API client, and navigation guards.

## Scope

### In Scope
- Login screen (email + password + remember me + validation + error handling)
- Register screen (name, email, password, password_confirmation, identification_number, phone, optional birth_date/gender)
- Auth state management via `TokenContext` with AsyncStorage persistence
- Axios API client with auth interceptor (token injection, 401 handling)
- Navigation flow: `AuthStack` (Login, Register) → `MainStack` (Home placeholder)
- Error normalization: 422 validation errors, 401 unauthorized, network errors

### Out of Scope
- Password reset flow
- Biometric / device passcode auth
- Profile completion after registration
- Logout on token expiry UX refinement
- Bottom tab navigation screens beyond the placeholder Home

## Capabilities

### New Capabilities
- `mobile-auth`: All authentication flows for the mobile app — login, registration, token storage, auth guards, API client with interceptors.

### Modified Capabilities
- None — the `users-roles` spec defines the backend role model; this change only touches the mobile client.

## Approach

**Approach B from exploration** — React Hook Form + Zod + Axios + AuthContext.

Install dependencies first, then build the layers bottom-up:
1. `tokenStorage.ts` — AsyncStorage CRUD for Sanctum token
2. `apiClient.ts` — Axios singleton with request interceptor (inject token) + response interceptor (normalize errors, detect 401)
3. `authService.ts` — typed API calls for login/register/logout/me
4. `AuthContext.tsx` — React context with `user`, `token`, `isAuthenticated`, `isLoading`, plus `login()`, `register()`, `logout()` methods
5. `LoginScreen.tsx` — react-hook-form + Zod schema, remember-me toggle, field-level + form-level error display
6. `RegisterScreen.tsx` — same pattern, 7 fields (5 required + 2 optional), inline validation
7. `AppNavigator.tsx` — `NavigationContainer` with conditional stack rendering based on `isAuthenticated`
8. `App.tsx` — wrap with `AuthProvider`, render `AppNavigator`

Error handling strategy: `apiClient` catches all responses and normalizes to `{ code, message, details }`. Screens read `details` to map field-level errors back to form fields.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `package.json` | Modified | New deps: navigation, async-storage, axios, react-hook-form, zod, @hookform/resolvers |
| `App.tsx` | Modified | Wrap with AuthProvider, render AppNavigator |
| `src/services/tokenStorage.ts` | New | AsyncStorage get/set/remove for auth token |
| `src/services/apiClient.ts` | New | Axios instance with auth interceptor + error normalization |
| `src/services/authService.ts` | New | login(), register(), logout(), me() API calls |
| `src/contexts/AuthContext.tsx` | New | Auth state + methods provider |
| `src/screens/auth/LoginScreen.tsx` | New | Login form with react-hook-form + Zod |
| `src/screens/auth/RegisterScreen.tsx` | New | Register form with react-hook-form + Zod |
| `src/navigation/AppNavigator.tsx` | New | Conditional auth stack navigator |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Token race condition on logout (async storage clear before API call) | Low | `isLoading` guard prevents navigation until storage confirmed cleared |
| 401 on API calls after token expires server-side | Medium | Response interceptor detects 401, calls `logout()` to clear state |
| Form validation UX gaps (no debounce, late blur) | Low | Use react-hook-form `mode: 'onBlur'` — validate on field leave |
| Network errors silently swallowed | Medium | apiClient normalizes all errors; screens show toast/alert for non-validation errors |

## Rollback Plan

1. Revert `package.json` to remove the 7 new dependencies
2. Restore `App.tsx` to the original boilerplate
3. Delete the 8 new source files/directories under `src/`
4. Run `npx expo prebuild --clean` if native module issues arise from navigation

## Dependencies

- Expo SDK 56 (already in project)
- `@react-navigation/native`, `@react-navigation/native-stack` (navigation)
- `@react-native-async-storage/async-storage` (token persistence)
- `axios` (HTTP client)
- `react-hook-form`, `zod`, `@hookform/resolvers` (form validation)
- Backend must expose `/api/auth/*` endpoints (already confirmed in exploration)

## Success Criteria

- [ ] User can register with required fields and receive a token stored in AsyncStorage
- [ ] User can log in with email/password and remain authenticated across app restarts (remember me)
- [ ] Authenticated users reach the MainStack; unauthenticated users are redirected to Login
- [ ] 422 responses surface field-level validation errors inline on the form
- [ ] 401 responses trigger logout and redirect to Login
- [ ] Network errors show a user-visible error message
- [ ] All form submissions are disabled while the request is in-flight