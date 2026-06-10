# Tasks: Admin Audit

**Total estimate**: ~450 lines, 2 phases, ~10 tasks, medium risk.
**Delivery**: Single PR covering all files.

---

## Phase 1: Foundation

### 1.1 Create `LogAdminAction`

**Path**: `app/Actions/Admin/LogAdminAction.php`
**TDD**: `tests/Unit/Actions/Admin/LogAdminActionTest.php`

- [x] **Create** `app/Actions/Admin/` directory
- [x] **Create** `LogAdminAction` class:
  - `__invoke(User $user, string $action, string $subjectType, int $subjectId, array $metadata = [], ?string $ipAddress = null): AuditLog`
  - Resolve `ipAddress` from `request()->ip()` when not provided
  - Use `class_basename($subjectType)` for storing short name
  - `actor_type` hardcoded to `'admin'` (the action is always admin-initiated)
  - Return the created `AuditLog` instance
- [x] **Run** unit tests — green

### 1.2 Add append-only boot events to `AuditLog`

**Path**: `app/Models/AuditLog.php`
**TDD**: `tests/Unit/Models/AuditLogTest.php`

- [x] **Modify** `AuditLog::boot()` — add `static::saving()` that throws `LogicException` when `$note->exists === true`
- [x] **Modify** `AuditLog::boot()` — add `static::deleting()` that always throws `LogicException`
- [x] **Create** `tests/Unit/Models/AuditLogTest.php`:
  - `it('throws LogicException when attempting to update a persisted audit log')` → `->throws(\LogicException::class)`
  - `it('throws LogicException when attempting to delete a persisted audit log')` → `->throws(\LogicException::class)`
  - `it('allows creating a new audit log row')` → `create()` succeeds
- [x] **Run** unit tests — green
- [x] **Note**: Do NOT add a factory yet (audit logs are written by the action, not by tests directly — use `AuditLog::create()` in tests)

### 1.3 Verify phase 1

- [x] Run `pest tests/Unit/Actions/Admin/LogAdminActionTest.php` — green
- [x] Run `pest tests/Unit/Models/AuditLogTest.php` — green

---

## Phase 2: Filament Resource

### 2.1 Create `AuditLogResource`

**Path**: `app/Filament/Resources/AdminAudit/AuditLogResource.php`

- [x] **Create** `app/Filament/Resources/AdminAudit/` directory
- [x] **Create** `AuditLogResource` class:
  - `protected static ?string $model = AuditLog::class`
  - `protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck`
  - `protected static string|\UnitEnum|null $navigationGroup = 'Admin'`
  - `protected static ?int $navigationSort = 99`
  - `canCreate = false`, `canEdit = false`, `canDelete = false` (override inherited to be explicit)
  - `public static function form(Schema $schema): Schema` — delegate to `AuditLogForm::configure()`
  - `public static function table(Table $table): Table` — delegate to `AuditLogsTable::configure()`
  - `public static function getPages(): array` — `['index' => ListAuditLogs::route('/'), 'view' => ViewAuditLog::route('/{record}')]`

### 2.2 Create `AuditLogsTable`

**Path**: `app/Filament/Resources/AdminAudit/Tables/AuditLogsTable.php`

- [x] **Create** `app/Filament/Resources/AdminAudit/Tables/` directory
- [x] **Create** `AuditLogsTable` with static `configure(Table $table): Table`:
  - Columns: user (relation name → 'user.name'), actor_type (badge), action (text), subject_type (text), subject_id (text), ip_address (text), created_at (datetime)
  - Sortable: actor_type, action, subject_type, created_at
  - Default sort: `created_at DESC`
  - Filters:
    - `SelectFilter::make('actor_type')` → options `['admin' => 'Admin', 'doctor' => 'Doctor', 'system' => 'System']`
    - `SelectFilter::make('action')` → keyed to distinct actions in the table (use `getRelationshipCountOptions` or static options: `created`, `updated`, `deleted`, `toggled`, `assigned`)
    - `SelectFilter::make('subject_type')` → keyed to distinct subject types
    - `Filter::make('created_at')` → date range picker

