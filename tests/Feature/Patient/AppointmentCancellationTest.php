<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use App\States\Appointment\Cancelled;
use App\States\Appointment\Pending;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $specialty = Specialty::factory()->create(['name' => 'Cardiology']);
    $doctor = Doctor::factory()->create(['specialty_id' => $specialty->id]);

    $this->patient = Patient::factory()->create();
    $this->user = $this->patient->user;

    $this->doctor = $doctor;
});

it('cancels an appointment when inside the 24h window (start_time at +48h)', function (): void {
    $this->actingAs($this->user);

    $appointment = Appointment::factory()->create([
        'doctor_id' => $this->doctor->id,
        'patient_id' => $this->patient->id,
        'start_time' => CarbonImmutable::now()->addHours(48)->setTime(10, 0),
        'end_time' => CarbonImmutable::now()->addHours(48)->setTime(10, 30),
        'state' => 'pending',
    ]);

    $response = $this->post(route('patient.cancel', $appointment));

    $response->assertRedirect(route('patient.dashboard'));
    $response->assertSessionHas('status');

    $appointment->refresh();
    expect($appointment->state)->toBeInstanceOf(Cancelled::class);
});

it('rejects cancellation when outside the 24h window (start_time at +12h)', function (): void {
    $this->actingAs($this->user);

    $appointment = Appointment::factory()->create([
        'doctor_id' => $this->doctor->id,
        'patient_id' => $this->patient->id,
        'start_time' => CarbonImmutable::now()->addHours(12)->setTime(10, 0),
        'end_time' => CarbonImmutable::now()->addHours(12)->setTime(10, 30),
        'state' => 'pending',
    ]);

    $response = $this->post(route('patient.cancel', $appointment));

    // Must reject with error
    $response->assertSessionHasErrors();
    $response->assertRedirect();

    // State must remain unchanged
    $appointment->refresh();
    expect($appointment->state)->toBeInstanceOf(Pending::class);
});
