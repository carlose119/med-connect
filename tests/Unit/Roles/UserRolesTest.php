<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('identifies an admin user as admin only', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue()
        ->and($user->isDoctor())->toBeFalse()
        ->and($user->isPatient())->toBeFalse();
});

it('identifies a doctor user as doctor only', function () {
    $user = User::factory()->doctor()->create();

    expect($user->isDoctor())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->isPatient())->toBeFalse();
});

it('identifies a patient user as patient only', function () {
    $user = User::factory()->patient()->create();

    expect($user->isPatient())->toBeTrue()
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->isDoctor())->toBeFalse();
});
