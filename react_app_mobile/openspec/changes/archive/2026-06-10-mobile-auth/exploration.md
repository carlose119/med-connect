# Exploration: Mobile Authentication Flow

**Change**: `mobile-auth`  
**Date**: 2026-06-10  
**Mode**: openspec

---

## Current State

The `react_app_mobile` Expo project is a blank shell:

| File | Status |
|------|--------|
| `App.tsx` | Basic boilerplate with `View` + `Text` |
| `package.json` | Expo 56 + React Native 0.85 + React 19 + TypeScript 6 |
| Navigation | **None installed** |
| Auth storage | **None** |
| Form validation | **None** |
| API client | **None** |

No `src/` structure, no context providers, no existing auth screens.

---

## API Surface (from backend)

### Endpoints

| Method | Path | Auth | Response |
|--------|------|------|----------|
| POST | `/api/auth/login` | No | `200 {data: {user, token}}` or `401` |
| POST | `/api/auth/register` | No | `201 {data: {user, token}}` or `422` |
| POST | `/api/auth/logout` | Yes | `204` or `401` |
| GET | `/api/auth/me` | Yes | `200 {data: user}` or `401` |

### UserResource Shape

```typescript
interface UserResource {
  id: number;
  name: string;
  email: string;
  role: 'patient' | 'admin' | 'doctor' | 'secretary';
}
```

### Register Request Fields

```
name: string (required)
email: string (required)
password: string (required, min 8, confirmed)
identification_number: string (required, unique:patients)
phone: string (required)
birth_date: string (optional, date)
gender: string (optional, in: male|female|other)
```

### Error Envelope

```typescript
interface ApiError {
  error: {
    code: string;      // e.g. "VALIDATION_ERROR"
    message: string;
    details?: Record<string, string[]>;  // field-level errors
  }
}
```

---

## Affected Areas

| File/Directory | Why affected |
|----------------|--------------|
| `App.tsx` | Root navigation setup, AuthProvider wrapping |
| `package.json` | New dependencies: navigation, async-storage, form validation |
| New: `src/screens/auth/LoginScreen.tsx` | Login UI + form validation |
| New: `src/screens/auth/RegisterScreen.tsx` | Self-registration UI + form validation |
| New: `src/contexts/AuthContext.tsx` | Auth state management (user, token, loading) |
| New: `src/services/apiClient.ts` | Axios instance with auth interceptors |
| New: `src/services/authService.ts` | API calls (login, register, logout, me) |
| New: `src/services/tokenStorage.ts` | AsyncStorage read/write for Sanctum token |
| New: `src/navigation/AppNavigator.tsx` | Stack navigator: AuthStack → MainStack |

---

## Approaches

### Approach A: Minimal — Manual state, basic forms

**Stack**: `@react-navigation/native` + `@react-navigation/native-stack` + `@react-native-async-storage/async-storage`

- No form library — manual `useState` + inline validation
- No context — pass callbacks as props
- Manual token header injection per request

| Pros | Cons |
|------|------|
| Zero extra dependencies | Verbose form code, repeated validation logic |
| Full control over UX | Easy to miss edge cases (debounce, blur) |
| Simple for reviewers | Harder to extend with complex forms later |
| **Effort: Low** | |

### Approach B: Recommended — Context + Zod + Axios

**Stack**: 
- Navigation: `@react-navigation/native` + `@react-navigation/native-stack`
- Token storage: `@react-native-async-storage/async-storage`
- Form validation: `react-hook-form` + `zod`
- HTTP client: `axios` (interceptors for token injection + error normalization)

- `AuthContext` with `user`, `token`, `isAuthenticated`, `isLoading`
- `tokenStorage.ts` for AsyncStorage CRUD
- `apiClient.ts` singleton with request/response interceptors
- `authService.ts` for API calls
- Screen components with `react-hook-form` + Zod schemas

| Pros | Cons |
|------|------|
| Clean separation of concerns | More dependencies to install |
| Type-safe forms with Zod | Requires learning react-hook-form patterns |
| Centralized auth state | Slight learning curve for team |
| Interceptors handle token injection everywhere | |
| **Effort: Medium** | |

### Approach C: Full Expo Router

**Stack**: `expo-router` (file-based routing)

- `/app/(auth)/login.tsx`
- `/app/(auth)/register.tsx`
- `/app/(tabs)/...`
- Middleware for auth guards

| Pros | Cons |
|------|------|
| Native Expo routing, better DX | Bigger refactor of App.tsx structure |
| File-based = less boilerplate | Overkill if only 2 auth screens now |
| SEO-ready, deep links | Steeper learning curve for team |
| **Effort: High** | |

---

## Recommendation

**Approach B** — React Hook Form + Zod + Axios + AuthContext.

Rationale:
1. The auth surface needs to be reliable and extensible — patient self-registration has 7 fields, manual validation gets unwieldy.
2. `AuthContext` + interceptors means every future API call automatically gets the token — no ad-hoc header passing.
3. The added dependencies (`react-hook-form`, `zod`, `axios`) are well-understood, stable, and small bundle-impact.
4. Keeps the door open to upgrade to Expo Router later without rewriting the auth layer.

**Do NOT start with Approach C** — Expo Router is great but introduces a structural paradigm shift. Get auth working first, then evaluate routing.

---

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| AsyncStorage token gets out of sync with API (logout race) | Low | Use `isLoading` guard on app mount; clear storage on logout |
| 401 on API calls when token expired | Medium | AuthContext should detect 401 and call logout() |
| Form validation UX gaps (no debounce, no blur validation) | Low | Use react-hook-form's `mode: 'onBlur'` |
| Network errors not surfaced to user | Medium | apiClient should normalize errors into ApiError shape |
| User registers but doesn't complete patient profile | Low | Post-auth redirect to profile completion screen (out of scope for this change, note it) |

---

## Dependencies to Install

```
expo install @react-navigation/native @react-navigation/native-stack react-native-screens react-native-safe-area-context
npm install @react-native-async-storage/async-storage axios react-hook-form zod @hookform/resolvers
```

---

## Navigation Flow

```
App.tsx
└── AuthProvider (wraps entire app)
    └── AppNavigator (Root Navigator)
        ├── AuthStack (unauthenticated)
        │   ├── LoginScreen
        │   └── RegisterScreen
        └── MainStack (authenticated)
            └── HomeScreen (placeholder — future change)
```

---

## Ready for Proposal

**Yes** — the exploration is complete. The API contracts are understood, the affected areas are identified, and Approach B is recommended with clear rationale.

**Next**: orchestrator should launch `sdd-propose` to create `openspec/changes/mobile-auth/proposal.md`.

**Key decisions to bake into proposal**:
1. Approach B: React Hook Form + Zod + Axios + AuthContext
2. Token storage: `@react-native-async-storage/async-storage`
3. Navigation: `@react-navigation/native-stack`
4. Error handling: centralized in apiClient interceptors
5. Out of scope: profile completion, password reset, biometric auth