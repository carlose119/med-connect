<?php

use App\Filament\Resources\DoctorAppointments\Pages\ListDoctorAppointments;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\States\Appointment\Cancelled;
use App\States\Appointment\Completed;
use App\States\Appointment\Confirmed;
use App\States\Appointment\NoShow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('doctor can see appointments page', function () {
    $user = User::factory()->doctor()->create();
    Doctor::factory()->for($user)->create();

    $this->actingAs($user)
        ->get('/doctor/appointments')
        ->assertSuccessful();
});

test('doctor sees only their own appointments', function () {
    $user1 = User::factory()->doctor()->create();
    $doctor1 = Doctor::factory()->for($user1)->create();
    $patient1 = Patient::factory()->create();

    $user2 = User::factory()->doctor()->create();
    $doctor2 = Doctor::factory()->for($user2)->create();
    $patient2 = Patient::factory()->create();

    Appointment::factory()->for($doctor1)->for($patient1)->create([
        'start_time' => CarbonImmutable::now()->addDay(),
    ]);
    Appointment::factory()->for($doctor2)->for($patient2)->create([
        'start_time' => CarbonImmutable::now()->addDay(),
    ]);

    $this->actingAs($user1)
        ->get('/doctor/appointments')
        ->assertSuccessful()
        ->assertSeeText($patient1->user->name)
        ->assertDontSeeText($patient2->user->name);
});

test('confirm action visible for pending appointments', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    Appointment::factory()->for($doctor)->for($patient)->create([
        'state' => 'pending',
        'start_time' => CarbonImmutable::now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get('/doctor/appointments')
        ->assertSuccessful()
        ->assertSee('Pending')
        ->assertSee('Confirm')
        ->assertSee('Cancel')
        ->assertDontSee('Complete')
        ->assertDontSee('No Show');
});

test('confirm action NOT visible for confirmed appointments', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    Appointment::factory()->for($doctor)->for($patient)->create([
        'state' => 'confirmed',
        'start_time' => CarbonImmutable::now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get('/doctor/appointments')
        ->assertSuccessful()
        ->assertSee('Confirmed')
        ->assertSee('Complete')
        ->assertSee('Cancel')
        ->assertSee('No Show');
});

test('cancel action changes state and stores reason', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    $appointment = Appointment::factory()->for($doctor)->for($patient)->create([
        'state' => 'pending',
        'start_time' => CarbonImmutable::now()->addDay(),
    ]);

    $appointment->cancellation_reason = 'Patient requested cancellation';
    $appointment->state->transitionTo(Cancelled::class, $user);

    $appointment->refresh();
    expect($appointment->state::$name)->toBe('cancelled');
    expect($appointment->cancellation_reason)->toBe('Patient requested cancellation');
});

test('non-doctor cannot access appointments page', function () {
    $patientUser = User::factory()->patient()->create();

    $this->actingAs($patientUser)
        ->get('/doctor/appointments')
        ->assertForbidden();
});
