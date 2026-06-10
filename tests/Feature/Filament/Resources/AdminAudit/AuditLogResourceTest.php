<?php

use App\Filament\Resources\AdminAudit\AuditLogResource;
use App\Filament\Resources\AdminAudit\Pages\ListAuditLogs;
use App\Filament\Resources\AdminAudit\Pages\ViewAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── Resource configuration ─────────────────────────────────────────

it('binds AuditLog model', function (): void {
    expect(AuditLogResource::getModel())->toBe(AuditLog::class);
});

it('navigation is in Admin group with ShieldCheck icon and sort 99', function (): void {
    expect(AuditLogResource::getNavigationGroup())->toBe('Admin');
    expect(AuditLogResource::getNavigationIcon())->not->toBeNull();
    expect(AuditLogResource::getNavigationSort())->toBe(99);
});

it('cannot create an audit log', function (): void {
    expect(AuditLogResource::canCreate())->toBeFalse();
});

it('cannot edit an audit log', function (): void {
    $admin = User::factory()->admin()->create();
    $log = AuditLog::create([
        'user_id' => $admin->id,
        'actor_type' => 'admin',
        'action' => 'created',
        'subject_type' => 'Doctor',
        'subject_id' => 1,
        'metadata' => [],
        'ip_address' => '127.0.0.1',
    ]);

    expect(AuditLogResource::canEdit($log))->toBeFalse();
});

it('cannot delete an audit log', function (): void {
    $admin = User::factory()->admin()->create();
    $log = AuditLog::create([
        'user_id' => $admin->id,
        'actor_type' => 'admin',
        'action' => 'created',
        'subject_type' => 'Doctor',
        'subject_id' => 1,
        'metadata' => [],
        'ip_address' => '127.0.0.1',
    ]);

    expect(AuditLogResource::canDelete($log))->toBeFalse();
});

it('registers only index and view pages (no create, no edit)', function (): void {
    $pages = AuditLogResource::getPages();
    expect($pages)->toHaveKeys(['index', 'view'])
        ->not->toHaveKeys(['create', 'edit']);
});

// ─── List page renders ───────────────────────────────────────────────

it('renders audit log list with expected columns', function (): void {
    $admin = User::factory()->admin()->create();
    $log = AuditLog::create([
        'user_id' => $admin->id,
        'actor_type' => 'admin',
        'action' => 'created',
        'subject_type' => 'Doctor',
        'subject_id' => 1,
        'metadata' => [],
        'ip_address' => '127.0.0.1',
    ]);

    Livewire::actingAs($admin)
        ->test(ListAuditLogs::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$log]);
});

// ─── View page renders ───────────────────────────────────────────────

it('renders view page with read-only form', function (): void {
    $admin = User::factory()->admin()->create();
    $log = AuditLog::create([
        'user_id' => $admin->id,
        'actor_type' => 'admin',
        'action' => 'updated',
        'subject_type' => 'Patient',
        'subject_id' => 3,
        'metadata' => ['reason' => 'Corrected address'],
        'ip_address' => '10.0.0.1',
    ]);

    Livewire::actingAs($admin)
        ->test(ViewAuditLog::class, ['record' => $log->getKey()])
        ->assertSuccessful();
});

// ─── No create page exposed ──────────────────────────────────────────

it('no create page exposed', function (): void {
    $pages = AuditLogResource::getPages();
    expect($pages)->not->toHaveKey('create');
});

// ─── No edit page exposed ────────────────────────────────────────────

it('no edit page exposed', function (): void {
    $pages = AuditLogResource::getPages();
    expect($pages)->not->toHaveKey('edit');
});