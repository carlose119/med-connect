<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a patient user and profile and redirects to dashboard on valid registration', function (): void {
    $response = $this->post('/patient/register', [
        'name' => 'John Doe',
        'identification_number' => 'DNI-12345678',
        'phone' => '+541112345678',
        'email' => 'john@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertRedirect('/patient/dashboard');

    $this->assertAuthenticated();

    $user = User::where('email', 'john@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('patient');

    $patient = Patient::where('user_id', $user->id)->first();
    expect($patient)->not->toBeNull();
    expect($patient->identification_number)->toBe('DNI-12345678');
    expect($patient->phone)->toBe('+541112345678');
});

it('rejects duplicate email registration', function (): void {
    User::factory()->patient()->create([
        'email' => 'a@b.com',
    ]);

    $response = $this->post('/patient/register', [
        'name' => 'Jane Doe',
        'identification_number' => 'DNI-87654321',
        'phone' => '+541198765432',
        'email' => 'a@b.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rejects duplicate identification number registration', function (): void {
    Patient::factory()->create([
        'identification_number' => 'DNI-11111111',
    ]);

    $response = $this->post('/patient/register', [
        'name' => 'Jane Doe',
        'identification_number' => 'DNI-11111111',
        'phone' => '+541198765432',
        'email' => 'jane@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertSessionHasErrors('identification_number');
    $this->assertGuest();
});