---
change: doctor-invitation-email
status: proposal
created: 2026-06-10
---

# Proposal: Doctor Invitation Email

## Intent

Replace the security anti-pattern of sending a plaintext auto-generated temp password with a proper account activation flow. RF-1.2 requires that a doctor receives an email with a link to **set** their own password — not receive an admin-assigned one. The current flow violates that requirement and leaks credentials through mail logs.

## Scope

### In Scope
- **DB migration**: add `invitation_token` (nullable, hashed UUID) and `invitation_sent_at` (nullable timestamp) to `users` table
- **`User` model**: add `invitation_token` and `invitation_sent_at` to `$fillable`; add helper methods `hasPendingInvitation()`, `isInvitationExpired()`, `clearInvitation()`
- **`InvitationController`**: new controller with `show()` (GET `/invitation/{token}`) and `activate()` (POST `/invitation/{token}`) actions
- **Route**: `GET /invitation/{token}` and `POST /invitation/{token}` in `routes/web.php`
- **Activation page**: Blade view at `resources/views/invitation/activate.blade.php` — form with password + confirm fields, submit posts to `activate()`
- **Invitation email**: repurpose `DoctorAccountCreated` mailable → rename to `InvitationActivated`; email template `emails/doctor-invitation.blade.php` replaces the credential box with a prominent "Activar mi cuenta" CTA button
- **`CreateUser.php`**: replace temp password generation with UUID token creation + queue `InvitationActivated` mail; user created with `is_active = false`
- **Token lifecycle**: 7-day expiration; expired tokens render a "Enlace expirado" state with "Solicitar nuevo enlace" link to admin
- **Re-invitation**: if admin re-creates a doctor with the same email, old token is overwritten
- **Tests**: extend `UserResourceAuditLogTest.php`; add new Pest feature test for the full invitation flow

### Out of Scope
- Mail delivery configuration (SMTP, Mailpit, etc.) — driver remains `log` in `.env`
- Mobile API or patient-facing flows
- Laravel password reset repurposing (explicitly rejected — "reset password" is semantically wrong for a brand-new doctor)
- `is_active` enforcement on doctor panel login (existing gate handles panel access; `is_active` flag for future hardening)

## Capabilities

> Contract between proposal and specs phases.

### New Capabilities
- `doctor-invitation`: Covers the full invitation lifecycle — token generation on admin creation, email delivery, activation page, password set, and account activation.

### Modified Capabilities
- `users-roles`: RF-1.2 scenarios added — doctor activation via invitation link, not credential assignment. Delta spec required.

## Approach

**Token-based invitation (custom flow)** — selected over Laravel password reset because RF-1.2 describes an **account activation** flow, not a password reset. Laravel's reset email says "reset your password" which is semantically incorrect for a brand-new doctor who has no password yet.

Flow:
1. Admin creates doctor via Filament → `CreateUser.php` generates a UUID v4 token → hashed and stored in `invitation_token`; `invitation_sent_at` = now; `is_active = false`
2. After DB commit, `InvitationActivated` mailable is queued with link `/invitation/{raw-token}`
3. Doctor clicks link → `InvitationController::show()` validates token (exists, not expired) → renders `invitation/activate.blade.php`
4. Doctor submits password form → `InvitationController::activate()` validates → password set (`password` column), `is_active = true`, `invitation_token = null`, `invitation_sent_at = null`
5. Redirect to `/doctor/login` with success toast

**Token security**: Store SHA-256 hash of the UUID in `invitation_token`. The raw UUID travels in the URL; the hash is what's matched in the DB. Token length: 36 chars (UUID v4).

**Activation page technology**: Blade (user explicitly chose Blade over Livewire for this single-purpose page).

**Backwards compat**: `CreateUser.php` no longer accepts an explicit `password` field for doctors — the form schema removes it. Existing `UserResourceAuditLogTest.php` must be updated.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `database/migrations/` | New | `add_invitation_fields_to_users` migration |
| `app/Models/User.php` | Modified | `invitation_token`, `invitation_sent_at` fillable + helpers |
| `app/Filament/Resources/User/Pages/CreateUser.php` | Modified | Token generation replaces temp password |
| `app/Mail/InvitationActivated.php` | New (renamed) | Replaces `DoctorAccountCreated` mailable |
| `resources/views/emails/doctor-invitation.blade.php` | New | Replaces credential email with CTA button |
| `app/Http/Controllers/InvitationController.php` | New | `show()` + `activate()` |
| `resources/views/invitation/activate.blade.php` | New | Activation form (Blade) |
| `routes/web.php` | Modified | `/invitation/{token}` GET + POST |
| `tests/Feature/Admin/UserResourceAuditLogTest.php` | Modified | Update for new flow |
| `tests/Feature/InvitationFlowTest.php` | New | Full invitation flow test |
| `openspec/specs/users-roles/spec.md` | Modified | Delta: RF-1.2 scenarios |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Token security — exposed in URLs and mail logs | Low | Store SHA-256 hash in DB; UUID v4 is unguessable; log driver means no real email exposure |
| Expired token UX — user lands on dead link | Medium | Graceful "Enlace expirado" page with admin contact instructions |
| Mail driver is `log` — no real email delivery | Low | Documented; team aware; SMTP/Mailpit setup is separate |
| Backward compat break — existing tests fail | Medium | Update `UserResourceAuditLogTest.php` alongside the code change |

## Rollback Plan

Since this change adds nullable columns (`invitation_token`, `invitation_sent_at`) and new files only:
1. Revert `CreateUser.php` to temp password generation (no DB change needed — columns are nullable)
2. Delete new files: `InvitationController`, `InvitationActivated`, `activate.blade.php`, `doctor-invitation.blade.php`
3. Remove new route entries from `web.php`
4. Migration: rollback or leave harmless (columns are nullable, unused if feature is off)
5. Revert test changes

**No data loss risk** — the change is additive and non-destructive.

## Dependencies

- None. Standalone change — does not depend on any other open change.

## Success Criteria

- [ ] Admin creates a doctor and the email lands in `storage/logs/mail.log` with an activation link
- [ ] Doctor visits `/invitation/{token}` and sees the activation form
- [ ] Doctor sets a password and is redirected to `/doctor/login`
- [ ] Doctor logs in successfully with the new password
- [ ] Token cannot be reused after activation
- [ ] Expired token (7+ days) shows "Enlace expirado" page
- [ ] `UserResourceAuditLogTest.php` passes
- [ ] New `InvitationFlowTest.php` passes