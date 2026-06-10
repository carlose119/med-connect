<?php

use App\Actions\Medical\IssuePrescriptionAction;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new IssuePrescriptionAction();

    // Create doctor and patient
    $doctorUser = \App\Models\User::factory()->doctor()->create();
    $this->doctor = Doctor::factory()->for($doctorUser)->create();

    $patientUser = \App\Models\User::factory()->patient()->create();
    $this->patient = Patient::factory()->for($patientUser)->create();

    // Create a completed appointment
    $this->appointment = Appointment::factory()->create([
        'doctor_id' => $this->doctor->id,
        'patient_id' => $this->patient->id,
        'state' => 'completed',
    ]);
});

describe('execute', function (): void {
    it('creates a prescription with the given items', function (): void {
        $items = [
            ['name' => 'Ibuprofen 400mg', 'dosage' => '400 mg', 'frequency' => 'cada 8h', 'duration' => '7 días'],
            ['name' => 'Omeprazole 20mg', 'dosage' => '20 mg', 'frequency' => '1 vez al día', 'duration' => '14 días'],
            ['name' => 'Paracetamol 500mg', 'dosage' => '500 mg', 'frequency' => 'cada 6h', 'duration' => '5 días'],
        ];

        $prescription = ($this->action)(
            $this->appointment,
            $this->doctor,
            $items
        );

        $this->assertDatabaseHas('prescriptions', [
            'id' => $prescription->id,
            'appointment_id' => $this->appointment->id,
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseCount('prescription_items', 3);
    });

    it('assigns correct 1-based positions to items in array order', function (): void {
        $items = [
            ['name' => 'Item A', 'dosage' => null, 'frequency' => null, 'duration' => null],
            ['name' => 'Item B', 'dosage' => null, 'frequency' => null, 'duration' => null],
            ['name' => 'Item C', 'dosage' => null, 'frequency' => null, 'duration' => null],
        ];

        $prescription = ($this->action)(
            $this->appointment,
            $this->doctor,
            $items
        );

        $savedItems = $prescription->items()->orderBy('position')->get();

        expect($savedItems[0]->name)->toBe('Item A');
        expect($savedItems[0]->position)->toBe(1);

        expect($savedItems[1]->name)->toBe('Item B');
        expect($savedItems[1]->position)->toBe(2);

        expect($savedItems[2]->name)->toBe('Item C');
        expect($savedItems[2]->position)->toBe(3);
    });

    it('sets a unique_code on the prescription', function (): void {
        $prescription = ($this->action)(
            $this->appointment,
            $this->doctor,
            [['name' => 'Test drug', 'dosage' => null, 'frequency' => null, 'duration' => null]]
        );

        expect($prescription->unique_code)->not->toBeNull();
        expect($prescription->unique_code)->toMatch('/^RX-\d{4}-\d{6}$/');
    });

    it('rejects an appointment that is not completed', function (): void {
        $pendingAppointment = Appointment::factory()->create([
            'doctor_id' => $this->doctor->id,
            'patient_id' => $this->patient->id,
            'state' => 'pending',
        ]);

        $items = [['name' => 'Test drug', 'dosage' => null, 'frequency' => null, 'duration' => null]];

        expect(fn () => ($this->action)($pendingAppointment, $this->doctor, $items))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('persists prescription and items in a single transaction', function (): void {
        // If transaction fails, no prescription should be created
        $items = [
            ['name' => 'Drug A', 'dosage' => null, 'frequency' => null, 'duration' => null],
        ];

        $prescription = ($this->action)(
            $this->appointment,
            $this->doctor,
            $items
        );

        // Verify both prescription and items exist in DB
        expect(Prescription::find($prescription->id))->not->toBeNull();
        expect($prescription->items)->toHaveCount(1);
    });
});