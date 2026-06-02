<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * Coverage delta — agenda-test-coverage (item 5, REQ-API-5 §1).
 *
 * Locks the default-timezone behaviour: when no `?tz=` query param
 * is supplied, the per-request resolved timezone falls back to
 * `config('clinic.timezone')` (default `America/Argentina/Buenos_Aires`).
 *
 * Endpoint choice: GET /api/doctors/{id}/slots renders datetimes via
 * SlotResource, which formats the slot's `start_time` and `end_time`
 * using the resolved TZ. /api/auth/me was rejected because it
 * doesn't render datetimes.
 *
 * The ResolveTimezone middleware (app/Http/Middleware/ResolveTimezone.php
 * lines 32-35) already implements the fallback. RED is "test does
 * not exist yet" — the new scenario passes on first run. TDD
 * exception documented at T-COV-8.
 */
it('defaults to clinic.timezone when ?tz= is omitted', function (): void {
    config(['clinic.timezone' => 'America/Argentina/Buenos_Aires']);

    // Build a doctor with a published schedule for 3 days from now
    // (same pattern as ListSlotsTest).
    [, $doctor, ] = $this->createDoctorWithToken(
        CarbonImmutable::now()->addDays(3),
    );
    $date = CarbonImmutable::now()->addDays(3)->startOfDay();
    $dateString = $date->toDateString();

    // No ?tz= query — ResolveTimezone should fall back to
    // config('clinic.timezone') = America/Argentina/Buenos_Aires,
    // which has offset -03:00 (no DST in this zone in June 2026).
    $response = $this->actingAs($doctor->user, 'sanctum')
        ->getJson("/api/doctors/{$doctor->id}/slots?date={$dateString}");

    $response->assertStatus(200)
        ->assertJsonCount(6, 'data');                // 09:00-12:00, 30min slots

    $firstStart = $response->json('data.0.start_time');
    expect($firstStart)->toBeString();
    expect($firstStart)->toEndWith('-03:00');        // AR offset (no DST in June)
});
