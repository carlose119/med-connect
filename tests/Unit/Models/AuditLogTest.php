<?php

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Task 1.2 — AuditLog immutability via factory.
 *
 * RED: Tests written against the boot() saving/deleting guards in AuditLog.
 * GREEN: Guards already exist in AuditLog.php — these tests should pass.
 */
it('allows creating a new audit log via factory', function (): void {
    $log = AuditLog::factory()->create();

    $this->assertDatabaseHas('audit_logs', [
        'id' => $log->id,
        'action' => $log->action,
    ]);
});

it('throws when attempting to update an existing audit log', function (): void {
    $log = AuditLog::factory()->create();

    expect(function () use ($log): void {
        $log->action = 'hacked';
        $log->save();
    })->toThrow(\LogicException::class, 'Audit logs are immutable. Update is not permitted.');
});

it('throws when attempting to delete an existing audit log', function (): void {
    $log = AuditLog::factory()->create();

    expect(fn () => $log->delete())->toThrow(\LogicException::class, 'Audit logs are immutable. Delete is not permitted.');
});