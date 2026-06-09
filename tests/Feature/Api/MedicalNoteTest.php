<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 1 — clinical-records — API integration tests for MedicalNotes.
 *
 * These tests exercise the full HTTP stack: route → controller → action
 * → model, including the append-only guard and policy authorisation.
 *
 * Scenarios cover Tasks 4.1, 4.2, 4.3, and 4.4.
 */

// ─── Helpers ──────────────────────────────────────────────────────

/**
 * Create a doctor-user + doctor profile + a completed appointment with
 * the given patient + a medical history for the patient.  Returns
 * [doctorUser, doctor, history].
 *
 * @return array{0: User, 1: Doctor, 2: MedicalHistory}
 */
function createDoctorWithAppointment(Patient $patient): array
{
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->create();

    Appointment::factory()
        ->for($doctor)
        ->for($patient)
        ->create([
            'state' => 'completed',
            'start_time' => CarbonImmutable::now()->subDay()->setTime(10, 0),
            'end_time' => CarbonImmutable::now()->subDay()->setTime(10, 30),
        ]);

    return [$doctorUser, $doctor, $history];
}

/**
 * Create a doctor-user + doctor profile with NO appointments.
 * Returns [doctorUser, doctor].
 *
 * @return array{0: User, 1: Doctor}
 */
function createDoctorWithoutAppointment(): array
{
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();

    return [$doctorUser, $doctor];
}

// ─── Task 4.1: POST create note ───────────────────────────────────

describe('POST /api/medical-histories/{history}/notes', function (): void {
    it('creates a note with 201 when doctor has a completed appointment', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history] = createDoctorWithAppointment($patient);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson("/api/medical-histories/{$history->id}/notes", [
                'symptoms' => 'Fever and cough',
                'physical_exam' => 'Temperature 39°C',
                'diagnosis' => 'Seasonal flu',
                'treatment_notes' => 'Antipyretics and rest',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.medical_history_id', $history->id)
            ->assertJsonPath('data.diagnosis', 'Seasonal flu')
            ->assertJsonPath('data.symptoms', 'Fever and cough')
            ->assertJsonStructure(['data' => ['id', 'medical_history_id', 'doctor', 'symptoms', 'physical_exam', 'diagnosis', 'treatment_notes', 'corrects_note_id', 'created_at']]);

        // Verify the note was persisted
        expect(MedicalNote::where('medical_history_id', $history->id)->count())->toBe(1);
    });

    it('returns 403 when a doctor without an appointment tries to create a note', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        $history = MedicalHistory::factory()->for($patient)->create();
        [$unauthorizedDoctorUser] = createDoctorWithoutAppointment();

        $response = $this->actingAs($unauthorizedDoctorUser, 'sanctum')
            ->postJson("/api/medical-histories/{$history->id}/notes", [
                'diagnosis' => 'Unauthorized diagnosis',
            ]);

        $response->assertStatus(403);
    });

    it('returns 422 VALIDATION_ERROR when diagnosis is missing', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history] = createDoctorWithAppointment($patient);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson("/api/medical-histories/{$history->id}/notes", [
                'symptoms' => 'Cough',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    });
});

// ─── Task 4.2: POST amend note ────────────────────────────────────

describe('POST /api/medical-notes/{note}/amend', function (): void {
    it('creates an amendment with corrects_note_id, original unchanged', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history] = createDoctorWithAppointment($patient);

        // Create the original note first
        $original = MedicalNote::create([
            'medical_history_id' => $history->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Initial diagnosis',
        ]);

        // Amend it
        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson("/api/medical-notes/{$original->id}/amend", [
                'diagnosis' => 'Corrected diagnosis',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.corrects_note_id', $original->id)
            ->assertJsonPath('data.diagnosis', 'Corrected diagnosis');

        // Original must be untouched
        $original->refresh();
        expect($original->diagnosis)->toBe('Initial diagnosis');
    });

    it('returns 403 when a patient tries to amend a note', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history] = createDoctorWithAppointment($patient);

        $note = MedicalNote::create([
            'medical_history_id' => $history->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Diagnosis',
        ]);

        // Patient tries to amend — should be forbidden
        $response = $this->actingAs($patientUser, 'sanctum')
            ->postJson("/api/medical-notes/{$note->id}/amend", [
                'diagnosis' => 'Patient amendment',
            ]);

        $response->assertStatus(403);
    });
});

