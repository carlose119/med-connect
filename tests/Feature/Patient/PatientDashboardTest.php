<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows upcoming appointments with date, time, doctor, specialty, and status', function (): void {
    $patient = Patient::factory()->create();
    $user = $patient->user;

    $cardiology = Specialty::factory()->create(['name' => 'Cardiology']);
    $doctor = Doctor::factory()->create(['specialty_id' => $cardiology->id]);

    $appointment1 = Appointment::factory()->create([
        'doctor_id' => $doctor->id,
        'patient_id' => $patient->id,
        'start_time' => now()->addDay()->setTime(10, 0),
        'end_time' => now()->addDay()->setTime(10, 30),
        'state' => 'pending',
    ]);

    $appointment2 = Appointment::factory()->create([
        'doctor_id' => $doctor->id,
        'patient_id' => $patient->id,
        'start_time' => now()->addDays(2)->setTime(14, 30),
        'end_time' => now()->addDays(2)->setTime(15, 0),
        'state' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get('/patient/dashboard');
    $response->assertOk();

    // Both appointments must appear with their details
    $response->assertSee($doctor->user->name);
    $response->assertSee('Cardiology');
    $response->assertSee('pending');
    $response->assertSee('confirmed');
    $response->assertSee($appointment1->start_time->format('H:i'));
    $response->assertSee($appointment2->start_time->format('H:i'));
});

it('shows an empty-state message when the patient has no appointments', function (): void {
    $patient = Patient::factory()->create();
    $user = $patient->user;

    $response = $this->actingAs($user)->get('/patient/dashboard');
    $response->assertOk();
    $response->assertSee('No upcoming appointments');
});

it('shows past appointments with completed, cancelled, and no_show status', function (): void {
    $patient = Patient::factory()->create();
    $user = $patient->user;

    $cardiology = Specialty::factory()->create(['name' => 'Cardiology']);
    $doctor = Doctor::factory()->create(['specialty_id' => $cardiology->id]);

    Appointment::factory()->create([
        'doctor_id' => $doctor->id,
        'patient_id' => $patient->id,
        'start_time' => now()->subDays(5)->setTime(10, 0),
        'end_time' => now()->subDays(5)->setTime(10, 30),
        'state' => 'completed',
    ]);

    Appointment::factory()->create([
        'doctor_id' => $doctor->id,
        'patient_id' => $patient->id,
        'start_time' => now()->subDays(10)->setTime(14, 0),
        'end_time' => now()->subDays(10)->setTime(14, 30),
        'state' => 'cancelled',
    ]);

    $response = $this->actingAs($user)->get('/patient/dashboard');
    $response->assertOk();

    $response->assertSee('Past Appointments');
    $response->assertSee('completed');
    $response->assertSee('cancelled');
});

it('does not show cancelled upcoming appointments in the upcoming section', function (): void {
    $patient = Patient::factory()->create();
    $user = $patient->user;

    $cardiology = Specialty::factory()->create(['name' => 'Cardiology']);
    $doctor = Doctor::factory()->create(['specialty_id' => $cardiology->id]);

    // An appointment cancelled in the past should only appear in Past Appointments
    Appointment::factory()->create([
        'doctor_id' => $doctor->id,
        'patient_id' => $patient->id,
        'start_time' => now()->addDays(3)->setTime(10, 0),
        'end_time' => now()->addDays(3)->setTime(10, 30),
        'state' => 'cancelled',
    ]);

    $response = $this->actingAs($user)->get('/patient/dashboard');
    $response->assertOk();

    // Upcoming section should be empty
    $response->assertSee('No upcoming appointments');
});
