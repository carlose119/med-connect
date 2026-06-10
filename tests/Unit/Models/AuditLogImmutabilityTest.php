<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Task 1.2 — AuditLog append-only boot guard.
 *
 * RED at this commit: the boot() saving/deleting events do not exist yet,
 * so update() and delete() will NOT throw. The tests are expected to FAIL
 * until Task 1.2 adds the boot event listeners.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('throws LogicException when attempting to update a persisted audit log', function (): void {
    $log = AuditLog::create([
        'user_id' => $this->admin->id,
        'actor_type' => 'admin',
        'action' => 'created',
        'subject_type' => 'Doctor',
        'subject_id' => 1,
        'metadata' => [],
        'ip_address' => '127.0.0.1',
    ]);

    $fresh = AuditLog::findOrFail($log->id);
    $fresh->action = 'updated';
    $fresh->save();
})->throws(\LogicException::class);

it('throws LogicException when attempting to delete a persisted audit log', function (): void {
    $log = AuditLog::create([
        'user_id' => $this->admin->id,
        'actor_type' => 'admin',
        'action' => 'created',
        'subject_type' => 'Doctor',
        'subject_id' => 1,
        'metadata' => [],
        'ip_address' => '127.0.0.1',
    ]);

    $fresh = AuditLog::findOrFail($log->id);
    $fresh->delete();
})->throws(\LogicException::class);

it('allows creating a new audit log row', function (): void {
    $log = AuditLog::create([
        'user_id' => $this->admin->id,
        'actor_type' => 'admin',
        'action' => 'created',
        'subject_type' => 'User',
        'subject_id' => 42,
        'metadata' => ['key' => 'value'],
        'ip_address' => '10.0.0.1',
    ]);

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->exists)->toBeTrue()
        ->and($log->action)->toBe('created')
        ->and($log->subject_type)->toBe('User')
        ->and($log->subject_id)->toBe(42);
});