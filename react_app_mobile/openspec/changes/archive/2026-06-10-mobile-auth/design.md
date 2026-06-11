# Design: Mobile Authentication Flow

## Technical Approach

Build the complete auth layer bottom-up: storage → api client → services → context → screens → navigation. React Hook Form + Zod for validation, Axios for HTTP, AsyncStorage for token persistence, React Navigation for routing.

## Architecture Decisions

### Decision: React Hook Form + Zod over Formik + Yup

**Choice**: Use `react-hook-form` with `mode: 'onBlur'` + Zod schemas
**Alternatives considered**: Formik + Yup (more boilerplate, larger bundle)
**Rationale**: RHF has better performance (no uncontrolled→controlled re-renders), Zod schemas double as runtime validators and TypeScript inference sources.

### Decision: Axios singleton with interceptors over fetch

**Choice**: Axios instance with request/response interceptors
**Alternatives considered**: Native fetch with custom wrappers
**Rationale**: Axios interceptors handle token injection and error normalization in one place — less boilerplate than fetch middleware, better error transformation.

### Decision: TokenContext with useReducer over Redux/Zustand

**Choice**: Simple React Context + useReducer for auth state
**Alternatives considered**: Redux Toolkit, Zustand
**Rationale**: Auth state is simple (user + token + loading flag) — no need for a full state manager. Context is built-in, no extra deps.

### Decision: AsyncStorage for token persistence

**Choice**: `@react-native-async-storage/async-storage` for token storage
**Alternatives considered**: expo-secure-store (overkill for non-sensitive tokens), MMKV (native module complexity)
**Rationale**: AsyncStorage is the standard for Expo token persistence. Sanctum tokens are short-lived and non-sensitive (tied to session). Stores: `auth_token`, `auth_refresh_token`, `auth_user`.

### Decision: User cache to reduce /me calls

**Choice**: Store user object in AsyncStorage, load immediately on init, then validate token with `/me`
**Rationale**: Reduces UI flash on launch. Token validation still happens in background — user sees content immediately while auth resolves.

### Decision: Refresh token before expiry

**Choice**: Axios interceptor handles 401 → refresh → retry flow automatically
**Rationale**: Transparent to screens — no UI interruption for valid refresh. If refresh fails, logout is triggered. Pre-expiry check (5 min before `exp`) triggers proactive refresh.

### Decision: expo-local-authentication for biometric auth

**Choice**: `expo-local-authentication` for fingerprint/FaceID with `expo-crypto` key storage
**Alternatives considered**: react-native-biometrics (native module), Keychain-only (no biometric prompt)
**Rationale**: expo-local-authentication is Expo-compatible, works with Expo Go, and integrates with AsyncStorage for storing a biometric-gated token key.

## File Structure

```
src/
├── services/
│   ├── tokenStorage.ts      # AsyncStorage CRUD for auth_token, auth_refresh_token, auth_user
│   ├── apiClient.ts         # Axios singleton with interceptors + refresh/retry logic
│   ├── authService.ts       # login(), register(), logout(), me(), refresh()
│   └── biometricService.ts  # isAvailable(), authenticate(), storeBiometricKey(), clearBiometricKey()
├── contexts/
│   └── AuthContext.tsx      # Auth state + login/register/logout/biometric methods
├── screens/
│   └── auth/
│       ├── LoginScreen.tsx  # Email/password form + biometric button (conditional)
│       └── RegisterScreen.tsx
├── navigation/
│   ├── AuthNavigator.tsx    # Stack: Login → Register
│   ├── MainNavigator.tsx    # Placeholder Home screen
│   └── AppNavigator.tsx    # Conditional: AuthStack | MainStack
├── types/
│   └── auth.ts              # LoginForm, RegisterForm, User, ApiError, AuthState
└── App.tsx                  # Wrap with AuthProvider + AppNavigator
```

## Data Flow

