<?php

use App\Actions\Medical\CreateMedicalNoteAction;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * PR 1 — clinical-records — CreateMedicalNoteAction.
 *
 * RED at this commit: the action class does not exist yet.
 * Tests fail until Task 2.1 creates it.
 */
beforeEach(function (): void {
    $this->history = MedicalHistory::factory()->create();
    $this->doctor = Doctor::factory()->create();
    $this->action = app(CreateMedicalNoteAction::class);
});

it('creates a MedicalNote with all fields', function (): void {
    $note = ($this->action)(
        $this->history,
        $this->doctor,
        [
            'symptoms' => 'Headache',
            'physical_exam' => 'Normal',
            'diagnosis' => 'Migraine',
            'treatment_notes' => 'Rest',
        ],
    );

    expect($note)->toBeInstanceOf(MedicalNote::class)
        ->and($note->exists)->toBeTrue()
        ->and($note->medical_history_id)->toBe($this->history->id)
        ->and($note->doctor_id)->toBe($this->doctor->id)
        ->and($note->symptoms)->toBe('Headache')
        ->and($note->physical_exam)->toBe('Normal')
        ->and($note->diagnosis)->toBe('Migraine')
        ->and($note->treatment_notes)->toBe('Rest');
});

it('creates a MedicalNote with only the required diagnosis field', function (): void {
    $note = ($this->action)(
        $this->history,
        $this->doctor,
        ['diagnosis' => 'Hypertension'],
    );

    expect($note)->toBeInstanceOf(MedicalNote::class)
        ->and($note->exists)->toBeTrue()
        ->and($note->diagnosis)->toBe('Hypertension')
        ->and($note->symptoms)->toBeNull();
});
