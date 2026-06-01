# Doctor Schedule

## Purpose
The doctor schedule defines when a doctor is available. It combines recurring weekly rules with point-in-time overrides that add or remove availability on a specific date.

## ADDED Requirements

### Requirement: Recurring Schedule Rules
The system MUST allow a doctor to publish recurring schedule rules binding `day_of_week`, `start_time`, `end_time`, and `slot_duration_minutes`. A rule contributes to slot generation only when `active = true`.

#### Scenario: Active recurring rule produces slots
- **Given** a doctor with an active recurring rule for `tuesday` from `09:00` to `12:00` with `slot_duration_minutes = 30`
- **When** the availability service is queried for any Tuesday
- **Then** the result contains `09:00`, `09:30`, `10:00`, `10:30`, `11:00`, `11:30`

#### Scenario: Inactive recurring rule produces no slots
- **Given** a doctor with a recurring rule for `wednesday` from `09:00` to `12:00` marked `active = false`
- **When** the availability service is queried for any Wednesday
- **Then** the result does not contain any slot from that rule

### Requirement: Schedule Overrides
The system MUST allow point-in-time overrides for a specific date. An override is `block` (removes availability) or `extra_availability` (adds availability), applied on top of the recurring rules for that date.

#### Scenario: block override excludes a time range
- **Given** a recurring rule producing `09:00`, `09:30`, `10:00` on the target day and a `block` override covering `09:15` to `09:45`
- **When** the availability service is queried for the target day
- **Then** the result contains only `09:00` and `10:00`

#### Scenario: extra_availability override adds a slot
- **Given** a doctor with no recurring rule covering `15:00` on the target day and an `extra_availability` override adding `15:00` to `15:30`
- **When** the availability service is queried for the target day
- **Then** the result contains `15:00`

### Requirement: Schedule Validation
The system MUST validate every recurring rule and override at write time. A recurring rule with `end_time <= start_time` or `slot_duration_minutes <= 0` MUST be rejected. An override MUST allow nullable `start_time` and `end_time` to represent a full-day entry.

#### Scenario: Recurring rule with non-positive duration is rejected
- **Given** an attempt to create a recurring rule with `slot_duration_minutes = 0`
- **When** the rule is saved
- **Then** validation fails and no row is persisted

#### Scenario: Recurring rule with end before start is rejected
- **Given** an attempt to create a recurring rule with `start_time = 12:00` and `end_time = 09:00`
- **When** the rule is saved
- **Then** validation fails and no row is persisted

#### Scenario: Override times are nullable for a full-day entry
- **Given** an attempt to create a `block` override for the whole day with `start_time = null` and `end_time = null`
- **When** the override is saved
- **Then** validation succeeds and the override removes availability for the entire day