### 2.3 Create `AuditLogForm`

**Path**: `app/Filament/Resources/AdminAudit/Schemas/AuditLogForm.php`

- [x] **Create** `app/Filament/Resources/AdminAudit/Schemas/` directory
- [x] **Create** `AuditLogForm` with static `configure(Schema $schema): Schema`:
  - Read-only informational form showing all fields
  - KeyValue or TextInput fields for: user, actor_type, action, subject_type, subject_id, ip_address, metadata (JSON viewer), created_at
  - All fields `->disabled()` — no editing

### 2.4 Create `ListAuditLogs` page

**Path**: `app/Filament/Resources/AdminAudit/Pages/ListAuditLogs.php`

- [x] **Create** `app/Filament/Resources/AdminAudit/Pages/` directory
- [x] **Create** `ListAuditLogs extends ListRecords`:
  - `protected static string $resource = AuditLogResource::class`
  - No custom logic needed — inherits ListRecords

### 2.5 Create `ViewAuditLog` page

**Path**: `app/Filament/Resources/AdminAudit/Pages/ViewAuditLog.php`

- [x] **Create** `ViewAuditLog extends ViewRecord`:
  - `protected static string $resource = AuditLogResource::class`
  - Custom content: render `metadata` field as a styled JSON `<pre>` block instead of raw JSON text
  - Add a "back" link to the list page

### 2.6 Feature tests for AuditLogResource

**Path**: `tests/Feature/Filament/Resources/AdminAudit/AuditLogResourceTest.php`

- [x] **Create** test file:
  - `it('shows audit log list page')` → `get('/admin/audit-logs')->assertOk()`
  - `it('shows individual audit log view page')` → create an audit log via factory, `get('/admin/audit-logs/{id}')->assertOk()`
  - `it('cannot access create page')` → `get('/admin/audit-logs/create')->assertForbidden()`
  - `it('cannot access edit page')` → `get('/admin/audit-logs/{id}/edit')->assertForbidden()`
  - `it('cannot delete audit log')` → `delete('/admin/audit-logs/{id}')->assertMethodNotAllowed()` (or 403)
  - `it('list page shows actor_type, action, subject columns')` → assert elements present
  - `it('list page is paginated')` → assert pagination controls present

### 2.7 Verify phase 2

- [x] Run `pest tests/Feature/Filament/Resources/AdminAudit/AuditLogResourceTest.php` — green
- [x] Manually verify `/admin/audit-logs` loads in browser
- [x] Verify the metadata JSON renders readably on the view page

---

## Integration (Deferred — separate change)

### 3.1 Wire audit logging into existing admin resources (SKIP — deferred)

- [ ] Add `LogAdminAction` calls to `DoctorResource::afterCreate`, `afterUpdate`, `afterDelete`
- [ ] Add `LogAdminAction` calls to `PatientResource::afterCreate`, `afterUpdate`, `afterDelete`
- [ ] Add `LogAdminAction` calls to `UserResource::afterCreate`, `afterUpdate`, `afterDelete`
- [ ] Add integration tests for each resource's audit logging

> **Deferred**: This is a separate integration task. The foundation (action + resource + boot guard) is complete without it. Wire each admin resource in its own task group to keep review slices small.

---

## Verification Checklist

- [ ] `pest` suite passes with no new failures
- [ ] `AuditLog::boot()` throws on update and delete
- [ ] `LogAdminAction::execute()` creates a row with all required fields
- [ ] `/admin/audit-logs` renders a paginated table
- [ ] `/admin/audit-logs/{id}` renders full metadata
- [ ] Create/Edit/Delete buttons absent from AuditLogResource
- [ ] All 4 spec scenarios covered by tests:
  - [ ] Admin action writes a row → `LogAdminActionTest`
  - [ ] Audit rows have no updated_at → migration already confirmed, optional schema test
  - [ ] Audit rows cannot be deleted → `AuditLogTest`
  - [ ] Audit rows cannot be updated → `AuditLogTest`