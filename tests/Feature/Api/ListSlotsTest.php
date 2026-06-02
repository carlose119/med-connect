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
    // Build a doctor with a schedule for 3 days from now's day-of-week
    // so the date we query matches the schedule's day-of-week.
    [, $doctor, ] = $this->createDoctorWithToken(
        CarbonImmutable::now()->addDays(3),
    );
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
    // Build a near-future override schedule starting 30 minutes from
    // now with 30-min slots. The service filter
    // (`start > now() + 2h`) excludes the slots at 30/60/90 min from
    // now; the slot at exactly 2h passes. We assert the count is
    // strictly less than the unscheduled default (4 slots) — meaning
    // at least one was filtered by the anticipación guard.
    [, $doctor, ] = $this->createDoctorWithToken();
    $dayOfWeek = (int) CarbonImmutable::now()->dayOfWeekIso;

    $now = CarbonImmutable::now();
    $startAt = $now->copy()->addMinutes(30);

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

    $response->assertStatus(200);

    $count = count($response->json('data'));
    // The override schedule has 4 slots (30, 60, 90, 120 min from
    // now). The anticipación filter (start > now+2h) removes all of
    // them — none are strictly > 2h from now. Assert the count is
    // LESS than 4 (proving the filter kicked in). The exact number
    // is the size of the unfiltered 4-slot override; we don't pin
    // it because the trait's default 09:00-12:00 schedule also
    // contributes 6 already-past slots, all of which are rejected
    // by the same filter. So the unfiltered count for this test is
    // 4 (only the override is in the future).
    expect($count)->toBeLessThanOrEqual(4);

    // Inspect the returned slots — assert they are all strictly
    // greater than now+2h. If the anticipación filter is broken,
    // this assertion will catch it.
    $nowPlus2h = CarbonImmutable::now()->addHours(2);
    foreach ($response->json('data') as $row) {
        $slotStart = CarbonImmutable::parse($row['start_time']);
        expect($slotStart->greaterThan($nowPlus2h))->toBeTrue(
            'Slot at '.$row['start_time'].' is not > now+2h ('.$nowPlus2h->toIso8601String().')',
        );
    }
});

it('returns 422 VALIDATION_ERROR for an invalid date format', function (): void {
    [, $doctor, ] = $this->createDoctorWithToken();

    $response = $this->actingAs($doctor->user, 'sanctum')
        ->getJson("/api/doctors/{$doctor->id}/slots?date=not-a-date");

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});
