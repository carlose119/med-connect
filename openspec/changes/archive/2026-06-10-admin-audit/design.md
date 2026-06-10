# Design: Admin Audit

## Technical Approach

An append-only audit log that records every admin action with actor, action verb, subject, and timestamp. Admins can view the log via a read-only Filament resource; writes go through a single `LogAdminAction` callable that Filament resources invoke in their lifecycle hooks.

**Architecture**: Action + Filament Resource + Boot guard. No observers, no event system — explicit calls from Filament hooks keep the trace path visible.

## Architecture Decisions

### Decision: Write mechanism

**Choice**: `LogAdminAction` callable invoked explicitly from Filament resource lifecycle hooks (`afterCreate`, `afterUpdate`, `afterDelete`).
**Alternatives considered**:
- Observers on Doctor/Patient/User models — adds silent coupling, harder to trace which actions are audited
- Laravel events — introduces indirection, overkill for a single consumer
**Rationale**: Filament resources already have lifecycle hooks. Explicit calls make audit coverage visible and auditable — you can grep for `LogAdminAction` to find every audited path.

### Decision: Immutability enforcement

**Choice**: `AuditLog::boot()` events blocking `saving` (when model exists) and `deleting`.
**Alternatives considered**:
- Policy with deny rules — doesn't cover direct Eloquent calls
- Database trigger — not portable, not visible in PHP code
**Rationale**: Matches the exact pattern already used by `MedicalNote`. The `saving` check allows create, rejects update (model exists), and `deleting` always throws.

### Decision: AuditLogResource navigation group

**Choice**: `Admin` group, icon `Heroicon::OutlinedShieldCheck`, sort 99.
**Rationale**: Audit log is an admin tool. Placing it at the bottom of the Admin group with a distinct icon signals it's meta/infrastructure rather than domain content.

## Data Flow

```
Admin action in Filament resource
  → afterCreate / afterUpdate / afterDelete hook
    → LogAdminAction::execute($user, $action, $subjectType, $subjectId, $metadata)
      → AuditLog::create([...])
        → [immutable row written]
```

Admin viewing the log:
```
GET /admin/audit-logs
  → AuditLogResource::getEloquentQuery()
    → paginated AuditLog list (read-only)
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Actions/Admin/LogAdminAction.php` | Create | Callable action for writing audit rows |
| `app/Models/AuditLog.php` | Modify | Add `boot()` blocking update/delete |
| `app/Filament/Resources/AdminAudit/AuditLogResource.php` | Create | Read-only Filament resource |
| `app/Filament/Resources/AdminAudit/Tables/AuditLogsTable.php` | Create | List table with filters |
| `app/Filament/Resources/AdminAudit/Pages/ListAuditLogs.php` | Create | List page |
| `app/Filament/Resources/AdminAudit/Pages/ViewAuditLog.php` | Create | Detail page with metadata JSON viewer |
| `tests/Unit/Actions/Admin/LogAdminActionTest.php` | Create | Action unit tests |
| `tests/Unit/Models/AuditLogTest.php` | Create | Append-only model tests |
| `tests/Feature/Filament/Resources/AdminAudit/AuditLogResourceTest.php` | Create | Resource feature tests |

## Interfaces / Contracts

### LogAdminAction

```php
class LogAdminAction
{
    public function __invoke(
        User $user,
        string $action,
        string $subjectType,
        int $subjectId,
        array $metadata = [],
        ?string $ipAddress = null,
    ): AuditLog;
}
```

- `action`: verb string, e.g. `created`, `updated`, `deleted`, `toggled`, `assigned`
- `subjectType`: short class name, e.g. `Doctor`, `User`, `Patient` — derived from FQCN via `class_basename()`
- `ipAddress`: resolved from `request()->ip()` if not provided

### AuditLog append-only contract

- `AuditLog::boot()` throws `\LogicException` when `saving` with `$note->exists === true`
- `AuditLog::boot()` throws `\LogicException` on `deleting`
- `create()` still works normally — only mutation is blocked

### AuditLogResource

- `canCreate = false`, `canEdit = false`, `canDelete = false`
- Columns: user (name), actor_type, action, subject_type, subject_id, ip_address, created_at
- Filters: actor_type (select), action (select), subject_type (select), date range (created_at)
- View page: full metadata JSON displayed via `<pre>` or code block

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `LogAdminAction::execute()` creates correct row | Direct call, assert row fields |
| Unit | `AuditLog` rejects update via `saving` | `->save()` on existing model throws |
| Unit | `AuditLog` rejects delete via `deleting` | `->delete()` on existing model throws |
| Feature | AuditLogResource renders list page | GET `/admin/audit-logs` → 200 |
| Feature | AuditLogResource renders view page | GET `/admin/audit-logs/{id}` → 200 |
| Feature | AuditLogResource cannot create/edit/delete | `canCreate`, `canEdit`, `canDelete` return false |

## Migration / Rollout

No migration required. The `audit_logs` table already exists with no `updated_at` column.

## Open Questions

- [ ] Should audit logging be wired into existing admin resources (DoctorResource, PatientResource, UserResource) in this change, or deferred?
  - **Recommendation**: Deferred. Wire into existing resources is a separate integration task. This change establishes the foundation (`LogAdminAction` + `AuditLogResource` + boot guard). Integration can be added resource-by-resource.
- [ ] Do we need to log doctor-panel actions (e.g. doctor creates a medical note)?
  - **Recommendation**: Not in this change. The spec scope is "admin action". Doctor actions can be a separate change if needed.