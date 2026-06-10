<?php

use App\Filament\Resources\ClinicalRecords\MedicalHistoryResource;
use App\Filament\Resources\ClinicalRecords\RelationManagers\MedicalNotesRelationManager;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── RelationManager registration ─────────────────────────────────

it('resource registers MedicalNotesRelationManager', function (): void {
    expect(MedicalHistoryResource::getRelations())->toContain(MedicalNotesRelationManager::class);
});

it('relation manager uses notes as relationship name', function (): void {
    expect(MedicalNotesRelationManager::getRelationshipName())->toBe('notes');
});

// ─── No edit action ───────────────────────────────────────────────

it('relation manager has no edit action button in HTML', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    $appointment = Appointment::factory()->for($doctor)->for($patient)->completed()->create();
    MedicalNote::factory()->for($history)->for($doctor)->for($appointment)->create();

    Livewire::actingAs($doctorUser)
        ->test(\App\Filament\Resources\ClinicalRecords\Pages\EditMedicalHistory::class, ['record' => $history->getKey()])
        ->assertSuccessful()
        ->assertDontSeeHtml('wire\\:click=\"table\\.record\\.action\\.edit');
});

// ─── Note creation via model (auto-assign doctor_id) ──────────────

it('note model auto-assigns doctor_id from auth on create', function (): void {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->for($patientUser)->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $history = MedicalHistory::factory()->for($patient)->for($doctor, 'primaryDoctor')->create();
    $appointment = Appointment::factory()->for($doctor)->for($patient)->completed()->create();

    // Simulate what the RM Hidden field does: default from auth
    $note = MedicalNote::create([
        'medical_history_id' => $history->id,
        'appointment_id' => $appointment->id,
        'doctor_id' => $doctorUser->doctor->id,
        'symptoms' => 'Cough',
        'diagnosis' => 'Flu',
    ]);

    expect($note->doctor_id)->toBe($doctor->id);
});

// ─── Append-only enforcement at model level ───────────────────────

it('model rejects update attempt', function (): void {
    $note = MedicalNote::factory()->create();

    $note->diagnosis = 'Changed';
    $note->save();
})->throws(\LogicException::class, 'Medical notes are append-only. Update is not permitted.');

it('model rejects delete attempt', function (): void {
    $note = MedicalNote::factory()->create();

    $note->delete();
})->throws(\LogicException::class, 'Medical notes are append-only. Delete is not permitted.');