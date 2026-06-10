<?php

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Models\DoctorScheduleOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Task 5.8: Authorization — cross-doctor access ─────────────────

// ─── Schedules ──────────────────────────────────────────────────────

it('prevents a doctor from editing another doctors schedule', function (): void {
    // Doctor A creates a schedule.
    $doctorAUser = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->for($doctorAUser)->create();
    $scheduleA = DoctorSchedule::factory()->for($doctorA)->create();

    // Doctor B tries to access it.
    $doctorBUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorBUser)->create();

    $this->actingAs($doctorBUser)
        ->get("/doctor/doctor-schedules/{$scheduleA->id}/edit")
        ->assertNotFound();
});

it('lets a doctor edit their own schedule', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $schedule = DoctorSchedule::factory()->for($doctor)->create();

    $this->actingAs($doctorUser)
        ->get("/doctor/doctor-schedules/{$schedule->id}/edit")
        ->assertSuccessful();
});

it('lets an admin edit any doctors schedule', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $schedule = DoctorSchedule::factory()->for($doctor)->create();

    $admin = User::factory()->admin()->create();

    // Admin accesses via the admin panel path (not the doctor panel).
    $this->actingAs($admin)
        ->get("/admin/doctor-schedules/{$schedule->id}/edit")
        ->assertSuccessful();
});

// ─── Overrides ──────────────────────────────────────────────────────

it('prevents a doctor from editing another doctors override', function (): void {
    $doctorAUser = User::factory()->doctor()->create();
    $doctorA = Doctor::factory()->for($doctorAUser)->create();
    $overrideA = DoctorScheduleOverride::factory()->for($doctorA)->create();

    $doctorBUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorBUser)->create();

    $this->actingAs($doctorBUser)
        ->get("/doctor/doctor-schedule-overrides/{$overrideA->id}/edit")
        ->assertNotFound();
});

it('lets a doctor edit their own override', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $override = DoctorScheduleOverride::factory()->for($doctor)->create();

    $this->actingAs($doctorUser)
        ->get("/doctor/doctor-schedule-overrides/{$override->id}/edit")
        ->assertSuccessful();
});

it('lets an admin edit any doctors override', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $override = DoctorScheduleOverride::factory()->for($doctor)->create();

    $admin = User::factory()->admin()->create();

    // Admin accesses via the admin panel path.
    $this->actingAs($admin)
        ->get("/admin/doctor-schedule-overrides/{$override->id}/edit")
        ->assertSuccessful();
});
