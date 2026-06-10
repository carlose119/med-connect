<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 2 — prescriptions — API integration tests for the prescriptions
 * endpoint group (store, show, update).
 *
 * These tests exercise the full HTTP stack: route → controller → action
 * → model, including policy authorisation, appointment state guard,
 * and DB constraint handling.
 *
 * Scenarios cover Tasks 2.1, 2.2, 2.3, and 2.4.
 */

// ─── Helpers ──────────────────────────────────────────────────────

/**
 * Build a completed appointment for a given doctor and patient.
 *
 * @return array{0: Appointment, 1: Doctor, 2: User}
 */
function buildCompletedAppointment(User $doctorUser, Doctor $doctor, \App\Models\Patient $patient): array
{
    $appointment = Appointment::factory()
        ->for($doctor)
        ->for($patient)
        ->create([
            'state' => 'completed',
            'start_time' => CarbonImmutable::now()->subDay()->setTime(10, 0),
            'end_time' => CarbonImmutable::now()->subDay()->setTime(10, 30),
        ]);

    return [$appointment, $doctor, $doctorUser];
}

// ─── Task 2.1 + 2.2: POST /api/prescriptions ─────────────────────

describe('POST /api/prescriptions', function (): void {
    it('creates a prescription with items as 201', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/prescriptions', [
                'appointment_id' => $appointment->id,
                'items' => [
                    ['name' => 'Ibuprofen', 'dosage' => '400mg', 'frequency' => 'cada 8h', 'duration' => '5 días'],
                    ['name' => 'Paracetamol', 'dosage' => '500mg', 'frequency' => 'cada 6h', 'duration' => '3 días'],
                    ['name' => 'Omeprazole', 'dosage' => '20mg', 'frequency' => '1 vez al día', 'duration' => '14 días'],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'appointment_id',
                    'doctor',
                    'patient_id',
                    'unique_code',
                    'issued_at',
                    'status',
                    'cancellation_reason',
                    'items' => [
                        '*' => ['id', 'name', 'dosage', 'frequency', 'duration', 'position'],
                    ],
                ],
            ])
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cancellation_reason', null)
            ->assertJsonCount(3, 'data.items');

        // DB: 1 prescription + 3 items
        expect(Prescription::count())->toBe(1);
        expect(PrescriptionItem::count())->toBe(3);
    });

    it('assigns positions to items in array order', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/prescriptions', [
                'appointment_id' => $appointment->id,
                'items' => [
                    ['name' => 'Item A', 'dosage' => '100mg', 'frequency' => 'daily', 'duration' => '7 days'],
                    ['name' => 'Item B', 'dosage' => '200mg', 'frequency' => 'bid', 'duration' => '5 days'],
                    ['name' => 'Item C', 'dosage' => '300mg', 'frequency' => 'tid', 'duration' => '3 days'],
                ],
            ]);

        $response->assertStatus(201);

        $prescription = Prescription::first();
        $items = $prescription->items()->orderBy('position')->get();

        expect($items[0]->name)->toBe('Item A');
        expect($items[0]->position)->toBe(1);
        expect($items[1]->name)->toBe('Item B');
        expect($items[1]->position)->toBe(2);
        expect($items[2]->name)->toBe('Item C');
        expect($items[2]->position)->toBe(3);
    });

    it('returns 422 when appointment is not completed', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();

        $pendingAppointment = Appointment::factory()
            ->for($doctor)
            ->for($patient)
            ->create([
                'state' => 'pending',
                'start_time' => CarbonImmutable::now()->addDay()->setTime(10, 0),
                'end_time' => CarbonImmutable::now()->addDay()->setTime(10, 30),
            ]);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/prescriptions', [
                'appointment_id' => $pendingAppointment->id,
                'items' => [
                    ['name' => 'Amoxicillin', 'dosage' => '250mg', 'frequency' => 'tid', 'duration' => '7 days'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    });

    it('returns 403 when doctor does not own the appointment', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();

        // Another doctor
        $otherDoctorUser = User::factory()->doctor()->create();
        $otherDoctor = Doctor::factory()->for($otherDoctorUser)->create();

        $appointment = Appointment::factory()
            ->for($otherDoctor)
            ->for($patient)
            ->create([
                'state' => 'completed',
                'start_time' => CarbonImmutable::now()->subDay()->setTime(11, 0),
                'end_time' => CarbonImmutable::now()->subDay()->setTime(11, 30),
            ]);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/prescriptions', [
                'appointment_id' => $appointment->id,
                'items' => [
                    ['name' => 'Drug', 'dosage' => '100mg', 'frequency' => 'daily', 'duration' => '7 days'],
                ],
            ]);

        $response->assertStatus(403);
    });

    it('returns 422 VALIDATION_ERROR when items are missing', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/prescriptions', [
                'appointment_id' => $appointment->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.items.0', 'The items field is required.');
    });

    it('returns 422 VALIDATION_ERROR when items array is empty', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/prescriptions', [
                'appointment_id' => $appointment->id,
                'items' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    });

    it('returns 401 when unauthenticated', function (): void {
        $response = $this->postJson('/api/prescriptions', [
            'appointment_id' => 1,
            'items' => [['name' => 'Drug', 'dosage' => '100mg', 'frequency' => 'daily', 'duration' => '7 days']],
        ]);

        $response->assertStatus(401);
    });
});

