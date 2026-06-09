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

test('cancel action via Livewire stores reason and transitions', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    $appointment = Appointment::factory()->for($doctor)->for($patient)->create([
        'state' => 'pending',
        'start_time' => CarbonImmutable::now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test(ListDoctorAppointments::class)
        ->callTableAction('cancel', $appointment, [
            'cancellation_reason' => 'Patient requested via Livewire',
        ]);

    $appointment->refresh();
    expect($appointment->state::class)->toBe(Cancelled::class);
    expect($appointment->cancellation_reason)->toBe('Patient requested via Livewire');
});

test('non-doctor cannot access appointments page', function () {
    $patientUser = User::factory()->patient()->create();

    $this->actingAs($patientUser)
        ->get('/doctor/appointments')
        ->assertForbidden();
});

test('confirm action transitions to confirmed', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    $appointment = Appointment::factory()->for($doctor)->for($patient)->create([
        'state' => 'pending',
        'start_time' => CarbonImmutable::now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test(ListDoctorAppointments::class)
        ->callTableAction('confirm', $appointment);

    $appointment->refresh();
    expect($appointment->state::class)->toBe(Confirmed::class);
});

test('complete action transitions confirmed to completed', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    $appointment = Appointment::factory()->for($doctor)->for($patient)->create([
        'state' => 'confirmed',
        'start_time' => CarbonImmutable::now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test(ListDoctorAppointments::class)
        ->callTableAction('complete', $appointment);

    $appointment->refresh();
    expect($appointment->state::class)->toBe(Completed::class);
});

test('no show action transitions confirmed to no show', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    $appointment = Appointment::factory()->for($doctor)->for($patient)->create([
        'state' => 'confirmed',
        'start_time' => CarbonImmutable::now()->addDay(),
    ]);

    Livewire::actingAs($user)
        ->test(ListDoctorAppointments::class)
        ->callTableAction('no_show', $appointment);

    $appointment->refresh();
    expect($appointment->state::class)->toBe(NoShow::class);
});

test('cancel action hidden for terminal states', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();
    $base = CarbonImmutable::now()->addDay();

    $completed = Appointment::factory()->for($doctor)->for($patient)->completed()->create([
        'start_time' => $base,
    ]);
    $cancelled = Appointment::factory()->for($doctor)->for($patient)->cancelled()->create([
        'start_time' => $base->addHour(),
    ]);
    $noShow = Appointment::factory()->for($doctor)->for($patient)->noShow()->create([
        'start_time' => $base->addHours(2),
    ]);

    Livewire::actingAs($user)
        ->test(ListDoctorAppointments::class)
        ->assertTableActionHidden('cancel', $completed)
        ->assertTableActionHidden('cancel', $cancelled)
        ->assertTableActionHidden('cancel', $noShow);
});

test('no show action hidden for non confirmed states', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();
    $base = CarbonImmutable::now()->addDay();

    $pending = Appointment::factory()->for($doctor)->for($patient)->pending()->create([
        'start_time' => $base,
    ]);
    $completed = Appointment::factory()->for($doctor)->for($patient)->completed()->create([
        'start_time' => $base->addHour(),
    ]);
    $cancelled = Appointment::factory()->for($doctor)->for($patient)->cancelled()->create([
        'start_time' => $base->addHours(2),
    ]);
    $noShow = Appointment::factory()->for($doctor)->for($patient)->noShow()->create([
        'start_time' => $base->addHours(3),
    ]);

    Livewire::actingAs($user)
        ->test(ListDoctorAppointments::class)
        ->assertTableActionHidden('no_show', $pending)
        ->assertTableActionHidden('no_show', $completed)
        ->assertTableActionHidden('no_show', $cancelled)
        ->assertTableActionHidden('no_show', $noShow);
});

test('date filter today shows only todays appointments', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    $today = CarbonImmutable::now()->startOfDay()->addHours(10);
    $tomorrow = CarbonImmutable::now()->addDay()->startOfDay()->addHours(10);

    Appointment::factory()->for($doctor)->for($patient)->create([
        'start_time' => $today,
    ]);
    Appointment::factory()->for($doctor)->for($patient)->create([
        'start_time' => $tomorrow,
    ]);

    Livewire::actingAs($user)
        ->test(ListDoctorAppointments::class)
        ->set('tableFilters.date_range.value', 'today')
        ->assertCountTableRecords(1);
});

test('date filter past shows only past appointments', function () {
    $user = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($user)->create();
    $patient = Patient::factory()->create();

    $yesterday = CarbonImmutable::now()->subDay()->startOfDay()->addHours(10);
    $tomorrow = CarbonImmutable::now()->addDay()->startOfDay()->addHours(10);

    Appointment::factory()->for($doctor)->for($patient)->create([
        'start_time' => $yesterday,
    ]);
    Appointment::factory()->for($doctor)->for($patient)->create([
        'start_time' => $tomorrow,
    ]);

    Livewire::actingAs($user)
        ->test(ListDoctorAppointments::class)
        ->set('tableFilters.date_range.value', 'past')
        ->assertCountTableRecords(1);
});
