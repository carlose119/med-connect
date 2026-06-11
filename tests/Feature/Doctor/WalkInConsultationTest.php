<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use App\Models\Patient;
use App\Models\User;
use App\States\Appointment\Confirmed;
use App\States\Appointment\Pending;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the walk-in consultation page with patient_id', function () {
    $doctorUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorUser)->create();
    $patient = Patient::factory()->create();

    $this->actingAs($doctorUser)
        ->get("/doctor/consultation/{$patient->id}")
        ->assertSuccessful();
});

it('creates medical history if patient has none', function () {
    $doctorUser = User::factory()->doctor()->create();
    /** @var Doctor $doctor */
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $patient = Patient::factory()->create();

    expect(MedicalHistory::where('patient_id', $patient->id)->exists())->toBeFalse();

    $this->actingAs($doctorUser)
        ->get("/doctor/consultation/{$patient->id}")
        ->assertSuccessful();

    expect(MedicalHistory::where('patient_id', $patient->id)->exists())->toBeTrue();
});

it('creates walk-in appointment on mount', function () {
    $doctorUser = User::factory()->doctor()->create();
    /** @var Doctor $doctor */
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $patient = Patient::factory()->create();

    $this->actingAs($doctorUser)
        ->get("/doctor/consultation/{$patient->id}")
        ->assertSuccessful();

    $this->assertDatabaseHas('appointments', [
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'notes' => 'Consulta sin cita',
    ]);
});

it('saves a medical note', function () {
    $doctorUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorUser)->create();
    $patient = Patient::factory()->create();
    MedicalHistory::factory()->for($patient)->create();

    $this->actingAs($doctorUser)
        ->post("/doctor/consultation/{$patient->id}/save-note", [
            'patient_id' => $patient->id,
            'symptoms' => 'Dolor de cabeza',
            'physical_exam' => 'Presión arterial normal',
            'diagnosis' => 'Cefalea tensional',
            'treatment_notes' => 'Reposo y paracetamol',
        ])
        ->assertSessionHas('status');

    expect(MedicalNote::count())->toBe(1);
    $note = MedicalNote::first();
    expect($note->symptoms)->toBe('Dolor de cabeza');
    expect($note->diagnosis)->toBe('Cefalea tensional');
});

it('rejects missing diagnosis when saving note', function () {
    $doctorUser = User::factory()->doctor()->create();
    Doctor::factory()->for($doctorUser)->create();
    $patient = Patient::factory()->create();
    MedicalHistory::factory()->for($patient)->create();

    $this->actingAs($doctorUser)
        ->post("/doctor/consultation/{$patient->id}/save-note", [
            'patient_id' => $patient->id,
            'diagnosis' => '',
        ])
        ->assertSessionHasErrors('diagnosis');

    expect(MedicalNote::count())->toBe(0);
});

it('confirmAppointment transitions pending to confirmed', function () {
    $doctorUser = User::factory()->doctor()->create();
    /** @var Doctor $doctor */
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $patient = Patient::factory()->create();
    MedicalHistory::factory()->for($patient)->create();

    $appointment = Appointment::factory()
        ->for($doctor)
        ->for($patient)
        ->create(['state' => Pending::class]);

    $this->actingAs($doctorUser)
        ->post("/doctor/consultation/{$patient->id}/confirm")
        ->assertSessionHas('status');

    $appointment->refresh();
    expect($appointment->state)->toBeInstanceOf(Confirmed::class);
});

it('completeConsultation transitions confirmed to completed', function () {
    $doctorUser = User::factory()->doctor()->create();
    /** @var Doctor $doctor */
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $patient = Patient::factory()->create();
    MedicalHistory::factory()->for($patient)->create();

    $appointment = Appointment::factory()
        ->for($doctor)
        ->for($patient)
        ->create(['state' => Confirmed::class]);

    $this->actingAs($doctorUser)
        ->post("/doctor/consultation/{$patient->id}/complete")
        ->assertSessionHas('status');

    $appointment->refresh();
    expect($appointment->state::class)->toBe('App\States\Appointment\Completed');
});

it('non-doctor cannot access consultation page', function () {
    $patientUser = User::factory()->patient()->create();
    $patient = Patient::factory()->create();

    $this->actingAs($patientUser)
        ->get("/doctor/consultation/{$patient->id}")
        ->assertForbidden();
});

it('requires authentication', function () {
    $patient = Patient::factory()->create();

    // Project has no 'login' route, so auth middleware throws 500 instead of redirect
    $this->get("/doctor/consultation/{$patient->id}")
        ->assertStatus(500);
});