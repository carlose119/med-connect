<?php

use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class);

/**
 * PR 3 — agenda-http — GET /api/specialties (REQ-API-6 + design §5
 * #11). Returns the ACTIVE list (small fixed-size, no pagination).
 *
 * RED at this commit: the route doesn't exist. Both scenarios fail.
 */

it('returns only the active specialties', function (): void {
    Specialty::create(['name' => 'Cardiology', 'slug' => 'cardiology', 'is_active' => true]);
    Specialty::create(['name' => 'Dermatology', 'slug' => 'dermatology', 'is_active' => true]);
    Specialty::create(['name' => 'Discontinued', 'slug' => 'discontinued', 'is_active' => false]);

    [$patient, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($patient, 'sanctum')
        ->getJson('/api/specialties');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');

    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('cardiology');
    expect($slugs)->toContain('dermatology');
    expect($slugs)->not->toContain('discontinued');
});

it('returns the resource shape with id, name, slug, is_active', function (): void {
    Specialty::create(['name' => 'Cardiology', 'slug' => 'cardiology', 'is_active' => true]);

    [$patient, , ] = $this->createPatientWithToken();

    $response = $this->actingAs($patient, 'sanctum')
        ->getJson('/api/specialties');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'slug', 'is_active'],
            ],
        ]);

    $row = $response->json('data.0');
    expect($row['is_active'])->toBeTrue();
    expect($row['name'])->toBe('Cardiology');
});
