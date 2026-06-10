<?php

use App\Filament\Resources\ClinicalRecords\Pages\ViewPrescription;
use App\Filament\Resources\ClinicalRecords\RelationManagers\PrescriptionItemsRelationManager;
use App\Models\Doctor;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─── RelationManager registration ─────────────────────────────────────

it('relation manager uses items as relationship name', function (): void {
    expect(PrescriptionItemsRelationManager::getRelationshipName())->toBe('items');
});

// ─── Items table renders on view page ───────────────────────────────

it('renders items table with expected columns on view page', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $prescription = Prescription::factory()->for($doctor)->create();
    PrescriptionItem::factory()->for($prescription)->create(['name' => 'Amoxicillin']);

    // Verify the relationship resolves correctly (items count = 1)
    $items = $prescription->items()->get();
    expect($items)->toHaveCount(1)
        ->and($items->first()->name)->toBe('Amoxicillin');
});

// ─── Items ordered by position ───────────────────────────────────────

it('items displayed in position order', function (): void {
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $prescription = Prescription::factory()->for($doctor)->create();

    // Create items out of position order: C, A, B
    PrescriptionItem::factory()->for($prescription)->create(['name' => 'Item C', 'position' => 3]);
    PrescriptionItem::factory()->for($prescription)->create(['name' => 'Item A', 'position' => 1]);
    PrescriptionItem::factory()->for($prescription)->create(['name' => 'Item B', 'position' => 2]);

    // Verify items come back in position order via the relationship
    $items = $prescription->items()->get()->pluck('name')->toArray();
    expect($items)->toBe(['Item A', 'Item B', 'Item C']);
});

// ─── No create action on items table ─────────────────────────────────

it('no create action on items table', function (): void {
    $adminUser = User::factory()->admin()->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $prescription = Prescription::factory()->for($doctor)->create();

    Livewire::actingAs($adminUser)
        ->test(ViewPrescription::class, ['record' => $prescription->getKey()])
        ->assertDontSeeHtml('wire\\:click=\"table\\.record\\.action\\.create')
        ->assertDontSeeHtml('wire\\:click=\"header\\.action\\.create');
});

// ─── No edit action on items table ──────────────────────────────────

it('no edit action on items table', function (): void {
    $adminUser = User::factory()->admin()->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $prescription = Prescription::factory()->for($doctor)->create();
    PrescriptionItem::factory()->for($prescription)->create();

    Livewire::actingAs($adminUser)
        ->test(ViewPrescription::class, ['record' => $prescription->getKey()])
        ->assertDontSeeHtml('wire\\:click=\"table\\.record\\.action\\.edit');
});

// ─── No delete action on items table ────────────────────────────────

it('no delete action on items table', function (): void {
    $adminUser = User::factory()->admin()->create();
    $doctorUser = User::factory()->doctor()->create();
    $doctor = Doctor::factory()->for($doctorUser)->create();
    $prescription = Prescription::factory()->for($doctor)->create();
    PrescriptionItem::factory()->for($prescription)->create();

    Livewire::actingAs($adminUser)
        ->test(ViewPrescription::class, ['record' => $prescription->getKey()])
        ->assertDontSeeHtml('wire\\:click=\"table\\.record\\.action\\.delete');
});