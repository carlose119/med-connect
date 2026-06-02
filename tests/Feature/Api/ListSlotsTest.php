<?php

use Carbon\CarbonImmutable;
use Database\Factories\DoctorScheduleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 3 — agenda-http — GET /api/doctors/{id}/slots (REQ-API-6 +
 * design §5 #12 + the slot generator at
 * App\Services\DoctorAvailabilityService).
 *
 * RED at this commit: the GET /api/doctors/{doctor}/slots route is
 * not yet registered. All 4 assertions must fail.
 *
 * Once T-API-23 lands (DoctorController@slots + ListSlotsRequest +
 * SlotResource + the route), all 4 scenarios must pass.
 *
 * The slot list comes from DoctorAvailabilityService::slots(),
 * which is a pure function: doctor id + date (+ optional tz).
 */

it('returns the slots for a doctor with a published schedule', function (): void {
    [, $doctor, ] = $this->createDoctorWithToken();
    $date = CarbonImmutable::now()->addDays(3)->startOfDay();
    $dateString = $date->toDateString();

    $response = $this->actingAs($doctor->user, 'sanctum')
        ->getJson("/api/doctors/{$doctor->id}/slots?date={$dateString}");

    $response->assertStatus(200)
        ->assertJsonCount(6, 'data');            // 09:00-12:00, 30min slots = 6 slots

    $data = $response->json('data');
    expect($data[0])->toHaveKey('start_time');
    expect($data[0])->toHaveKey('end_time');
});

it('returns an empty array when the doctor has no schedule for the date', function (): void {
    $user = \App\Models\User::factory()->doctor()->create();
    $specialty = \App\Models\Specialty::firstOrCreate(
        ['slug' => 'general-medicine'],
        ['name' => 'General Medicine', 'is_active' => true],
    );
    $doctor = \App\Models\Doctor::factory()->for($user)->for($specialty)->create();

    $date = CarbonImmutable::now()->addDays(7)->startOfDay();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/doctors/{$doctor->id}/slots?date={$date->toDateString()}");

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('filters out slots that fall inside the 2h anticipation window', function (): void {
    [, $doctor, ] = $this->createDoctorWithToken();
    $dayOfWeek = (int) CarbonImmutable::now()->dayOfWeekIso;

    // Build a schedule starting 30 minutes from now — every slot
    // falls inside the 2h window, so the service filters them all.
    $now = CarbonImmutable::now();
    $startAt = $now->copy()->addMinutes(30)->setTimezone($now->getTimezone()->getName());

    DoctorScheduleFactory::new()
        ->for($doctor)
        ->create([
            'day_of_week' => $dayOfWeek,
            'start_time' => $startAt->toTimeString(),
            'end_time' => $startAt->copy()->addHours(2)->toTimeString(),
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

    $response = $this->actingAs($doctor->user, 'sanctum')
        ->getJson("/api/doctors/{$doctor->id}/slots?date={$now->toDateString()}");

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('returns 422 VALIDATION_ERROR for an invalid date format', function (): void {
    [, $doctor, ] = $this->createDoctorWithToken();

    $response = $this->actingAs($doctor->user, 'sanctum')
        ->getJson("/api/doctors/{$doctor->id}/slots?date=not-a-date");

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});
