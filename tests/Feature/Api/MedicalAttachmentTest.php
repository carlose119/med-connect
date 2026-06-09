<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalAttachment;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use App\Models\Patient;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 2 — clinical-records-attachments — API integration tests for
 * Medical Attachments (upload, list, delete).
 *
 * Strict TDD: every test below is written in RED phase, referencing
 * code that does NOT exist yet. Production code is added per task.
 *
 * Scenarios cover Tasks 5.1 through 5.6.
 */

// ─── Helpers ──────────────────────────────────────────────────────

/**
 * Build a completed appointment + medical history + note for a patient.
 * Returns [doctorUser, doctor, history, note].
 *
 * @return array{0: User, 1: Doctor, 2: MedicalHistory, 3: MedicalNote}
 */
function createDoctorWithAppointmentAndNote(Patient $patient): array
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

    $note = MedicalNote::create([
        'medical_history_id' => $history->id,
        'doctor_id' => $doctor->id,
        'diagnosis' => 'Test diagnosis for attachments',
    ]);

    return [$doctorUser, $doctor, $history, $note];
}

// ─── Task 5.1: clinical_attachments disk ──────────────────────────

describe('clinical_attachments disk', function (): void {
    it('is registered in filesystems config', function (): void {
        $disks = config('filesystems.disks');

        expect(isset($disks['clinical_attachments']))->toBeTrue();
        expect($disks['clinical_attachments'])->toHaveKeys(['driver', 'root']);
    });
});

// ─── Task 5.6: Upload integration ─────────────────────────────────

describe('POST /api/medical-notes/{note}/attachments', function (): void {
    it('uploads a file and returns 201 with the attachment resource', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history, $note] = createDoctorWithAppointmentAndNote($patient);

        Storage::fake('clinical_attachments');

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson("/api/medical-notes/{$note->id}/attachments", [
                'file' => UploadedFile::fake()->image('xray.jpg'),
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'note_id', 'filename', 'mime', 'size', 'url', 'created_at']])
            ->assertJsonPath('data.note_id', $note->id)
            ->assertJsonPath('data.filename', 'xray.jpg')
            ->assertJsonPath('data.mime', 'image/jpeg');
    });

    it('rejects files larger than 10MB', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history, $note] = createDoctorWithAppointmentAndNote($patient);

        Storage::fake('clinical_attachments');

        $oversized = UploadedFile::fake()->create('large.pdf', 15000, 'application/pdf');

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson("/api/medical-notes/{$note->id}/attachments", [
                'file' => $oversized,
            ]);

        $response->assertStatus(422);
    });

    it('rejects invalid mime types', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history, $note] = createDoctorWithAppointmentAndNote($patient);

        Storage::fake('clinical_attachments');

        $invalid = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->postJson("/api/medical-notes/{$note->id}/attachments", [
                'file' => $invalid,
            ]);

        $response->assertStatus(422);
    });
});

// ─── Task 5.6: List integration ───────────────────────────────────

describe('GET /api/medical-notes/{note}/attachments', function (): void {
    it('returns attachments for the note', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history, $note] = createDoctorWithAppointmentAndNote($patient);

        // Create two attachments via factory
        MedicalAttachment::factory()
            ->count(2)
            ->for($note)
            ->create(['uploaded_by' => $doctorUser->id]);

        $response = $this->actingAs($doctorUser, 'sanctum')
            ->getJson("/api/medical-notes/{$note->id}/attachments");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [
                '*' => ['id', 'note_id', 'filename', 'mime', 'size', 'url', 'created_at'],
            ]]);
    });
});

// ─── Task 5.6: Delete integration ─────────────────────────────────

describe('DELETE /api/medical-attachments/{attachment}', function (): void {
    it('deletes own attachment and returns 200', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history, $note] = createDoctorWithAppointmentAndNote($patient);

        Storage::fake('clinical_attachments');

        // Upload first so we have a real file on the fake disk
        $uploadResponse = $this->actingAs($doctorUser, 'sanctum')
            ->postJson("/api/medical-notes/{$note->id}/attachments", [
                'file' => UploadedFile::fake()->image('to-delete.jpg'),
            ]);

        $attachmentId = $uploadResponse->json('data.id');

        // Now delete it
        $response = $this->actingAs($doctorUser, 'sanctum')
            ->deleteJson("/api/medical-attachments/{$attachmentId}");

        $response->assertStatus(200);

        // Verify it's gone from the DB
        expect(MedicalAttachment::find($attachmentId))->toBeNull();
    });

    it('returns 403 when a different user tries to delete', function (): void {
        [$patientUser, $patient] = $this->createPatientWithToken();
        [$doctorUser, $doctor, $history, $note] = createDoctorWithAppointmentAndNote($patient);
        [$otherDoctorUser] = $this->createDoctorWithToken();

        Storage::fake('clinical_attachments');

        // Upload as doctorUser
        $uploadResponse = $this->actingAs($doctorUser, 'sanctum')
            ->postJson("/api/medical-notes/{$note->id}/attachments", [
                'file' => UploadedFile::fake()->image('protected.jpg'),
            ]);

        $attachmentId = $uploadResponse->json('data.id');

        // Try to delete as otherDoctorUser — should be forbidden
        $response = $this->actingAs($otherDoctorUser, 'sanctum')
            ->deleteJson("/api/medical-attachments/{$attachmentId}");

        $response->assertStatus(403);

        // Attachment must still exist
        expect(MedicalAttachment::find($attachmentId))->not->toBeNull();
    });
});
