# Archive Report: doctor-invitation-email

**Change**: doctor-invitation-email (RF-1.2)
**Archived**: 2026-06-11
**Status**: PASS (0 CRITICAL, 0 WARNING, 0 SUGGESTION)

## Change Summary

Replace the security anti-pattern of sending a plaintext auto-generated temp password with a proper account activation flow. RF-1.2 requires that a doctor receives an email with a link to **set** their own password — not receive an admin-assigned one.

## What Was Implemented

- **DB migration**: Added `invitation_token` (SHA-256 hash, 64 chars) and `invitation_sent_at` (timestamp) to `users` table
- **User model**: Added invitation helpers (`generateInvitationToken()`, `hasPendingInvitation()`, `isInvitationExpired()`, `clearInvitationToken()`)
- **InvitationController**: New controller with `show()` (GET `/invitation/{token}`) and `activate()` (POST `/invitation/{token}`) actions
- **Activation page**: Blade view `resources/views/invitation/activate.blade.php` with password + confirm fields
- **Expired page**: Blade view `resources/views/invitation/expired.blade.php` for tokens older than 7 days
- **Invitation email**: `InvitationActivated` mailable (replaced `DoctorAccountCreated`) with "Activar mi cuenta" CTA button
- **CreateUser.php**: Token generation replaces temp password; handles re-invitation by reusing inactive doctor records
- **Old files deleted**: `DoctorAccountCreated.php`, `doctor-account-created.blade.php`

## Key Files Created/Modified

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/*_add_invitation_fields_to_users.php` | Create | Adds `invitation_token` + `invitation_sent_at` |
| `app/Models/User.php` | Modified | Invitation helpers + fillable fields |
| `app/Http/Controllers/InvitationController.php` | Create | `show()` + `activate()` |
| `routes/web.php` | Modified | GET + POST `/invitation/{token}` routes |
| `resources/views/invitation/activate.blade.php` | Create | Activation form |
| `resources/views/invitation/expired.blade.php` | Create | Expired token page |
| `app/Mail/InvitationActivated.php` | Create | Replaces `DoctorAccountCreated` |
| `resources/views/emails/doctor-invitation.blade.php` | Create | Replaces `doctor-account-created.blade.php` |
| `app/Filament/Resources/Users/Pages/CreateUser.php` | Modified | Token generation, re-invitation logic |
| `app/Mail/DoctorAccountCreated.php` | Delete | Replaced by `InvitationActivated` |
| `resources/views/emails/doctor-account-created.blade.php` | Delete | Replaced by `doctor-invitation.blade.php` |
| `tests/Unit/UserInvitationTest.php` | Create | 4 unit tests for invitation helpers |
| `tests/Feature/InvitationFlowTest.php` | Create | 8 feature tests for full lifecycle |
| `tests/Feature/Admin/UserResourceAuditLogTest.php` | Modified | Updated for new flow |

## Test Results

| Suite | Passed | Failed | Duration |
|-------|--------|--------|----------|
| UserInvitationTest | 4 | 0 | 2.94s |
| InvitationFlowTest | 8 | 0 | 3.48s |
| UserResourceAuditLogTest | 3 | 0 | 2.88s |
| **Total** | **15** | **0** | **~9.3s** |

## Specs Synced

The following delta requirements were merged into `openspec/specs/users-roles/spec.md`:

1. **Doctor Invitation Token Generation** — Admin creates doctor → UUID token generated → SHA-256 hashed → stored in DB → `InvitationActivated` mailable queued
2. **Doctor Activation via Invitation Link** — 4 scenarios: valid token shows form, password set activates account, expired token shows "Enlace expirado", consumed token redirects to login
3. **Doctor Re-invitation** — Admin re-creating doctor with same email reuses inactive user record, regenerates token, requeues mail
4. **Doctor Panel Login After Activation** — Doctor can log in with their new password after activation

## Design Decisions Implemented

| Decision | Status |
|----------|--------|
| Token as SHA-256 hash (not plain UUID) | ✅ |
| Server-side 7-day expiration using `invitation_sent_at` | ✅ |
| Re-invite reuses inactive user record | ✅ |
| Blade for activation page (not Livewire) | ✅ |
| `InvitationActivated` mailable with raw token + expiresAt | ✅ |
| Email CTA replaces credential box | ✅ |

## Artifacts Archived

- `openspec/changes/archive/2026-06-11-doctor-invitation-email/proposal.md`
- `openspec/changes/archive/2026-06-11-doctor-invitation-email/specs/users-roles/spec.md`
- `openspec/changes/archive/2026-06-11-doctor-invitation-email/design.md`
- `openspec/changes/archive/2026-06-11-doctor-invitation-email/tasks.md`
- `openspec/changes/archive/2026-06-11-doctor-invitation-email/verify-report.md`

## SDD Cycle Complete

The change has been fully planned (proposal), specified (specs), designed (design), implemented (apply), verified (verify), and archived. Ready for the next change.