```
App Launch
    │
    ▼
AuthContext initializes
    │
    ▼
tokenStorage.getToken() + tokenStorage.getUser() ──→ AsyncStorage
    │
    ├── Token found ──→ Call GET /api/auth/me to validate
    │                        │
    │                   ├── 200 OK ──→ update user, isAuthenticated = true
    │                   └── 401 ──→ try refresh token
    │                              │
    │                         ├── Refresh OK ──→ update tokens, isAuthenticated = true
    │                         └── Refresh fail ──→ clear all, isAuthenticated = false
    │
    └── No token ──→ isAuthenticated = false

User taps Login
    │
    ▼
LoginScreen (react-hook-form + Zod validation)
    │
    ▼
authService.login(credentials)
    │
    ▼
apiClient.post('/api/auth/login', data)
    │
    ├── 200 ──→ store token + refresh_token + user in AsyncStorage
    │              └── set user, isAuthenticated = true
    └── 422/401/Network ──→ throw ApiError → screen shows error message

User taps Biometric
    │
    ▼
biometricService.authenticate()
    │
    ├── Success ──→ tokenStorage.getToken() ──→ validate with /me → proceed
    └── Fail/Cancel ──→ show password form (no error)
```

## Axios Interceptor Flow (Refresh Logic)

```
Request sent with Bearer token
    │
    ├── 2xx response ──→ return to caller
    │
    └── 401 response
            │
            ├── Check: is this a refresh request itself?
            │       └── Yes → trigger logout (avoid infinite loop)
            │
            ├── Attempt: POST /api/auth/refresh with refresh token
            │
            │   ├── Refresh succeeds → update tokens in AsyncStorage
            │   │                       → retry original request with new token
            │   │
            │   └── Refresh fails → clear tokens → logout → redirect to Login
            │
            └── 401 from /me endpoint on launch → same refresh flow
```

## Interfaces / Contracts

### Types (src/types/auth.ts)

```typescript
export interface LoginForm {
  email: string;
  password: string;
  remember_me?: boolean;
}

export interface RegisterForm {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  identification_number: string;
  phone: string;
  birth_date?: string;  // ISO date
  gender?: 'M' | 'F';
}

export interface User {
  id: number;
  name: string;
  email: string;
  role: string;
}

export interface AuthState {
  user: User | null;
  token: string | null;
  refreshToken: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

export interface ApiError {
  code: 'VALIDATION_ERROR' | 'UNAUTHORIZED' | 'NETWORK_ERROR' | 'SERVER_ERROR' | 'SESSION_EXPIRED';
  message: string;
  details?: Record<string, string[]>;
}
```

### apiClient (src/services/apiClient.ts)

```typescript
const apiClient = axios.create({
  baseURL: process.env.EXPO_PUBLIC_API_URL || 'http://localhost:8000',
  timeout: 15000,
});

let isRefreshing = false;
let refreshQueue: Array<(token: string) => void> = [];

// Request interceptor: inject token
apiClient.interceptors.request.use(async (config) => {
  const token = await tokenStorage.getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor: normalize errors + refresh logic
apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    // 401 → attempt refresh
    if (error.response?.status === 401 && !originalRequest._retry) {
      if (isRefreshing) {
        // Queue the request until refresh completes
        return new Promise((resolve) => {
          refreshQueue.push((token) => {
            originalRequest.headers.Authorization = `Bearer ${token}`;
            resolve(apiClient(originalRequest));
          });
        });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        const refreshToken = await tokenStorage.getRefreshToken();
        if (!refreshToken) throw new Error('No refresh token');

        const response = await axios.post(`${baseURL}/api/auth/refresh`, {
          refresh_token: refreshToken,
        });

        const { token, refresh_token } = response.data;
        await tokenStorage.setTokens(token, refresh_token);

        // Retry queued requests
        refreshQueue.forEach(cb => cb(token));
        refreshQueue = [];

        originalRequest.headers.Authorization = `Bearer ${token}`;
        return apiClient(originalRequest);
      } catch {
        // Refresh failed → logout
        await tokenStorage.clearAll();
        refreshQueue.forEach(cb => cb(''));
        refreshQueue = [];
        return Promise.reject({ code: 'SESSION_EXPIRED', message: 'Tu sesión expiró.' });
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(normalizeError(error));
  }
);
```

### AuthContext (src/contexts/AuthContext.tsx)

```typescript
interface AuthContextValue {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  biometricAvailable: boolean;
  login: (data: LoginForm) => Promise<void>;
  register: (data: RegisterForm) => Promise<void>;
  logout: () => Promise<void>;
  loginWithBiometric: () => Promise<void>;
}
```

### biometricService (src/services/biometricService.ts)

```typescript
// Check if device supports and has enrolled biometrics
export async function isBiometricAvailable(): Promise<boolean>

// Prompt for biometric and return success/failure
export async function authenticate(): Promise<boolean>

// Store a key in AsyncStorage that unlocks biometric auth
export async function enableBiometric(): Promise<void>

// Remove biometric key (user disables it from settings)
export async function disableBiometric(): Promise<void>

// Check if user has enabled biometric
export async function isBiometricEnabled(): Promise<boolean>
```

