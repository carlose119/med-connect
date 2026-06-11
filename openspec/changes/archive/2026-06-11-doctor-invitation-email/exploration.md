---
change: doctor-invitation-email
status: exploration
created: 2026-06-10
---

# Exploration: doctor-invitation-email

RF-1.2 — Sistema de invitación por email para médicos creados por el admin.

## Current State

Admin creates a doctor via `CreateUser.php` (Filament `CreateRecord`). When `role === 'doctor'`:

1. A temp password is auto-generated: 8 uppercase alphanumeric chars (`Str::upper(Str::random(8))`)
2. The password is bcrypt'd and stored in the `users` table
3. A `Doctor` profile row is created in the same DB transaction
4. After commit, `Mail::to($user->email)->queue(new DoctorAccountCreated($user, $tempPassword))` fires
5. The email (`DoctorAccountCreated.php` mailable → `emails/doctor-account-created.blade.php`) sends the **plaintext temp password** in a dark code block, plus a link to `/doctor/login`

**What exists today:**
- `users` table: id, name, email, email_verified_at, password, **is_active**, role, remember_token, timestamps
- `password_reset_tokens` table: exists (standard Laravel) but is **not used**
- `DoctorAccountCreated.php` mailable (implements `ShouldQueue`)
- `DoctorPanelProvider.php` — Filament panel at `/doctor` with built-in login form
- No web auth controllers (no ForgotPasswordController, no ResetPasswordController)
- No `invitation_token` / `invitation_sent_at` fields
- No activation page or route
- Mail driver: `log` (writes to `storage/logs/mail.log`, not actually sent)

**The problem with current approach:**
- Sending a plaintext temp password via email is a security anti-pattern (email is not secure, password appears in mail store logs)
- RF-1.2 says "el médico recibe un email de activación con link para definir su contraseña" — the password should be set by the doctor, not assigned by admin

---

## Affected Areas

- `app/Models/User.php` — needs `invitation_token` + `invitation_sent_at` fields; optional helper methods
- `app/Filament/Resources/Users/Pages/CreateUser.php` — change from temp password generation to token generation + queue invitation email
- `app/Mail/DoctorAccountCreated.php` — replace temp password with activation link; rename class to reflect new intent
- `resources/views/emails/doctor-account-created.blade.php` — redesign email template (remove credential box, add activation button)
- `routes/web.php` — add `/invitation/{token}` GET route pointing to a new activation page
- `app/Http/Controllers/InvitationController.php` — new controller: validate token, render activation form, handle password set + account activate
- `database/migrations/` — new migration adding `invitation_token` + `invitation_sent_at` to `users`
- `tests/Feature/Admin/UserResourceAuditLogTest.php` — extend existing tests to cover the new invitation flow
- `openspec/specs/users-roles/spec.md` — add RF-1.2 delta scenarios

---

## Approaches

### 1. Token-based invitation (custom invitation flow)

Admin creates doctor → generates a UUID token → saves to `invitation_token` + `invitation_sent_at` on the user → sends email with link `/invitation/{token}` → doctor lands on activation page → sets password → `is_active = true`, token cleared → redirected to `/doctor/login`.

**Pros:**
- Full control over the UX and email narrative ("activate your account" not "reset password")
- Token can expire (e.g., 7 days)
- Re-invitation is trivial (regenerate token)
- Standard, well-understood pattern
- No dependency on Laravel's password reset flow

**Cons:**
- New DB columns (`invitation_token`, `invitation_sent_at`)
- New route, controller, view, and form
- Token lifecycle management (expire, revoke, re-send)
- More code to maintain

**Effort:** Medium

---

### 2. Extend Laravel password reset (reuse built-in flow)

Admin creates doctor → triggers `Password::sendResetLink($user->email)` → doctor receives Laravel's reset email (from `resources/views/emails/exceptions/*.blade.php` or custom reset template) → clicks link → lands on standard `reset-password` route → sets password → done.

**Pros:**
- Uses built-in Laravel scaffolding — no new DB columns, no new routes
- Token expiration, revocation, and email already handled
- Minimal new code