// ─── Task 2.1 + 2.2: GET /api/prescriptions/{prescription} ───────

describe('GET /api/prescriptions/{prescription}', function (): void {
    it('returns 200 with nested items for an authorised doctor', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $prescription = Prescription::factory()
            ->for($appointment)
            ->for($doctor)
            ->for($patient)
            ->create(['status' => 'active']);

        PrescriptionItem::factory()->for($prescription)->create([
            'name' => 'Item One',
            'position' => 1,
        ]);
        PrescriptionItem::factory()->for($prescription)->create([
            'name' => 'Item Two',
            'position' => 2,
        ]);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->getJson("/api/prescriptions/{$prescription->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $prescription->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(2, 'data.items');
    });

    it('returns 403 for a doctor viewing another doctor prescription', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();

        $otherDoctorUser = User::factory()->doctor()->create();
        $otherDoctor = Doctor::factory()->for($otherDoctorUser)->create();

        $appointment = Appointment::factory()
            ->for($otherDoctor)
            ->for($patient)
            ->create(['state' => 'completed']);

        $prescription = Prescription::factory()
            ->for($appointment)
            ->for($otherDoctor)
            ->for($patient)
            ->create();

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->getJson("/api/prescriptions/{$prescription->id}");

        $response->assertStatus(403);
    });

    it('returns 403 for a patient viewing another patient prescription', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $prescription = Prescription::factory()
            ->for($appointment)
            ->for($doctor)
            ->for($patient)
            ->create();

        // Another patient
        [$otherPatientUser, $otherPatient] = $this->createPatientWithToken();

        $response = $this->actingAs($otherPatientUser, 'sanctum')
            ->getJson("/api/prescriptions/{$prescription->id}");

        $response->assertStatus(403);
    });

    it('allows patient to view their own prescription', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $prescription = Prescription::factory()
            ->for($appointment)
            ->for($doctor)
            ->for($patient)
            ->create();

        $response = $this->actingAs($patientUser, 'sanctum')
            ->getJson("/api/prescriptions/{$prescription->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $prescription->id);
    });
});

// ─── Task 2.1 + 2.2: PUT /api/prescriptions/{prescription} ─────────

describe('PUT /api/prescriptions/{prescription}', function (): void {
    it('cancels a prescription with 200', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $prescription = Prescription::factory()
            ->for($appointment)
            ->for($doctor)
            ->for($patient)
            ->create(['status' => 'active']);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->putJson("/api/prescriptions/{$prescription->id}", [
                'status' => 'cancelled',
                'cancellation_reason' => 'Patient reported adverse reaction.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Patient reported adverse reaction.');
    });

    it('returns 422 when cancellation_reason is missing', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $prescription = Prescription::factory()
            ->for($appointment)
            ->for($doctor)
            ->for($patient)
            ->create(['status' => 'active']);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->putJson("/api/prescriptions/{$prescription->id}", [
                'status' => 'cancelled',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    });

    it('returns 403 when doctor tries to cancel another doctor prescription', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();

        $otherDoctorUser = User::factory()->doctor()->create();
        $otherDoctor = Doctor::factory()->for($otherDoctorUser)->create();

        $appointment = Appointment::factory()
            ->for($otherDoctor)
            ->for($patient)
            ->create(['state' => 'completed']);

        $prescription = Prescription::factory()
            ->for($appointment)
            ->for($otherDoctor)
            ->for($patient)
            ->create(['status' => 'active']);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->putJson("/api/prescriptions/{$prescription->id}", [
                'status' => 'cancelled',
                'cancellation_reason' => 'Reason',
            ]);

        $response->assertStatus(403);
    });
});

