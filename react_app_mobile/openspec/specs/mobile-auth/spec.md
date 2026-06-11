# Mobile Authentication Specification

## Purpose

The mobile-auth capability provides secure patient authentication for the MedConnect Expo app. It covers login, registration, token persistence, auth state management, API client integration, and navigation guards.

## Requirements

### Requirement: Login Form Validation

The LoginScreen MUST validate email format and require a password of at least 8 characters before submission. The form MUST use `mode: 'onBlur'` validation — fields validate when the user leaves them, not on every keystroke.

#### Scenario: Valid login submission

- GIVEN the user has entered a valid email and password (≥8 chars)
- WHEN the user taps "Iniciar Sesión"
- THEN the form MUST submit and the system MUST call the login API
- AND the submit button MUST be disabled during the request

#### Scenario: Invalid email format

- GIVEN the user has entered an invalid email (e.g., "user@")
- WHEN the user leaves the email field (onBlur)
- THEN the system MUST display "Ingresa un email válido" below the field
- AND the form MUST NOT submit until the error is cleared

#### Scenario: Password too short

- GIVEN the user has entered a password with fewer than 8 characters
- WHEN the user leaves the password field (onBlur)
- THEN the system MUST display "La contraseña debe tener al menos 8 caracteres"
- AND the form MUST NOT submit until the error is cleared

### Requirement: Remember Me Token Persistence

When "Recordarme" is checked, the system MUST store the auth token in AsyncStorage with a persistent key. The token MUST persist across app restarts.

#### Scenario: Remember me saves token

- GIVEN the user has entered valid credentials and checked "Recordarme"
- WHEN login succeeds
- THEN the system MUST store the token in AsyncStorage under key `auth_token`
- AND subsequent app launches MUST retrieve the token and skip the login screen

#### Scenario: Remember me not checked — session only

- GIVEN the user has entered valid credentials and NOT checked "Recordarme"
- WHEN login succeeds
- THEN the system MUST store the token in AsyncStorage under key `auth_token`
- AND the token persists for the session (no difference in storage behavior)

### Requirement: Registration Form Validation

The RegisterScreen MUST validate all required fields: name, email, password, password_confirmation, identification_number, phone. Optional fields: birth_date, gender.

#### Scenario: Registration with all required fields

- GIVEN the user has entered all required fields correctly
- WHEN the user taps "Registrarse"
- THEN the form MUST submit and call the register API
- AND the submit button MUST be disabled during the request

#### Scenario: Password mismatch

- GIVEN the user has entered "Password123" for password and "Password456" for password_confirmation
- WHEN the user leaves the password_confirmation field (onBlur)
- THEN the system MUST display "Las contraseñas no coinciden"
- AND the form MUST NOT submit until the error is cleared

#### Scenario: Missing required field

- GIVEN the user has left the identification_number field empty
- WHEN the user taps "Registrarse"
- THEN the system MUST display "Este campo es obligatorio" for the empty field
- AND the form MUST NOT submit

### Requirement: API Error Normalization

The apiClient MUST normalize all API errors into a consistent shape: `{ code: string, message: string, details?: Record<string, string[]> }`. This enables screens to map field-level errors back to form fields.

#### Scenario: 422 validation error mapping

- GIVEN the API returns HTTP 422 with `{ errors: { email: ["El email ya está registrado"] } }`
- WHEN the response is received
- THEN the apiClient MUST transform it to `{ code: "VALIDATION_ERROR", message: "Error de validación", details: { email: ["El email ya está registrado"] } }`
- AND the LoginScreen MUST display "El email ya está registrado" below the email field

#### Scenario: 401 unauthorized response

- GIVEN the API returns HTTP 401 with `{ message: "Unauthorized" }`
- WHEN the response is received
- THEN the apiClient MUST transform it to `{ code: "UNAUTHORIZED", message: "Credenciales inválidas" }`
- AND trigger the logout flow (clear token, redirect to Login)

#### Scenario: Network error handling

- GIVEN the network is unavailable or the server is unreachable
- WHEN the request fails
- THEN the apiClient MUST return `{ code: "NETWORK_ERROR", message: "Error de conexión. Verifica tu internet." }`
- AND the screen MUST display the error message to the user

### Requirement: Auth State Management

The AuthContext MUST expose: `user`, `token`, `isAuthenticated`, `isLoading`, `login()`, `register()`, `logout()`. The context MUST initialize by checking AsyncStorage for an existing token on app launch.

#### Scenario: App launch with valid stored token and user cache

- GIVEN the app launches with a valid `auth_token` and cached `auth_user` in AsyncStorage
- WHEN the AuthContext initializes
- THEN `isLoading` MUST be true while the system calls `GET /api/auth/me` to validate the token
- AND if the response is 200, `user` MUST be updated from the response (cache is kept fresh)
- AND if the response is 401, the system MUST clear both `auth_token` and `auth_user` and redirect to Login
- AND `isAuthenticated` MUST be true once validation succeeds

#### Scenario: App launch with valid stored token, no user cache

- GIVEN the app launches with a valid `auth_token` but no `auth_user` in AsyncStorage
- WHEN the AuthContext initializes
- THEN `isLoading` MUST be true while the system calls `GET /api/auth/me`
- AND if the response is 200, `user` MUST be populated and stored in AsyncStorage
- AND `isAuthenticated` MUST be true

#### Scenario: App launch with expired/invalid token

- GIVEN the app launches with a stored `auth_token`
- WHEN the AuthContext calls `GET /api/auth/me` and receives 401
- THEN the system MUST clear both `auth_token` and `auth_user` from AsyncStorage
- AND `isAuthenticated` MUST be false
- AND the user MUST be routed to the AuthStack (Login)

