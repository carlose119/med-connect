<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPatients;

uses(RefreshDatabase::class, CreatesPatients::class);

it('returns new tokens for valid refresh token', function (): void {
    $user = User::factory()->patient()->create([
        'password' => bcrypt('password123'),
    ]);

    // Login to get tokens
    $loginResponse = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $refreshToken = $loginResponse->json('data.refresh_token');

    // Use refresh token
    $response = $this->postJson('/api/auth/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'refresh_token'])
        ->assertJsonMissing(['data']);

    // New tokens should be different
    $newToken = $response->json('token');
    $newRefreshToken = $response->json('refresh_token');
    expect($newToken)->not->toBeEmpty();
    expect($newRefreshToken)->toHaveLength(64);
});

it('returns 401 for invalid refresh token', function (): void {
    $response = $this->postJson('/api/auth/refresh', [
        'refresh_token' => 'invalid-token-value',
    ]);

    $response->assertStatus(401);
});

it('returns 401 for expired refresh token', function (): void {
    $user = User::factory()->patient()->create();

    // Create an expired refresh token directly in DB
    $hashedToken = hash('sha256', 'expired-token');
    \App\Models\RefreshToken::create([
        'user_id' => $user->id,
        'token' => $hashedToken,
        'device_name' => 'test',
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->postJson('/api/auth/refresh', [
        'refresh_token' => 'expired-token',
    ]);

    $response->assertStatus(401);
});