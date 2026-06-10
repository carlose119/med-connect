<?php

use App\Actions\Medical\CancelPrescriptionAction;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new CancelPrescriptionAction();

    $doctorUser = \App\Models\User::factory()->doctor()->create();
    $this->doctor = Doctor::factory()->for($doctorUser)->create();

    $patientUser = \App\Models\User::factory()->patient()->create();
    $this->patient = Patient::factory()->for($patientUser)->create();

    // Active prescription
    $this->prescription = Prescription::factory()->create([
        'doctor_id' => $this->doctor->id,
        'patient_id' => $this->patient->id,
        'status' => 'active',
        'cancellation_reason' => null,
    ]);
});

describe('execute', function (): void {
    it('sets status to cancelled on an active prescription', function (): void {
        $result = ($this->action)($this->prescription, 'Patient requested cancellation.');

        expect($result->status)->toBe('cancelled');
    });

    it('sets the cancellation_reason when provided', function (): void {
        $result = ($this->action)($this->prescription, 'Patient requested cancellation.');

        expect($result->cancellation_reason)->toBe('Patient requested cancellation.');
    });

    it('sets cancellation_reason to null when no reason is provided', function (): void {
        $result = ($this->action)($this->prescription, null);

        expect($result->cancellation_reason)->toBeNull();
    });

    it('rejects cancellation of an already cancelled prescription', function (): void {
        $cancelledPrescription = Prescription::factory()->create([
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'status' => 'cancelled',
        ]);

        expect(fn () => ($this->action)($cancelledPrescription, 'Trying again'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('returns the updated prescription', function (): void {
        $result = ($this->action)($this->prescription, 'Test reason');

        expect($result)->toBeInstanceOf(Prescription::class);
        expect($result->id)->toBe($this->prescription->id);
    });
});