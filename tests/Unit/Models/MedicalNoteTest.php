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

it('allows creating a new note', function (): void {
    $note = MedicalNote::create([
        'medical_history_id' => $this->history->id,
        'doctor_id' => $this->doctor->id,
        'diagnosis' => 'A new diagnosis',
    ]);

    expect($note)->toBeInstanceOf(MedicalNote::class)
        ->and($note->exists)->toBeTrue();
});
