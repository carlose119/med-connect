# Design: Doctor Schedule Management

## Technical Approach

Two Filament Resources (`DoctorScheduleResource`, `DoctorScheduleOverrideResource`) following the existing directory-per-resource convention (SpecialtyResource pattern). Full CRUD on both, scoped to the logged-in doctor's records via `getEloquentQuery()`. Admin sees all. Validation via custom Rule classes reusable across Resources and future API endpoints.

## Architecture Decisions

| Decision | Choice | Alternative | Rationale |
|----------|--------|-------------|-----------|
| Resource layout | **Two separate resources** | Single merged resource with tabs | Each model has distinct fields/validation. Follows project's one-resource-per-model (Specialty vs DoctorAppointment). |
| Authz | **Formal Policy classes + query scoping** | Inline role checks in resource | Policies are testable in isolation (AppointmentPolicy pattern). Query scoping prevents data leaks even if policy misconfigured. |
| Cache strategy | **No cache** | Cache generated slots, invalidate on write | Slot gen is fast (2 queries + in-memory). Cache invalidation must detect affected dates. YAGNI. |
| Panel placement | **Doctor panel + admin panel** | Doctor panel only | Both `admin` and `doctor` roles can manage schedules per spec. Admin sees all doctors, doctor sees own. |

## Data Flow

```
Filament Form → Custom Rules (ScheduleDurationPositive, ScheduleEndAfterStart)
  → doctor_schedules / doctor_schedule_overrides (DB write)
  → DoctorAvailabilityService (reads fresh, no cache)
```

Slots are computed on-the-fly: active rules → slice into slots → subtract block overrides → add extra_availability overrides → subtract booked appointments → apply 2h anticipación filter.

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Filament/Resources/DoctorSchedules/DoctorScheduleResource.php` | Create | Resource, form/table wiring, query scoping, nav icon |
| `app/Filament/Resources/DoctorSchedules/Schemas/DoctorScheduleForm.php` | Create | day_of_week select, time pickers, duration input, active toggle |
| `app/Filament/Resources/DoctorSchedules/Tables/DoctorSchedulesTable.php` | Create | Columns + day_of_week / is_active filters |
| `app/Filament/Resources/DoctorSchedules/Pages/ListDoctorSchedules.php` | Create | List with CreateAction header |
| `app/Filament/Resources/DoctorSchedules/Pages/CreateDoctorSchedule.php` | Create | Create form |
| `app/Filament/Resources/DoctorSchedules/Pages/EditDoctorSchedule.php` | Create | Edit + DeleteAction |
| `app/Filament/Resources/DoctorScheduleOverrides/DoctorScheduleOverrideResource.php` | Create | Resource, form/table wiring, query scoping |
| `app/Filament/Resources/DoctorScheduleOverrides/Schemas/DoctorScheduleOverrideForm.php` | Create | Date, type select, nullable times, reason textarea |
| `app/Filament/Resources/DoctorScheduleOverrides/Tables/DoctorScheduleOverridesTable.php` | Create | Columns + type / date range filters |
| `app/Filament/Resources/DoctorScheduleOverrides/Pages/ListDoctorScheduleOverrides.php` | Create | List |
| `app/Filament/Resources/DoctorScheduleOverrides/Pages/CreateDoctorScheduleOverride.php` | Create | Create form |
| `app/Filament/Resources/DoctorScheduleOverrides/Pages/EditDoctorScheduleOverride.php` | Create | Edit + DeleteAction |
| `app/Rules/ScheduleDurationPositive.php` | Create | `$value > 0` validation |
| `app/Rules/ScheduleEndAfterStart.php` | Create | `end_time > start_time` validation |
| `app/Policies/DoctorSchedulePolicy.php` | Create | Admin: all. Doctor: own records only. |
| `app/Policies/DoctorScheduleOverridePolicy.php` | Create | Same shape as above |
| `app/Providers/AppServiceProvider.php` | Modify | Register both policies |

## Key Implementation Details

**Doctor scoping** — Resources override `getEloquentQuery()`:
```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->when(
            auth()->user()->isDoctor(),
            fn (Builder $q) => $q->where('doctor_id', auth()->user()->doctor->id),
        );
}
```

**Validation rules** — Used via named static methods for reuse:
```php
public static function rules(): array
{
    return [
        'slot_duration_minutes' => ['required', 'integer', new ScheduleDurationPositive],
        'end_time' => ['required', new ScheduleEndAfterStart($this->start_time)],
    ];
}
```

**Override times** — `start_time` and `end_time` mapped as nullable in the form (no `->required()`). A `block` override with both null = full-day unavailability.

**No unique constraints** — Multiple overrides per doctor+date allowed (service merges them). Multiple rules per day_of_week allowed (intentional for split shifts like 9-12 + 14-18).

**Times** — Stored as wall-clock (`TIME` columns). Service converts to UTC using consultorio timezone. Document in UI helper text.

## Testing Strategy

| Layer | What | Approach |
|-------|------|----------|
| Unit | ScheduleDurationPositive rule | Values: 0, -1, 1, 30 — test pass/fail boundaries |
| Unit | ScheduleEndAfterStart rule | start<end passes; start=end fails; start>end fails |
| Unit | DoctorSchedulePolicy | Admin=true always; doctor=true own, false others |
| Unit | DoctorScheduleOverridePolicy | Same shape |
| Feature | Create schedule via Filament | Pest Livewire: fill form → assert DB row |
| Feature | Toggle active | Edit → toggle → assert is_active flips |
| Feature | Create block override | Nullable times omitted → assert DB |
| Feature | Delete override | Delete action → assert row removed |
| Feature | 5 spec scenarios | Via DoctorAvailabilityService: inactive rule, block override, non-positive duration rejected, end<start rejected, nullable times accepted |

## Open Questions

- [ ] Admin panel: reuse same resource (admin sees all via query scoping) or build a separate "all schedules" view?
- [ ] `doctor_id` on create: auto-assign from `auth()->user()->doctor->id` (recommended) or expose as a select?
