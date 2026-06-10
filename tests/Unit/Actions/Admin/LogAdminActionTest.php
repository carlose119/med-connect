<?php

use App\Actions\Admin\LogAdminAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Task 1.1 — LogAdminAction unit tests.
 *
 * RED at this commit: LogAdminAction does not exist yet.
 * Tests fail until the action class is created.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->action = new LogAdminAction();
});

it('creates an audit log row with correct fields', function (): void {
    $log = ($this->action)(
        $this->admin,
        'created',
        'Doctor',
        99,
        ['reason' => 'New hire'],
        '127.0.0.1',
    );

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->exists)->toBeTrue()
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->actor_type)->toBe('admin')
        ->and($log->action)->toBe('created')
        ->and($log->subject_type)->toBe('Doctor')
        ->and($log->subject_id)->toBe(99)
        ->and($log->metadata)->toBe(['reason' => 'New hire'])
        ->and($log->ip_address)->toBe('127.0.0.1');
});

it('extracts short class name from subject_type', function (): void {
    $log = ($this->action)(
        $this->admin,
        'updated',
        \App\Models\Doctor::class, // fully qualified
        1,
        [],
        null,
    );

    // class_basename strips the namespace
    expect($log->subject_type)->toBe('Doctor');
});

it('uses request ip when ip param is not provided', function (): void {
    // When no explicit IP is passed, the action falls back to request()->ip().
    // Laravel test context sets this to 127.0.0.1 by default.
    $log = ($this->action)(
        $this->admin,
        'deleted',
        'User',
        5,
        [],
        null, // null triggers fallback to request()->ip()
    );

    expect($log->ip_address)->toBe('127.0.0.1');
});

it('accepts explicit ip address', function (): void {
    $log = ($this->action)(
        $this->admin,
        'toggled',
        'Patient',
        7,
        [],
        '203.0.113.42',
    );

    expect($log->ip_address)->toBe('203.0.113.42');
});

it('creates audit log with empty metadata', function (): void {
    $log = ($this->action)(
        $this->admin,
        'created',
        'User',
        12,
        [], // empty metadata
        '10.0.0.1',
    );

    expect($log->metadata)->toEqual([]);
});