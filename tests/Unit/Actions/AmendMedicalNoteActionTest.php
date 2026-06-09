<?php

use App\Actions\Medical\AmendMedicalNoteAction;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * PR 1 — clinical-records — AmendMedicalNoteAction.
 *
 * RED at this commit: the action class does not exist yet.
 * Tests fail until Task 2.2 creates it.
 */
beforeEach(function (): void {
    $this->history = MedicalHistory::factory()->create();
    $this->doctor = Doctor::factory()->create();

    $this->originalNote = MedicalNote::create([
        'medical_history_id' => $this->history->id,
        'doctor_id' => $this->doctor->id,
        'diagnosis' => 'Original diagnosis',
    ]);

    $this->action = app(AmendMedicalNoteAction::class);
});

it('creates a new note linked to the original via corrects_note_id', function (): void {
    $amendment = ($this->action)(
        $this->originalNote,
        ['diagnosis' => 'Corrected diagnosis'],
    );

    expect($amendment)->toBeInstanceOf(MedicalNote::class)
        ->and($amendment->exists)->toBeTrue()
        ->and($amendment->corrects_note_id)->toBe($this->originalNote->id)
        ->and($amendment->diagnosis)->toBe('Corrected diagnosis');

    // Original note must remain untouched
    $this->originalNote->refresh();
    expect($this->originalNote->diagnosis)->toBe('Original diagnosis');
});

it('throws LogicException when amending a note that already has corrections', function (): void {
    // First amendment — succeeds
    ($this->action)($this->originalNote, ['diagnosis' => 'First correction']);

    // Second amendment on the SAME original — must fail
    ($this->action)($this->originalNote, ['diagnosis' => 'Second correction']);
})->throws(\LogicException::class, 'already been corrected');
