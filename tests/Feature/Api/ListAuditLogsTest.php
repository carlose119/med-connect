<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesDoctors;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class, CreatesDoctors::class);

/**
 * PR 3 — agenda-http — GET /api/audit-logs (REQ-API-6 + design §5
 * #15). ADMIN-ONLY.
 *
 * RED at this commit: the route doesn't exist. All 4 scenarios fail.
 *
 * Authz (controller checks isAdmin() directly because there is no
 * AuditLogPolicy):
 *   - admin   → 200, paginated list with filter support
 *   - doctor  → 403 FORBIDDEN
 *   - patient → 403 FORBIDDEN
 */

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    [$this->doctorUser, , ] = $this->createDoctorWithToken();
    [$this->patientUser, , ] = $this->createPatientWithToken();

    // Seed 5 audit logs with different action verbs.
    for ($i = 0; $i < 3; $i++) {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'actor_type' => 'admin',
            'action' => 'user.created',
            'subject_type' => User::class,
            'subject_id' => $i + 1,
            'metadata' => ['role' => 'patient'],
            'ip_address' => '127.0.0.1',
        ]);
    }
    AuditLog::create([
        'user_id' => $this->admin->id,
        'actor_type' => 'admin',
        'action' => 'doctor.created',
        'subject_type' => 'Doctor',
        'subject_id' => 1,
        'metadata' => ['license_number' => 'LIC-001'],
        'ip_address' => '127.0.0.1',
    ]);
    AuditLog::create([
        'user_id' => $this->admin->id,
        'actor_type' => 'admin',
        'action' => 'appointment.cancelled',
        'subject_type' => 'Appointment',
        'subject_id' => 1,
        'metadata' => [],
        'ip_address' => '127.0.0.1',
    ]);
});

it('returns a paginated list of audit logs for an admin actor', function (): void {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/audit-logs');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'user_id', 'actor_type', 'action', 'subject_type', 'subject_id', 'created_at'],
            ],
            'meta',
            'links',
        ]);
});

it('returns 403 FORBIDDEN for a doctor actor', function (): void {
    $response = $this->actingAs($this->doctorUser, 'sanctum')
        ->getJson('/api/audit-logs');

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('returns 403 FORBIDDEN for a patient actor', function (): void {
    $response = $this->actingAs($this->patientUser, 'sanctum')
        ->getJson('/api/audit-logs');

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('filters by ?action=doctor.created', function (): void {
    $response = $this->actingAs($this->admin, 'sanctum')
        ->getJson('/api/audit-logs?action=doctor.created');

    $response->assertStatus(200)
        ->assertJsonPath('meta.total', 1);

    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['action'])->toBe('doctor.created');
});