## Component Inventory

| Component | File | Responsibility |
|-----------|------|----------------|
| `LoginScreen` | `src/screens/auth/LoginScreen.tsx` | Email/password form with RHF + Zod, remember-me toggle, inline errors, biometric button (conditional) |
| `RegisterScreen` | `src/screens/auth/RegisterScreen.tsx` | 7-field registration form, password confirmation, optional fields |
| `AuthNavigator` | `src/navigation/AuthNavigator.tsx` | Stack: Login ↔ Register (link to register on Login, back link on Register) |
| `MainNavigator` | `src/navigation/MainNavigator.tsx` | Stack with placeholder HomeScreen |
| `AppNavigator` | `src/navigation/AppNavigator.tsx` | Conditional render based on `isAuthenticated`; blocks navigation while `isLoading` |
| `AuthProvider` | `src/contexts/AuthContext.tsx` | Context provider with all auth methods and state |
| `biometricService` | `src/services/biometricService.ts` | expo-local-authentication wrapper for availability, enroll check, and auth |
| `tokenStorage` | `src/services/tokenStorage.ts` | AsyncStorage CRUD for `auth_token`, `auth_refresh_token`, `auth_user` |

## Navigation Flow

```
AppNavigator
    │
    ├── isLoading === true
    │       └── Show blank screen (prevents flash of wrong stack)
    │
    ├── isAuthenticated === false
    │       └── AuthNavigator
    │              ├── LoginScreen (initial)
    │              │       └── Link → RegisterScreen
    │              └── RegisterScreen
    │                      └── Back → LoginScreen
    │
    └── isAuthenticated === true
            └── MainNavigator
                   └── HomeScreen (placeholder)
```

## Error Normalization Strategy

| HTTP Status | Original Shape | Normalized |
|-------------|----------------|------------|
| 200-299 | `{ data: { user, token } }` | Pass through |
| 422 | `{ message, errors: { field: [] } }` | `{ code: 'VALIDATION_ERROR', message: 'Error de validación', details: errors }` |
| 401 | `{ message: 'Unauthorized' }` | `{ code: 'UNAUTHORIZED', message: 'Credenciales inválidas' }` → trigger logout |
| 5xx | `{ message }` | `{ code: 'SERVER_ERROR', message }` |
| Network | `TypeError` | `{ code: 'NETWORK_ERROR', message: 'Error de conexión. Verifica tu internet.' }` |

Screens access `error.details` to map field-level validation errors back to form fields using `setError(field, { message })`.

## Dependencies to Install

```json
{
  "@react-navigation/native": "^7.x",
  "@react-navigation/native-stack": "^7.x",
  "react-native-screens": "latest",
  "react-native-safe-area-context": "latest",
  "@react-native-async-storage/async-storage": "latest",
  "axios": "^1.x",
  "react-hook-form": "^7.x",
  "zod": "^3.x",
  "@hookform/resolvers": "^3.x",
  "expo-local-authentication": "latest",
  "expo-crypto": "latest"
}
```

**Note on biometrics**: `expo-local-authentication` requires a device or simulator with biometric sensors. On web or unsupported devices, the biometric button is hidden. Test on a physical device or Android emulator with fingerprint enrolled.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | Zod schemas validate correctly | Test schema parsing with valid/invalid data |
| Unit | tokenStorage CRUD | Mock AsyncStorage, test get/set/remove |
| Unit | AuthContext state transitions | Test login → loading → authenticated flow |
| Integration | apiClient interceptor | Mock Axios, verify token injection |
| Screen | Form validation UX | Test blur, submit, error display |

## Migration / Rollback

No migration required — greenfield feature.

**Rollback**:
1. `npm uninstall @react-navigation/native @react-navigation/native-stack react-native-screens react-native-safe-area-context @react-native-async-storage/async-storage axios react-hook-form zod @hookform/resolvers expo-local-authentication expo-crypto`
2. Restore `App.tsx` to original boilerplate
3. Delete `src/` auth files

## Open Questions

All resolved:
- ✅ User object cached in AsyncStorage to reduce `/me` calls
- ✅ Refresh token mechanism implemented with interceptor retry logic
- ✅ Biometric auth via `expo-local-authentication` with conditional UI