### Requirement: Navigation Guards

The AppNavigator MUST render AuthStack when `isAuthenticated === false` and MainStack when `isAuthenticated === true`. Navigation MUST NOT occur while `isLoading === true`.

#### Scenario: Unauthenticated user reaches Login

- GIVEN the user is not authenticated (`isAuthenticated === false`)
- WHEN the app renders
- THEN the AppNavigator MUST render the AuthStack containing LoginScreen and RegisterScreen
- AND the user MUST NOT be able to access MainStack screens

#### Scenario: Authenticated user bypasses Login

- GIVEN the user is authenticated (`isAuthenticated === true`)
- WHEN the app renders
- THEN the AppNavigator MUST render the MainStack
- AND the user MUST NOT see the Login screen

### Requirement: Form Submit Disable During Request

All form submissions MUST disable the submit button while the API request is in-flight to prevent double-submission.

#### Scenario: Submit button disabled during login

- GIVEN the user has filled the login form correctly
- WHEN the user taps "Iniciar Sesión"
- THEN the button MUST show a loading indicator and be disabled
- AND a second tap MUST have no effect
- AND the button MUST re-enable only on request completion (success or failure)

## Scenarios Summary

| Requirement | Happy Path | Edge Cases |
|-------------|-----------|------------|
| Login Form Validation | Valid submission | Invalid email, short password |
| Remember Me Token Persistence | Token saved and restored | Expired token on launch |
| Registration Form Validation | All fields valid | Password mismatch, missing required |
| API Error Normalization | Success response | 422, 401, network errors |
| Auth State Management | Valid token loaded | Invalid/expired token cleared |
| Navigation Guards | Conditional stack rendering | Loading state prevents navigation |
| Form Submit Disable | Button disabled during request | No double-submission |
| User Object Cache | User cached on login/me response | Cache used on next launch |
| Refresh Token | Token refreshed before expiry | 401 triggers refresh attempt |
| Biometric Auth | Fingerprint/face login option | Fallback to password |

## Constraints

- All form validation MUST use Zod schemas with `mode: 'onBlur'` in react-hook-form
- Token storage keys: `auth_token` (token), `auth_user` (user object), `auth_refresh_token` (refresh token)
- API base URL: `process.env.EXPO_PUBLIC_API_URL || 'http://localhost:8000'`
- Auth endpoints: `POST /api/auth/login`, `POST /api/auth/register`, `POST /api/auth/logout`, `POST /api/auth/refresh`, `GET /api/auth/me`
- Biometric: expo-local-authentication for fingerprint/FaceID, falls back to password on failure or unavailability

### Requirement: User Object Caching

The system MUST store the user object in AsyncStorage alongside the token to avoid calling `me()` on every app launch. The cache MUST be updated on every successful `/me` response.

#### Scenario: User cached on successful login

- GIVEN the user logs in successfully
- WHEN the login response contains the user object
- THEN the system MUST store both `auth_token` and `auth_user` in AsyncStorage

#### Scenario: User cache used on app launch

- GIVEN the app launches with a cached `auth_user` in AsyncStorage
- WHEN the AuthContext initializes
- THEN the cached user MUST be immediately available (reduces UI flash)
- AND the system MUST still call `GET /api/auth/me` to validate the token

#### Scenario: User cache cleared on logout

- GIVEN the user is logged out
- WHEN `logout()` is called
- THEN the system MUST clear both `auth_token` and `auth_user` from AsyncStorage

### Requirement: Refresh Token Mechanism

The system MUST implement a refresh token flow to handle token expiration gracefully without forcing the user to re-enter credentials.

#### Scenario: Token refreshed before expiry

- GIVEN the access token is about to expire (5 minutes before expiry)
- WHEN the apiClient receives a 401 response
- THEN the system MUST first attempt to refresh the token using `POST /api/auth/refresh` with the refresh token
- AND if the refresh succeeds, retry the original request with the new access token
- AND update both `auth_token` and `auth_refresh_token` in AsyncStorage

#### Scenario: Refresh token also expired

- GIVEN the apiClient receives a 401 and the refresh attempt also fails
- THEN the system MUST clear both tokens from AsyncStorage
- AND trigger the logout flow (clear user, redirect to Login)

#### Scenario: Refresh endpoint unavailable

- GIVEN the `POST /api/auth/refresh` endpoint returns an error or is unreachable
- THEN the system MUST trigger the logout flow
- AND display "Tu sesión expiró. Iniciá sesión de nuevo."

### Requirement: Biometric Authentication

The system MUST offer biometric login (fingerprint / Face ID) as an alternative to password, with seamless fallback.

#### Scenario: Biometric available and authenticated

- GIVEN the user has enabled biometric auth and the device supports it
- WHEN the user taps the biometric button on the Login screen
- THEN the system MUST prompt for biometric verification
- AND if successful, retrieve the stored token and proceed to the main app

#### Scenario: Biometric unavailable on device

- GIVEN the device does not support biometric authentication
- WHEN the user is on the Login screen
- THEN the biometric button MUST NOT be displayed

#### Scenario: Biometric available but not enrolled

- GIVEN the device supports biometric but the user has not enrolled any
- WHEN the user is on the Login screen
- THEN the biometric button MUST NOT be displayed

#### Scenario: Biometric verification fails

- GIVEN the user taps the biometric button and the biometric prompt appears
- WHEN the user cancels or fails biometric verification
- THEN the system MUST NOT throw an error
- AND the user MUST be able to fall back to password login