**Cons:**
- The email says "reset your password" not "activate your account" — confusing UX for a brand-new doctor
- The flow is designed for "you forgot your password", not "your admin just created your account"
- Laravel's reset tokens use the `password_reset_tokens` table which exists but is not currently used — repurposing it for invitations could create conceptual confusion
- The doctor has no password initially, so "reset" is semantically incorrect

**Effort:** Low

---

### 3. Hybrid — token invitation but borrow Laravel's token mechanism

Use the existing `password_reset_tokens` table for invitation tokens instead of adding new columns. Admin creates doctor → generate token → insert into `password_reset_tokens` (same table Laravel uses) → send email with link `/invitation/{token}` → validate via `Password::broker('users')->reset(...)` → activate.

**Pros:**
- No new DB columns
- Leverages Laravel's token validation and expiration (24h default)
- Cleaner than Option 1, more semantically correct than Option 2

**Cons:**
- Overloading `password_reset_tokens` for invitations is semantically confusing (the table is for password resets, not account activations)
- Token intent is unclear — same table serves two purposes
- The reset email blade still says "reset", not "activate"
- If Laravel updates token format or behavior, this could break silently

**Effort:** Low-Medium

---

## Recommendation

**Option 1: Token-based invitation (custom flow)** is the best fit for RF-1.2.

The PRD says "email de activación con link para definir su contraseña" — this is an account activation flow, not a password reset. Using Laravel's password reset (Option 2 or 3) is semantically wrong and creates UX confusion for brand-new doctors who expect "activate your account", not "reset your password."

Option 1 gives us:
- Clean activation UX ("tu cuenta ha sido creada, activa ahora")
- Token expiration for security
- Full control over email copy and branding
- Easy re-invitation path if the token expires or the doctor doesn't receive the email

The added effort (new columns, new route/controller/view) is manageable and keeps the domain model clean.

---

## Risks

1. **Token security** — `invitation_token` must be a cryptographically random token (UUID v4 or `Str::random(64)`), not sequential or guessable. Store the hash in the DB, not the plain token.
2. **Expired token UX** — the activation page must gracefully handle expired tokens with a "request new link" option (or a resend link sent to the admin who can relay it).
3. **Mail driver is `log`** — currently emails write to `storage/logs/mail.log`. While this is fine for local dev, the team should be aware that actual email delivery needs to be verified separately (e.g., switch `.env` `MAIL_MAILER` to `smtp` or use something like Mailpit).
4. **Backward compat** — the existing test `UserResourceAuditLogTest.php` tests `CreateUser` with `password` field in the form. The form schema will need to change (remove explicit password field for doctors, auto-generate token instead). Existing tests must be updated.
5. **Multiple invitations** — if admin re-creates a doctor with the same email, the old invitation token should be invalidated. The token generation should either overwrite the previous token or be guarded by unique constraint.

---

## Ready for Proposal

**Yes.** The exploration is complete. The recommended approach (Option 1) is clear and well-scoped. The orchestrator should proceed to `sdd-propose` with these key decisions:

1. Add `invitation_token` (nullable string, hashed UUID) and `invitation_sent_at` (nullable timestamp) to `users`
2. Create `InvitationController` with `show()` (GET `/invitation/{token}`) and `activate()` (POST `/invitation/{token}`)
3. Create `InvitationActivated` mailable (rename from `DoctorAccountCreated` or keep and repurpose)
4. Update `CreateUser.php` to generate token instead of temp password
5. Update email template: remove credential box, add "activate your account" button
6. Token expires after 7 days; expired tokens render a "link expired" page with instructions
7. Tests: extend `UserResourceAuditLogTest.php` + add new feature test for the invitation flow

**One clarification needed before proposal**: Should the activation page be a Blade view served by a controller, or a Livewire component? Given this is a single-purpose page with minimal interaction (render form + submit), a Blade controller view is simpler and avoids Filament panel overhead. But if the team prefers consistency with Filament patterns, a Livewire component could work.