<?php

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the profile edit page with user and patient data', function (): void {
    $patient = Patient::factory()->create();
    $user = $patient->user;

    $response = $this->actingAs($user)->get('/patient/profile');
    $response->assertOk();
    $response->assertSee($user->name);
    $response->assertSee('My Profile');
});

it('updates the profile with new name, email, and identification number', function (): void {
    $patient = Patient::factory()->create(['identification_number' => 'TEMP-1']);
    $user = $patient->user;

    $response = $this->actingAs($user)->post('/patient/profile', [
        'name' => 'Updated Name',
        'email' => $user->email,
        'identification_number' => 'DNI-12345678',
        'phone' => '+541112345678',
    ]);

    $response->assertRedirect('/patient/profile');
    $response->assertSessionHas('status');

    $user->refresh();
    $patient->refresh();

    expect($user->name)->toBe('Updated Name');
    expect($patient->identification_number)->toBe('DNI-12345678');
    expect($patient->phone)->toBe('+541112345678');
});
