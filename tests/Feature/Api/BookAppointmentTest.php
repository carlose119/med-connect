<?php

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 2 — agenda-http — POST /api/appointments (REQ-API-2 + REQ-API-7
 * + REQ-API-8 + REQ-API-9 + the booking contract from
 * agenda-core/booking/spec.md).
 *
 * RED at this commit: the POST /api/appointments route does not
 * exist yet, so every scenario returns 404 (with error.code =
 * ROUTE_NOT_FOUND per the PR 1 exception handler). All 4 assertions
 * must fail.
 *
 * Once T-API-11 lands (AppointmentController@store +
 * BookAppointmentRequest + AppointmentResource + the route), all 4
 * scenarios must pass.
 */

beforeEach(function (): void {
    // Create a doctor + a published schedule for "now + 3 days at 10:00".
    // The 3-day buffer guarantees the anticipation check (2h minimum)
    // passes trivially.
    [$this->doctorUser, $this->doctor, ] = $this->createDoctorWithToken(
        CarbonImmutable::now()->addDays(3),
    );

    // The doctor's schedule was seeded by the trait for
    // `now()->addDays(3)->dayOfWeekIso` with a 09:00-12:00 window in
    // 30-minute slots. Build the canonical "10:00 local" start that
    // the trait schedules guarantee.
    $this->targetDay = CarbonImmutable::now()->addDays(3)->setTime(10, 0);
});

it('returns 201 with the appointment resource on the happy path', function (): void {
    [$user, $patient, ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $this->targetDay->toIso8601String(),
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.state', 'pending')
        ->assertJsonPath('data.doctor_id', $this->doctor->id)
        ->assertJsonPath('data.patient_id', $patient->id)
        ->assertJsonStructure(['data' => ['id', 'doctor_id', 'patient_id', 'state', 'start_time', 'end_time']]);

    // The DB must hold exactly one appointment for (doctor, patient).
    expect(Appointment::query()
        ->where('doctor_id', $this->doctor->id)
        ->where('patient_id', $patient->id)
        ->count())->toBe(1);
});

it('returns 422 VALIDATION_ERROR when doctor_id does not exist', function (): void {
    [$user, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => 99999,
            'start_time' => $this->targetDay->toIso8601String(),
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    $details = $response->json('error.details');
    expect($details)->toBeArray();
    expect($details)->toHaveKey('doctor_id');
    expect($details['doctor_id'])->toBeArray();
});

it('returns 409 SLOT_NOT_AVAILABLE when the slot is already booked', function (): void {
    // First booking as a patient — succeeds and creates a row that
    // blocks the slot. The patient actor is the "self-service" path
    // so the controller resolves patient_id from $user->patient->id
    // without needing a `patient_id` in the body.
    [$firstUser, , ] = $this->createPatientWithToken();
    $this->actingAs($firstUser, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $this->targetDay->toIso8601String(),
        ])->assertStatus(201);

    // Second booking (different patient, same slot) must collide via
    // the slot-lookup guard in BookAppointmentAction.
    [$user, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $this->targetDay->toIso8601String(),
        ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'SLOT_NOT_AVAILABLE');
});

it('returns 422 ANTICIPATION_WINDOW_VIOLATION when start_time is inside the 2h window', function (): void {
    [$user, , ] = $this->createPatientWithToken();

    // The action's 2nd guard is defense-in-depth: the slot service
    // already filters out slots inside the 2h window, so a request
    // posting "now+1h" is normally caught by the 1st guard with 409
    // (slot not in the published list). To exercise the 2nd guard
    // through the HTTP layer we bind a fake service that returns
    // the requested slot even though it is inside the 2h window —
    // same pattern as tests/Feature/Agenda/BookAppointmentFailureTest.
    $nearFuture = CarbonImmutable::now()->addMinutes(90)->startOfMinute();
    $nearFutureUtc = $nearFuture->copy()->setTimezone('UTC');

    $fakeService = new class($nearFutureUtc) extends \App\Services\DoctorAvailabilityService
    {
        public function __construct(private readonly \Carbon\CarbonInterface $slot) {}

        public function slots(int $doctorId, \Carbon\CarbonInterface $date, ?string $tz = null): array
        {
            return [[
                'start' => $this->slot->copy()->toImmutable(),
                'end' => $this->slot->copy()->addMinutes(30)->toImmutable(),
            ]];
        }
    };

    app()->instance(\App\Services\DoctorAvailabilityService::class, $fakeService);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $nearFuture->toIso8601String(),
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'ANTICIPATION_WINDOW_VIOLATION');
});

/**
 * Coverage delta — agenda-test-coverage (item 6 + item 9, both
 * REQ-API-5 §3 and REQ-API-7 §5).
 *
 * Item 6 (PASSES on first run): The controller's store() method
 * (app/Http/Controllers/Api/AppointmentController.php lines 116-119)
 * already does the `tz->toUtc(...)` conversion. The new scenario
 * pins the contract: the wire body is interpreted in the resolved
 * TZ, but the stored column is UTC.
 *
 * Item 9 (FAILS at this commit, will be fixed at T-COV-10): The
 * current BookAppointmentRequest::authorize()
 * (app/Http/Requests/Api/BookAppointmentRequest.php lines 31-43)
 * returns true for admin + doctor + patient. The spec (REQ-API-7
 * §5) says only patients can book via the API. The new scenarios
 * assert 403 FORBIDDEN for admin and doctor actors.
 */
it('interprets the write body in the resolved TZ and stores UTC', function (): void {
    [$user, $patient, ] = $this->createPatientWithToken();

    // Wire body: 07:00 AR (-03:00) on the target day, ?tz=AR.
    // The default schedule is 09:00-12:00 in app.timezone (UTC);
    // 07:00 AR == 10:00 UTC, which is inside the published window.
    // The conversion path being pinned: wire body in AR → stored
    // value in UTC. (We picked 07:00 AR instead of 10:00 AR so the
    // slot-lookup guard passes — 10:00 AR == 13:00 UTC, which is
    // outside the default 09:00-12:00 UTC window.)
    $localStart = $this->targetDay->setTimezone('America/Argentina/Buenos_Aires')->setTime(7, 0);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/appointments?tz=America/Argentina/Buenos_Aires', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $localStart->toIso8601String(),
        ]);

    $response->assertStatus(201);

    // The wire response must echo the local time with -03:00.
    $response->assertJsonPath('data.start_time', $localStart->toIso8601String());

    // The DB column must hold the UTC equivalent (07:00 AR == 10:00 UTC).
    $appointment = Appointment::query()
        ->where('doctor_id', $this->doctor->id)
        ->where('patient_id', $patient->id)
        ->latest('id')
        ->first();
    expect($appointment)->not->toBeNull();

    $rawStart = $appointment->getAttributes()['start_time'];
    expect($rawStart)->toBe($localStart->copy()->setTimezone('UTC')->toDateTimeString());
});

it('returns 403 FORBIDDEN for a non-patient actor (admin)', function (): void {
    $admin = \App\Models\User::factory()->admin()->create();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $this->targetDay->toIso8601String(),
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('returns 403 FORBIDDEN for a non-patient actor (doctor)', function (): void {
    // A SECOND doctor (not the doctor whose slot we're booking)
    // so the actor is unambiguously a doctor, not the patient.
    [, $otherDoctor, ] = $this->createDoctorWithToken();

    $response = $this->actingAs($otherDoctor->user, 'sanctum')
        ->postJson('/api/appointments', [
            'doctor_id' => $this->doctor->id,
            'start_time' => $this->targetDay->toIso8601String(),
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});
