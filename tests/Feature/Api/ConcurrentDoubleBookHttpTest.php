<?php

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 2 — agenda-http — HTTP-layer concurrency contract for POST
 * /api/appointments (REQ-CONC-1 + design §11 risk N2).
 *
 * Pins the behaviour that two clients racing for the same slot
 * always result in one 201 + one 409, never two 201s. The test is
 * a "race-verify" (per the strict TDD exception documented in
 * tasks.md T-API-14): no production code is added in this commit.
 * The persistence contract (the unique partial index from
 * agenda-core PR 2) and the exception handler (T-API-6) are
 * already in place; this test just pins their combined emergent
 * behaviour at the HTTP boundary.
 *
 * MariaDB-only: SQLite serialises requests inside a single
 * process, so the race window cannot be reproduced. The test
 * self-skips on non-mariadb drivers with a clear "MariaDB-only"
 * message — same pattern as the action-layer
 * tests/Feature/Agenda/ConcurrentDoubleBookTest.php.
 *
 * Concurrency simulation: Laravel doesn't fork inside a single
 * test process on Windows. The test issues the two POSTs serially
 * but seeds a pre-existing appointment at the target slot so the
 * second POST collides via the action's 1st-guard slot lookup.
 * The persistence contract (unique index) is what we care about;
 * the HTTP race is a consequence of that contract.
 */
it('two concurrent POST /api/appointments for the same slot return 201 + 409 on the real DB driver', function (): void {
    $driver = Config::get('database.default');

    if (! in_array($driver, ['mariadb', 'mysql'], true)) {
        $this->markTestSkipped(sprintf(
            'HTTP race test requires a real DB driver (mariadb/mysql); current driver is %s. '
            .'Run with DB_CONNECTION=mariadb to exercise this scenario.',
            $driver,
        ));
    }

    // Doctor + schedule for a target day with one published slot.
    [, $doctor] = $this->createDoctorWithToken(
        CarbonImmutable::now()->addDays(5),
    );

    $targetDate = CarbonImmutable::now()->addDays(5)->setTime(10, 0);

    // Two distinct patients, each with a Sanctum token.
    [$firstUser] = $this->createPatientWithToken();
    [, $secondPatient] = $this->createPatientWithToken();

    // First booking via the action — guarantees a row exists for
    // the (doctor, start_time) when the second HTTP request hits.
    // The action's lockForUpdate + slot lookup short-circuit the
    // race for the test path; the HTTP race is the "concurrent
    // client" use case this test pins.
    $firstResponse = $this->actingAs($firstUser, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'start_time' => $targetDate->toIso8601String(),
        ]);
    $firstResponse->assertStatus(201);

    $winningId = $firstResponse->json('data.id');
    expect($winningId)->toBeInt();

    // Second booking (different patient, same slot) — must collide
    // via the action's slot-lookup guard. Returns 409
    // SLOT_NOT_AVAILABLE.
    $secondUser = $secondPatient->user;
    $secondResponse = $this->actingAs($secondUser, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'start_time' => $targetDate->toIso8601String(),
        ]);
    $secondResponse->assertStatus(409)
        ->assertJsonPath('error.code', 'SLOT_NOT_AVAILABLE');

    // The DB must hold exactly ONE non-cancelled appointment for
    // (doctor_id, start_time). The unique partial index is the
    // persistence safety net behind the action's lockForUpdate.
    $count = Appointment::query()
        ->where('doctor_id', $doctor->id)
        ->where('start_time', $targetDate->copy()->setTimezone('UTC'))
        ->where('state', '!=', 'cancelled')
        ->count();
    expect($count)->toBe(1);
});

/**
 * Coverage delta — agenda-test-coverage (item 12, REQ-CONC-HTTP-1 §2).
 *
 * The losing 409 response includes the conflicting appointment id
 * under error.details.conflicting_appointment_id. The HTTP client
 * (mobile or web) uses the id to fetch the winning appointment
 * and surface "this slot is taken" UX without a second round-trip.
 *
 * RED at this commit: ErrorResponse::resolve() for DomainException
 * (app/Http/Responses/Api/ErrorResponse.php lines 111-118) returns
 * null details, so error.details is missing entirely from the
 * payload (the if ($details !== null) guard on line 42 of
 * ErrorResponse::fromException drops the field). The assertion on
 * error.details.conflicting_appointment_id will fail.
 *
 * The GREEN commit (T-COV-16) extends SlotNotAvailableException
 * with a `withConflict(int $id)` static factory + a getter, makes
 * BookAppointmentAction call withConflict() when the slot-lookup
 * guard fires, and adds the conflicting_appointment_id field to
 * ErrorResponse::resolve() for SlotNotAvailableException specifically.
 */
it('includes conflicting_appointment_id in the 409 details', function (): void {
    $driver = Config::get('database.default');

    if (! in_array($driver, ['mariadb', 'mysql'], true)) {
        $this->markTestSkipped(sprintf(
            'conflicting_appointment_id race test requires a real DB driver (mariadb/mysql); current driver is %s. '
            .'Run with DB_CONNECTION=mariadb to exercise this scenario.',
            $driver,
        ));
    }

    [, $doctor] = $this->createDoctorWithToken(
        CarbonImmutable::now()->addDays(5),
    );

    $targetDate = CarbonImmutable::now()->addDays(5)->setTime(10, 0);

    [$firstUser] = $this->createPatientWithToken();
    [, $secondPatient] = $this->createPatientWithToken();

    $firstResponse = $this->actingAs($firstUser, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'start_time' => $targetDate->toIso8601String(),
        ]);
    $firstResponse->assertStatus(201);

    $winningId = $firstResponse->json('data.id');
    expect($winningId)->toBeInt();

    $secondResponse = $this->actingAs($secondPatient->user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $doctor->id,
            'start_time' => $targetDate->toIso8601String(),
        ]);

    // 409 + error.code = SLOT_NOT_AVAILABLE (existing contract) + the
    // new field under error.details.conflicting_appointment_id.
    $secondResponse->assertStatus(409)
        ->assertJsonPath('error.code', 'SLOT_NOT_AVAILABLE')
        ->assertJsonPath('error.details.conflicting_appointment_id', $winningId);
});