// ─── Task 2.5: Unique code collision → 409 ───────────────────────

describe('Unique code collision', function (): void {
    it('proves the unique constraint exists at DB level by direct insert', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        // Create first prescription via API (generates unique code)
        $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/prescriptions', [
                'appointment_id' => $appointment->id,
                'items' => [['name' => 'Drug A', 'dosage' => '100mg', 'frequency' => 'daily', 'duration' => '7 days']],
            ])
            ->assertStatus(201);

        $existingCode = Prescription::first()->unique_code;

        // Insert a second prescription directly with the SAME unique_code,
        // bypassing the code generator. This proves the DB-level unique
        // constraint exists and rejects duplicates.
        $caught = false;
        try {
            \Illuminate\Support\Facades\DB::table('prescriptions')->insert([
                'appointment_id' => $appointment->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
                'unique_code' => $existingCode,
                'issued_at' => now()->toDateTimeString(),
                'status' => 'active',
                'cancellation_reason' => null,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            $caught = true;
            // SQLite: code 19, PostgreSQL: 23505, MySQL: 1062
            $isUniqueViolation = in_array($e->errorInfo[1] ?? 0, [19, 23505, 1062], true);
            expect($isUniqueViolation)->toBeTrue('Expected unique constraint violation, got: ' . $e->getMessage());
        }

        expect($caught)->toBeTrue('Expected QueryException for duplicate unique_code');
    });

    it('generates unique codes (no collision across 10 prescriptions)', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();

        $codes = [];
        $baseTime = CarbonImmutable::now()->addMinutes(30); // start 30min from now

        for ($i = 0; $i < 10; $i++) {
            $start = $baseTime->copy()->addMinutes($i * 60); // each appointment 1h apart
            $end = $start->copy()->addMinutes(30);

            $appt = Appointment::factory()
                ->for($doctor)
                ->for($patient)
                ->create([
                    'state' => 'completed',
                    'start_time' => $start,
                    'end_time' => $end,
                ]);

            $response = $this->actingAs($doctorUser, 'sanctum')
                ->postJson('/api/prescriptions', [
                    'appointment_id' => $appt->id,
                    'items' => [['name' => "Drug {$i}", 'dosage' => '100mg', 'frequency' => 'daily', 'duration' => '7 days']],
                ]);

            $response->assertStatus(201);
            $codes[] = $response->json('data.unique_code');
        }

        // All 10 codes must be unique
        expect(count(array_unique($codes)))->toBe(10);
    });
});

// ─── Task 2.3: Items ordered by position in GET response ───────────

describe('Items ordered by position in API response', function (): void {
    it('returns items in correct position order', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson('/api/prescriptions', [
                'appointment_id' => $appointment->id,
                'items' => [
                    ['name' => 'First', 'dosage' => '100mg', 'frequency' => 'daily', 'duration' => '7 days'],
                    ['name' => 'Second', 'dosage' => '200mg', 'frequency' => 'bid', 'duration' => '5 days'],
                    ['name' => 'Third', 'dosage' => '300mg', 'frequency' => 'tid', 'duration' => '3 days'],
                ],
            ]);

        $response->assertStatus(201);

        $prescription = Prescription::first();
        $items = $prescription->items()->orderBy('position')->get();

        expect($items[0]->position)->toBe(1);
        expect($items[1]->position)->toBe(2);
        expect($items[2]->position)->toBe(3);
    });
});

// ─── Task 2.1: GET /api/prescriptions index includes items ─────────

describe('GET /api/prescriptions includes items', function (): void {
    it('index returns prescriptions with items eager-loaded', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor] = $this->createDoctorWithToken();
        [$appointment] = buildCompletedAppointment($doctorUser, $doctor, $patient);

        $prescription = Prescription::factory()
            ->for($appointment)
            ->for($doctor)
            ->for($patient)
            ->create(['status' => 'active']);

        PrescriptionItem::factory()->for($prescription)->create(['name' => 'Item X', 'position' => 1]);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->getJson('/api/prescriptions');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.items');
    });
});