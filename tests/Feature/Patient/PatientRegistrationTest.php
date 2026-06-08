<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a patient user and profile and redirects to dashboard on valid registration', function (): void {
    $response = $this->post('/patient/register', [
        'name' => 'John Doe',
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
});

it('rejects duplicate email registration', function (): void {
    User::factory()->patient()->create([
        'email' => 'a@b.com',
    ]);

    $response = $this->post('/patient/register', [
        'name' => 'Jane Doe',
        'email' => 'a@b.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});
