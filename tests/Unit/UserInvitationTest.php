<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('generate_invitation_token_creates_hash_and_sets_sent_at', function () {
    $user = User::factory()->doctor()->create();
    $rawToken = $user->generateInvitationToken();
    $user->save();

    expect($rawToken)->toBeUuid()
        ->and($user->invitation_token)->toHaveLength(64) // SHA-256 hex
        ->and($user->invitation_sent_at)->not->toBeNull()
        ->and($user->hasPendingInvitation())->toBeTrue();
});

test('is_invitation_expired_returns_false_for_fresh_token', function () {
    $user = User::factory()->doctor()->create();
    $user->generateInvitationToken();
    $user->save();

    expect($user->isInvitationExpired())->toBeFalse();
});

test('is_invitation_expired_returns_true_for_old_token', function () {
    $user = User::factory()->doctor()->create();
    $user->generateInvitationToken();
    $user->save();
    $user->invitation_sent_at = now()->subDays(8);
    $user->save();

    expect($user->isInvitationExpired())->toBeTrue();
});

test('clear_invitation_token_removes_fields', function () {
    $user = User::factory()->doctor()->create();
    $user->generateInvitationToken();
    $user->save();
    $user->clearInvitationToken();
    $user->save();

    expect($user->invitation_token)->toBeNull()
        ->and($user->invitation_sent_at)->toBeNull()
        ->and($user->hasPendingInvitation())->toBeFalse();
});