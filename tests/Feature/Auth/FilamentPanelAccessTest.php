<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin reaches /admin', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin')->assertSuccessful();
});

test('non-admin is denied /admin', function () {
    $doctor = User::factory()->doctor()->create();
    $this->actingAs($doctor)->get('/admin')->assertForbidden();
});

test('doctor reaches /doctor', function () {
    $doctor = User::factory()->doctor()->create();
    $this->actingAs($doctor)->get('/doctor')->assertSuccessful();
});

test('non-doctor is denied /doctor', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/doctor')->assertForbidden();
});
