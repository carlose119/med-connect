<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects to dashboard with authenticated session on valid login', function (): void {
    $user = User::factory()->patient()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('correct-password'),
    ]);

    $response = $this->post('/patient/login', [
        'email' => 'john@example.com',
        'password' => 'correct-password',
    ]);

    $response->assertRedirect('/patient/dashboard');
    $this->assertAuthenticated();
});

it('shows validation error and does not authenticate on invalid password', function (): void {
    $user = User::factory()->patient()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('correct-password'),
    ]);

    $response = $this->post('/patient/login', [
        'email' => 'john@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors();
    $this->assertGuest();
});
