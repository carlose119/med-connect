---
change: doctor-invitation-email
status: design
created: 2026-06-10
---

# Design: Doctor Invitation Email

## Technical Approach

Replace the temp-password email flow with a token-based activation flow. When an admin creates a doctor, the system generates a UUID v4 token, stores its SHA-256 hash in the `users` table, queues an `InvitationActivated` mailable, and creates the user with `is_active = false`. The doctor clicks the link in the email, sets their own password, and the account activates. The raw UUID travels in the URL; only the hash is stored — this prevents credential leakage through mail logs or browser history.

## Architecture Decisions

### Decision: Token storage — SHA-256 hash vs. plain UUID

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Plain UUID in DB | Simple, fast lookup, but DB breach exposes usable tokens | **Rejected** |
| SHA-256 hash in DB | One-way, DB breach doesn't expose usable tokens; lookup still fast with index | **Selected** |
| Encrypted UUID | Reversible, but requires key management complexity | **Rejected** |

**Rationale**: SHA-256 is deterministic so we can verify a raw token by hashing it and comparing. UUID v4 provides sufficient entropy (122 bits) that the raw token in the URL is unguessable. The hash adds defense-in-depth — if the DB is breached, tokens cannot be used directly.

### Decision: Token expiration — 7 days, server-side check

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Client-side redirect (JS timer) | UX fails if user bookmarks the page | **Rejected** |
| URL query param with expiry | Expiry is mutable if user edits URL | **Rejected** |
| `invitation_sent_at` + server-side Carbon comparison | Single source of truth, enforced on every request | **Selected** |

**Rationale**: Storing `invitation_sent_at` as a timestamp and computing expiry in PHP keeps logic centralized in `User` model helpers. No separate `expires_at` column needed.

### Decision: Re-invitation — reuse inactive user record

| Option | Tradeoff | Decision |
|--------|----------|----------|
| Create new user record always | Simpler but orphan old inactive record | **Rejected** |
| Reuse inactive user with same email | Keeps user_id stable, avoids FK orphaning | **Selected** |

**Rationale**: Doctors may have FK references (appointments, notes). Reusing the existing inactive record preserves those references and allows the admin to re-invite without data loss.

### Decision: Blade for activation page (vs. Livewire)

User explicitly chose Blade for this single-purpose page. No shared state with the Filament panel is needed — the page is stateless. Livewire would add unnecessary overhead.

## Data Flow

```
Admin submits CreateUser form (role=doctor)
  │
  ▼
CreateUser::handleRecordCreation()
  ├── Generate UUID v4 → rawToken
  ├── Hash: hash('sha256', rawToken) → storedToken
  ├── Create User (is_active=false, invitation_token=storedToken, invitation_sent_at=now)
  ├── Create Doctor profile (same transaction)
  ├── Queue InvitationActivated mailable (rawToken, expiresAt)
  └── AuditLog::create()
        │
        ▼
Mail job picks up InvitationActivated
  └── Email lands in storage/logs/mail.log
        │
        ▼
Doctor clicks /invitation/{rawToken}
  │
  ▼
InvitationController::show(rawToken)
  ├── hash('sha256', rawToken) → lookupHash
  ├── User::where('invitation_token', lookupHash)->first()
  ├── Validation: exists? not expired? not consumed?
  └── Render activate.blade.php OR expired.blade.php
        │
        ▼
Doctor submits password form
  │
  ▼
InvitationController::activate(rawToken, password, password_confirmation)
  ├── Validate: password === confirmation, meets Laravel password rules
  ├── hash('sha256', rawToken) → lookupHash
  ├── User::where('invitation_token', lookupHash)->first()
  ├── $user->password = bcrypt(password)
  ├── $user->is_active = true
  ├── $user->invitation_token = null
  ├── $user->invitation_sent_at = null
  ├── $user->save()
  └── Redirect /doctor/login with success toast
```

## Data Model

