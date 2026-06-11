# Tasks: Mobile Authentication

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~1,200–1,400 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 → PR 2 → PR 3 |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Foundation: types, storage, apiClient | PR 1 | Base: main; includes all types + token storage + Axios singleton with refresh interceptors |
| 2 | Core Auth: services + AuthContext | PR 2 | Base: PR 1 branch; authService, biometricService, AuthContext + all auth methods |
| 3 | UI Layer: screens + navigation + App.tsx | PR 3 | Base: PR 2 branch; LoginScreen, RegisterScreen, navigators, provider wiring |

**Ask the user to choose a chain strategy before launching sdd-apply:**
- **Stacked PRs to main** — PRs merge to main in order (fast, simple)
- **Feature Branch Chain** — feature branch accumulates final integration; only tracker merges to main (better rollback control)

---

## Phase 1: Foundation (PR 1)

- [x] 1.1 Create `src/types/auth.ts` with interfaces: `LoginForm`, `RegisterForm`, `User`, `AuthState`, `ApiError`
- [x] 1.2 Create `src/services/tokenStorage.ts` with AsyncStorage CRUD for `auth_token`, `auth_refresh_token`, `auth_user`
- [x] 1.3 Create `src/services/apiClient.ts` — Axios singleton with token injection, error normalization (422→VALIDATION_ERROR, 401→UNAUTHORIZED, network→NETWORK_ERROR), and 401→refresh→retry interceptor flow
- [x] 1.4 Add npm dependencies to `package.json`: `@react-native-async-storage/async-storage axios @hookform/resolvers expo-local-authentication expo-crypto` (react-hook-form, zod, navigation already listed in design)

## Phase 2: Core Auth (PR 2)

- [x] 2.1 Create `src/services/authService.ts` with `login()`, `register()`, `logout()`, `me()`, `refreshToken()` methods
- [x] 2.2 Create `src/services/biometricService.ts` with `isBiometricAvailable()`, `authenticate()`, `enableBiometric()`, `disableBiometric()`, `isBiometricEnabled()` — wraps `expo-local-authentication`
- [x] 2.3 Create `src/contexts/AuthContext.tsx` with `AuthProvider` — initializes from AsyncStorage, calls `/me` to validate token on launch, exposes `login()`, `register()`, `logout()`, `loginWithBiometric()`, `user`, `token`, `isAuthenticated`, `isLoading`, `biometricAvailable`; handles refresh-fail → logout

## Phase 3: UI Layer (PR 3)

- [x] 3.1 Create `src/screens/auth/LoginScreen.tsx` — react-hook-form + Zod schema (email format, password ≥8 chars, `mode: 'onBlur'`), inline errors ("Ingresa un email válido", "La contraseña debe tener al menos 8 caracteres"), remember-me toggle, biometric button (conditional on `biometricAvailable`), submit button disabled during request
- [x] 3.2 Create `src/screens/auth/RegisterScreen.tsx` — 7-field form with Zod schema (all required fields, password_confirmation match, "Las contraseñas no coinciden", "Este campo es obligatorio"), submit disabled during request
- [x] 3.3 Create `src/navigation/AuthNavigator.tsx` — stack: LoginScreen (initial) ↔ RegisterScreen with links
- [x] 3.4 Create `src/navigation/MainNavigator.tsx` — stack with placeholder HomeScreen
- [x] 3.5 Create `src/navigation/AppNavigator.tsx` — conditional: `isLoading` → blank screen; `isAuthenticated === false` → AuthNavigator; `isAuthenticated === true` → MainNavigator
- [x] 3.6 Update `src/App.tsx` — wrap with `AuthProvider`, render `AppNavigator`

## Phase 4: Integration / Verification

- [ ] 4.1 Verify: valid login → token stored in AsyncStorage, user cached, redirected to HomeScreen
- [ ] 4.2 Verify: invalid email → "Ingresa un email válido" shown on blur; short password → "La contraseña debe tener al menos 8 caracteres" on blur
- [ ] 4.3 Verify: register password mismatch → "Las contraseñas no coinciden" on blur; missing field → "Este campo es obligatorio"
- [ ] 4.4 Verify: API 422 → field-level error shown on form; 401 → logout triggered, redirected to Login
- [ ] 4.5 Verify: network error → "Error de conexión. Verifica tu internet." displayed
- [ ] 4.6 Verify: app launch with valid token → `/me` called, user restored, HomeScreen shown
- [ ] 4.7 Verify: app launch with expired token → refresh attempted, fail → tokens cleared, Login shown
- [ ] 4.8 Verify: biometric button hidden when device unsupported; shown when enrolled biometrics available
- [ ] 4.9 Run `npx expo prebuild && npx expo run:android` to verify compilation