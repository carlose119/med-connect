<?php

use App\Filament\Doctor\Pages\RegisterWalkInPatient;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the walk-in registration page', function () {
    $user = User::factory()->doctor()->create();

    $this->actingAs($user)
        ->get('/doctor/register-walk-in')
        ->assertSuccessful();
});

it('creates a new patient with user in one transaction', function () {
    $user = User::factory()->doctor()->create();

    Livewire::actingAs($user)
        ->test(RegisterWalkInPatient::class)
        ->fillForm([
            'formData' => [
                'name' => 'María García',
                'email' => 'maria@example.com',
                'phone' => '+54 11 5555 1234',
                'identification_number' => '32123456',
                'birth_date' => '1990-05-15',
                'gender' => 'female',
            ],
        ])
        ->call('register');

    $this->assertDatabaseHas('users', [
        'name' => 'María García',
        'email' => 'maria@example.com',
        'role' => 'patient',
    ]);

    $patientUser = User::where('email', 'maria@example.com')->first();
    expect($patientUser)->not->toBeNull();

    $this->assertDatabaseHas('patients', [
        'user_id' => $patientUser->id,
        'phone' => '+54 11 5555 1234',
        'identification_number' => '32123456',
        'gender' => 'female',
    ]);
});

it('rejects duplicate email on registration', function () {
    $user = User::factory()->doctor()->create();
    User::factory()->patient()->create(['email' => 'existing@example.com']);

    Livewire::actingAs($user)
        ->test(RegisterWalkInPatient::class)
        ->fillForm([
            'formData' => [
                'name' => 'Nuevo Paciente',
                'email' => 'existing@example.com',
                'gender' => 'male',
            ],
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $this->assertDatabaseMissing('users', ['name' => 'Nuevo Paciente']);
});

it('rejects missing gender on registration', function () {
    $user = User::factory()->doctor()->create();

    Livewire::actingAs($user)
        ->test(RegisterWalkInPatient::class)
        ->fillForm([
            'formData' => [
                'name' => 'Test Patient',
                'gender' => '',
            ],
        ])
        ->call('register');

    $this->assertDatabaseMissing('users', ['name' => 'Test Patient']);
});

it('requires authentication', function () {
    $this->get('/doctor/register-walk-in')
        ->assertRedirect('/doctor/login');
});

it('non-doctor cannot access the page', function () {
    $patientUser = User::factory()->patient()->create();

    $this->actingAs($patientUser)
        ->get('/doctor/register-walk-in')
        ->assertForbidden();
});