### Migration: `add_invitation_fields_to_users`

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('invitation_token', 64)->nullable()->index()->after('password');
    $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token');
});
```

- `invitation_token` is 64 chars to accommodate SHA-256 hex (64 chars)
- Nullable: only doctors with pending invitations have a value
- Index: enables fast `WHERE invitation_token = ?` lookups

### User Model Changes

Add to `#[Fillable]` attribute:
```php
#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'invitation_token', 'invitation_sent_at'])]
```

Add to `casts()`:
```php
'invitation_sent_at' => 'datetime',
```

New helper methods on `User`:

| Method | Signature | Logic |
|--------|-----------|-------|
| `generateInvitationToken()` | `public function generateInvitationToken(): string` | Returns raw UUID v4. Hashes it and sets `invitation_token`. Sets `invitation_sent_at` to now. Returns raw token. |
| `hasPendingInvitation()` | `public function hasPendingInvitation(): bool` | `invitation_token !== null` |
| `isInvitationExpired()` | `public function isInvitationExpired(int $days = 7): bool` | `invitation_sent_at !== null && invitation_sent_at->addDays($days)->isPast()` |
| `clearInvitationToken()` | `public function clearInvitationToken(): void` | Sets `invitation_token = null`, `invitation_sent_at = null` |
| `getRawInvitationToken()` | `public function getRawInvitationToken(): ?string` | Looks up the raw token by matching hash. Returns null if no token. (Reverse-lookup for email generation — the raw token is needed for the URL.) |

**Note**: `getRawInvitationToken()` requires the raw token to be available at the time the email is queued (stored in the mailable, not re-computed from the hash). The raw token is stored in the mailable and is not persisted — only the hash is in the DB.

## Controller Design

### `InvitationController` — `app/Http/Controllers/InvitationController.php`

```
GET  /invitation/{token}  → show(Request $request, string $token)
POST /invitation/{token}  → activate(Request $request, string $token)
```

**`show()` logic:**
1. Hash the raw token: `$hash = hash('sha256', $token)`
2. Query: `User::where('invitation_token', $hash)->first()`
3. If no user → redirect to `/doctor/login` (consumed or invalid)
4. If user has `is_active = true` → redirect to `/doctor/login` (already activated)
5. If user `isInvitationExpired()` → render `invitation.expired` view
6. Otherwise → render `invitation.activate` view with `$token` (raw, for form action)

**`activate()` logic:**
1. Validate: `password` (min 8, confirmed), CSRF token
2. Hash the raw token: `$hash = hash('sha256', $token)`
3. Query: `User::where('invitation_token', $hash)->first()`
4. If no user → redirect to `/doctor/login`
5. If user `isInvitationExpired()` → render `invitation.expired`
6. Update: `password = bcrypt($request->password)`, `is_active = true`, clear token fields
7. Redirect `/doctor/login` with `session()->flash('success', 'Cuenta activada. Iniciá sesión.')`

**Form validation rules:**
```php
[
    'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
]
```

**Password rules**: `letters()` and `numbers()` match the existing password conventions in the app. `confirmed` requires a `password_confirmation` field.

## Routes

```php
// routes/web.php
Route::get('/invitation/{token}', [InvitationController::class, 'show'])
    ->middleware('guest')
    ->name('invitation.show');

Route::post('/invitation/{token}', [InvitationController::class, 'activate'])
    ->middleware('guest')
    ->name('invitation.activate');
```

- `guest` middleware ensures only unauthenticated users hit the activation flow
- Named routes for `route()` helper in views and tests
- Token is a string route parameter (UUID v4 = 36 chars)

## Views

### `resources/views/invitation/activate.blade.php`

Single-purpose activation form. Uses the same design language as `doctor-account-created.blade.php` (header, content, footer).