// ─── Task 4.3: GET list notes + show note ─────────────────────────

describe('GET /api/medical-histories/{history}/notes', function (): void {
    it('returns 200 with paginated notes for the history', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history] = createDoctorWithAppointment($patient);

        // Create 3 notes
        MedicalNote::create(['medical_history_id' => $history->id, 'doctor_id' => $doctor->id, 'diagnosis' => 'D1']);
        MedicalNote::create(['medical_history_id' => $history->id, 'doctor_id' => $doctor->id, 'diagnosis' => 'D2']);
        MedicalNote::create(['medical_history_id' => $history->id, 'doctor_id' => $doctor->id, 'diagnosis' => 'D3']);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->getJson("/api/medical-histories/{$history->id}/notes");

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    });
});

describe('GET /api/medical-notes/{note}', function (): void {
    it('returns 200 for an authorized doctor viewing the note', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history] = createDoctorWithAppointment($patient);

        $note = MedicalNote::create([
            'medical_history_id' => $history->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Viewable diagnosis',
        ]);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->getJson("/api/medical-notes/{$note->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $note->id)
            ->assertJsonPath('data.diagnosis', 'Viewable diagnosis');
    });

    it('returns 403 for an unauthorized doctor', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history] = createDoctorWithAppointment($patient);
        [$unauthorizedDoctorUser] = createDoctorWithoutAppointment();

        $note = MedicalNote::create([
            'medical_history_id' => $history->id,
            'doctor_id' => $doctor->id,
            'diagnosis' => 'Private diagnosis',
        ]);

        $response = $this->actingAs($unauthorizedDoctorUser, 'sanctum')
            ->getJson("/api/medical-notes/{$note->id}");

        $response->assertStatus(403);
    });
});

// ─── Task 4.4: N+1 fix ────────────────────────────────────────────

describe('N+1 prevention', function (): void {
    it('executes only 1 query for notes_count when loading 20 histories', function (): void {
        // Create a specialty and doctor directly (bypass factories to
        // avoid the unique-overflow in SpecialtyFactory when creating
        // many related models).
        $specialty = \App\Models\Specialty::create([
            'name' => 'General Medicine',
            'slug' => 'general-medicine',
            'is_active' => true,
        ]);

        $doctorUser = User::factory()->doctor()->create();
        $doctor = \App\Models\Doctor::create([
            'user_id' => $doctorUser->id,
            'specialty_id' => $specialty->id,
            'license_number' => 'LIC-DEFAULT',
        ]);

        // Create 20 patients, each with a history with 1 note.
        // Use direct DB insertion for the history to avoid the
        // Factory chain (MedicalHistoryFactory creates a Doctor via
        // DoctorFactory which overflows the Specialty unique pool).
        foreach (range(1, 20) as $i) {
            $patientUser = User::factory()->patient()->create();
            $patient = \App\Models\Patient::create([
                'user_id' => $patientUser->id,
                'identification_number' => "ID-{$i}",
                'phone' => '555-0100',
            ]);
            $history = MedicalHistory::create([
                'patient_id' => $patient->id,
                'primary_doctor_id' => $doctor->id,
                'opened_at' => now(),
            ]);

            MedicalNote::create([
                'medical_history_id' => $history->id,
                'doctor_id' => $doctor->id,
                'diagnosis' => "Diagnosis {$i}",
            ]);
        }

        $totalQueries = 0;
        DB::listen(function ($query) use (&$totalQueries): void {
            $sql = $query->sql;
            // Count only aggregate COUNT(*) queries on medical_notes
            if (str_contains($sql, 'select count(*)')
                && str_contains($sql, 'medical_notes')
                && ! str_contains($sql, 'exists')) {
                $totalQueries++;
            }
        });

        // Load all histories with notes_count (single aggregate query)
        $histories = MedicalHistory::query()
            ->withCount('notes')
            ->get();

        // Access notes_count to verify it's preloaded
        foreach ($histories as $history) {
            $count = $history->notes_count;
        }

        expect($totalQueries)->toBe(1);
    });
});
