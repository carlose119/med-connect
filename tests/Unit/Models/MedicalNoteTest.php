<?php

use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * PR 1 — clinical-records — Append-only guard on MedicalNote.
 *
 * RED at this commit: the boot() events do not exist yet, so update()
 * and delete() will NOT throw. The test is expected to FAIL until
 * Task 1.2 adds the saving/deleting event listeners.
 *
 * Spec: Append-Only Medical Notes (openspec/specs/clinical-records/spec.md)
 *   - Updating an existing note is impossible (LogicException)
 *   - Deleting an existing note is impossible (LogicException)
 *   - Amendment creates a new note (create() still succeeds)
 */
beforeEach(function (): void {
    $this->history = MedicalHistory::factory()->create();
    $this->doctor = Doctor::factory()->create();
});

it('throws LogicException when attempting to update a persisted note', function (): void {
    $note = MedicalNote::create([
        'medical_history_id' => $this->history->id,
        'doctor_id' => $this->doctor->id,
        'diagnosis' => 'Initial diagnosis',
    ]);

    $fresh = MedicalNote::findOrFail($note->id);
    $fresh->diagnosis = 'Updated diagnosis';
    $fresh->save();
})->throws(\LogicException::class);

it('throws LogicException when attempting to delete a persisted note', function (): void {
    $note = MedicalNote::create([
        'medical_history_id' => $this->history->id,
        'doctor_id' => $this->doctor->id,
        'diagnosis' => 'Initial diagnosis',
    ]);

    $fresh = MedicalNote::findOrFail($note->id);
    $fresh->delete();
})->throws(\LogicException::class);

it('allows creating a new note with basic fields', function (): void {
    $note = MedicalNote::create([
        'medical_history_id' => $this->history->id,
        'doctor_id' => $this->doctor->id,
        'diagnosis' => 'A new diagnosis',
    ]);

    expect($note)->toBeInstanceOf(MedicalNote::class)
        ->and($note->exists)->toBeTrue();
});

it('allows creating a new note with all fields', function (): void {
    $note = MedicalNote::create([
        'medical_history_id' => $this->history->id,
        'doctor_id' => $this->doctor->id,
        'symptoms' => 'Headache and fever',
        'physical_exam' => 'Temperature 38.5°C',
        'diagnosis' => 'Common cold',
        'treatment_notes' => 'Rest and hydration',
    ]);

    expect($note)->toBeInstanceOf(MedicalNote::class)
        ->and($note->exists)->toBeTrue()
        ->and($note->symptoms)->toBe('Headache and fever')
        ->and($note->physical_exam)->toBe('Temperature 38.5°C')
        ->and($note->diagnosis)->toBe('Common cold')
        ->and($note->treatment_notes)->toBe('Rest and hydration');
});

it('allows building and saving an unsaved model', function (): void {
    $note = new MedicalNote([
        'medical_history_id' => $this->history->id,
        'doctor_id' => $this->doctor->id,
        'diagnosis' => 'Unsaved diagnosis',
    ]);

    $note->save();

    expect($note->exists)->toBeTrue();
});