**Form fields:**
- `password` — input[type=password], required, min 8
- `password_confirmation` — input[type=password], required, matches password
- CSRF token (Laravel's `@csrf`)

**States to handle in the view:**
- Validation errors from `activate()` — use `@error('password')` and `@error('password_confirmation')`
- Token validation error from `show()` — if token is invalid/consumed, show a message before the form
- Success message from session flash (for when activation was already done before)

**Structure:**
```blade
<!-- Header: MedConnect branding -->
<!-- Content:
  - H2: "Activar tu cuenta"
  - Paragraph: "Establecé tu contraseña para ingresar a MedConnect"
  - Form: POST to /invitation/{token}
    - @csrf
    - password field + @error
    - password_confirmation field + @error
    - Submit: "Activar mi cuenta"
  - Info box: "Una vez activada, tu cuenta queda lista para usar."
-->
<!-- Footer -->
```

### `resources/views/invitation/expired.blade.php`

**Structure:**
```blade
<!-- Header: same MedConnect branding -->
<!-- Content:
  - H2: "Enlace expirado"
  - Paragraph: "Este enlace de activación venció hace más de 7 días."
  - Info box: "Contactá al administrador para solicitar un nuevo enlace."
  - Link: "Volver al inicio"
-->
<!-- Footer -->
```

## Mailable Redesign

### `InvitationActivated.php` — `app/Mail/InvitationActivated.php`

Renamed from `DoctorAccountCreated`. Replaces `tempPassword` with `invitationToken` (raw UUID, for URL) and `expiresAt` (Carbon, for display).

```php
public function __construct(
    public User $doctorUser,
    public string $invitationToken,   // raw UUID v4
    public Carbon $expiresAt,          // invitation_sent_at + 7 days
) {}
```

**Envelope**: Subject unchanged — `'Tu cuenta de médico ha sido creada — MedConnect'`

**Content**: View `emails.doctor-invitation.blade.php`

**Why not `ShouldQueue` change**: Keep the queue interface. The proposal specifies queue behavior is acceptable. The existing `DoctorAccountCreated` already implements `ShouldQueue`.

### `resources/views/emails/doctor-invitation.blade.php`

Replaces `emails/doctor-account-created.blade.php`. Removes the credential box (`<div class="credential-box">`). Adds a prominent CTA button.

**Key changes:**
- Remove: `credential-box` div with temp password
- Add: "Activar mi cuenta" button linking to `/invitation/{rawToken}`
- Add: "Este enlace expira el {expiresAt->format('d/m/Y')}" (display date in Argentine format)
- Keep: info box with email and role
- Keep: login link button for after activation

### Files to delete

- `app/Mail/DoctorAccountCreated.php` — replaced by `InvitationActivated.php`
- `resources/views/emails/doctor-account-created.blade.php` — replaced by `doctor-invitation.blade.php`

## Testing Strategy

### 1. Feature test: `InvitationFlowTest` — `tests/Feature/InvitationFlowTest.php`

Covers all spec scenarios in Given/When/Then form.

| Scenario | Test |
|----------|------|
| Admin creates doctor → email sent with activation link | Mock mail, assert mailable enqueued with raw token |
| Doctor lands on valid token → sees activation form | `get('/invitation/{valid-token}')` → assert 200, assert form fields present |
| Doctor submits valid password → account activates | `post('/invitation/{valid-token}', ['password' => ..., 'password_confirmation' => ...])` → assert redirect to `/doctor/login`, assert user `is_active = true`, assert token cleared |
| Doctor tries expired token → sees expired page | Mock time, create user with old `invitation_sent_at`, `get('/invitation/{token}')` → assert 200 with expired view |
| Doctor tries to reuse consumed token → redirects to login | Activate once, then `get('/invitation/{same-token}')` → assert redirect |
| Doctor with active account hits invitation URL → redirects | `get('/invitation/{token}')` with `is_active = true` → redirect |

**Test setup helper**: A `InvitationToken::generate(User $user)` factory or trait method that creates a valid token on a user (sets hash + sent_at).

### 2. Unit test: `UserInvitationTest` — `tests/Unit/UserInvitationTest.php`

| Method | Test |
|--------|------|
| `generateInvitationToken()` | Call → assert raw token is UUID v4, hash stored in DB, sent_at set |
| `hasPendingInvitation()` | With token → true; without token → false |
| `isInvitationExpired()` | Fresh token → false; token older than 7 days → true |
| `clearInvitationToken()` | Call → token and sent_at are null |

### 3. Extend: `UserResourceAuditLogTest`

Update the existing doctor creation test to remove `password` field from form data (it's no longer sent). The `temp_password_sent` metadata in the audit log should be replaced with `invitation_sent` boolean.

**Existing test change:**
```php
// Before: 'password' => 'password'
// After: remove password field entirely for doctor creation
// Metadata: 'temp_password_sent' → 'invitation_sent'
```

## Migration / Rollout

**No migration required for existing users** — new columns are nullable and only doctors created after this change will have values. No data backfill needed.

**Rollout sequence:**
1. Create migration (adds nullable columns + index)
2. Update `User` model (fillable + casts + helpers)
3. Create `InvitationController`
4. Add routes to `web.php`
5. Create `InvitationActivated` mailable + `doctor-invitation.blade.php` view
6. Update `CreateUser.php` (token generation, remove temp password, mail queue)
7. Create `activate.blade.php` and `expired.blade.php`
8. Write tests
9. Delete old files (`DoctorAccountCreated.php`, `doctor-account-created.blade.php`)
10. Run migration

**Rollback**: All changes are additive. Revert `CreateUser.php` to temp password generation — the nullable columns remain harmless if unused.

## Open Questions

- [ ] Should the activation email be sent synchronously (direct `Mail::to()`) or queued (`ShouldQueue`)? The existing `DoctorAccountCreated` uses `ShouldQueue`. Recommendation: keep `ShouldQueue` for consistency, but note that this means the activation link won't be visible in logs until the queue worker processes the job. Consider a sync fallback for local dev (use `Mail::send()` in dev, `Mail::queue()` in production).
- [ ] Does the admin panel need a "resend invitation" action on existing inactive doctors? Not in scope for this change, but worth noting for a future `doctor-reinvite` change.

## File Inventory

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/YYYY_MM_DD_HHMMSS_add_invitation_fields_to_users.php` | Create | Adds `invitation_token` (string 64, nullable, indexed) + `invitation_sent_at` (timestamp, nullable) |
| `app/Models/User.php` | Modify | Add `invitation_token` + `invitation_sent_at` to `#[Fillable]`; add casts; add helper methods |
| `app/Http/Controllers/InvitationController.php` | Create | `show()` and `activate()` actions for `/invitation/{token}` |
| `routes/web.php` | Modify | Add GET + POST routes for `/invitation/{token}` |
| `app/Mail/InvitationActivated.php` | Create | Replaces `DoctorAccountCreated` — mailable with raw token + expiry date |
| `resources/views/emails/doctor-invitation.blade.php` | Create | Replaces `doctor-account-created.blade.php` — CTA button instead of credential box |
| `resources/views/invitation/activate.blade.php` | Create | Activation form with password + confirm fields |
| `resources/views/invitation/expired.blade.php` | Create | "Enlace expirado" state page |
| `app/Filament/Resources/Users/Pages/CreateUser.php` | Modify | Token generation replaces temp password; queue `InvitationActivated`; handle re-invitation |
| `app/Mail/DoctorAccountCreated.php` | Delete | Replaced by `InvitationActivated.php` |
| `resources/views/emails/doctor-account-created.blade.php` | Delete | Replaced by `doctor-invitation.blade.php` |
| `tests/Unit/UserInvitationTest.php` | Create | Unit tests for `User` invitation helpers |
| `tests/Feature/InvitationFlowTest.php` | Create | Feature tests for full invitation lifecycle |
| `tests/Feature/Admin/UserResourceAuditLogTest.php` | Modify | Update doctor creation test: remove password field, update metadata key |