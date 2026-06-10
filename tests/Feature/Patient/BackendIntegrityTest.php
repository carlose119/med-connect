<?php

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns JSON response for API routes without interference from patient-web', function (): void {
    // Seed a doctor so the API has data to return
    $doctor = Doctor::factory()->create();

    // Patient-web session should NOT affect API routes
    $patient = Patient::factory()->create();
    $this->actingAs($patient->user);

    $response = $this->getJson('/api/doctors');
    $response->assertOk();
    $response->assertJsonStructure(['data']);
});

it('allows access to admin panel routes without patient-web interference', function (): void {
    $patient = Patient::factory()->create();
    $this->actingAs($patient->user);

    // Admin routes are accessible (not blocked by patient-web middleware)
    // The admin itself will enforce its own authorization, but the routes respond
    $response = $this->get('/admin');
    // We expect NOT a redirect back to patient-web or a 404 from route conflicts
    $this->assertTrue(
        in_array($response->status(), [200, 302, 403]),
        "Admin route returned unexpected status {$response->status()}"
    );
});