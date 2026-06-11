<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('show_renders_activation_form_for_valid_token', function () {
    $user = User::factory()->doctor()->create();
    $user->is_active = false;
    $user->save();
    $token = $user->generateInvitationToken();
    $user->save();

    $response = $this->get("/invitation/{$token}");

    $response->assertStatus(200);
    $response->assertSee('Activar tu cuenta');
    $response->assertSee('password');
    $response->assertSee('password_confirmation');
});

test('show_renders_expired_page_for_old_token', function () {
    $user = User::factory()->doctor()->create();
    $user->is_active = false;
    $user->save();
    $token = $user->generateInvitationToken();
    $user->save();
    $user->invitation_sent_at = now()->subDays(8);
    $user->save();

    $response = $this->get("/invitation/{$token}");

    $response->assertStatus(200);
    $response->assertSee('Enlace expirado');
});

test('show_redirects_for_invalid_token', function () {
    $response = $this->get('/invitation/non-existent-token');

    $response->assertRedirect('/doctor/login');
});

test('show_redirects_for_already_active_user', function () {
    $user = User::factory()->doctor()->create(); // is_active = true by default
    $token = $user->generateInvitationToken();
    $user->save();

    $response = $this->get("/invitation/{$token}");

    $response->assertRedirect('/doctor/login');
});

test('activate_sets_password_and_activates_account', function () {
    $user = User::factory()->doctor()->create();
    $user->is_active = false;
    $user->save();
    $token = $user->generateInvitationToken();
    $user->save();

    $response = $this->post("/invitation/{$token}", [
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $response->assertRedirect('/doctor/login');
    $response->assertSessionHas('success', 'Cuenta activada. Iniciá sesión.');

    $user->refresh();
    expect($user->is_active)->toBeTrue()
        ->and($user->invitation_token)->toBeNull()
        ->and(\Illuminate\Support\Facades\Hash::check('Password123', $user->password))->toBeTrue();
});

test('activate_validates_password_confirmation', function () {
    $user = User::factory()->doctor()->create();
    $user->is_active = false;
    $user->save();
    $token = $user->generateInvitationToken();
    $user->save();

    $response = $this->post("/invitation/{$token}", [
        'password' => 'Password123',
        'password_confirmation' => 'WrongPassword',
    ]);

    $response->assertSessionHasErrors('password');
});

test('doctor_can_login_after_activation', function () {
    $user = User::factory()->doctor()->create(['is_active' => true]);
    $user->password = bcrypt('Password123');
    $user->save();

    // Verify the user can access the doctor panel when authenticated
    $this->actingAs($user);
    $response = $this->get('/doctor');

    $response->assertStatus(200);
});

test('re_invitation_invalidates_previous_token', function () {
    $admin = User::factory()->admin()->create();
    $specialty = Specialty::factory()->create();

    // Create inactive doctor with existing token
    $user = User::factory()->doctor()->create(['is_active' => false]);
    expect($user->is_active)->toBeFalse();

    $doctor = \App\Models\Doctor::create([
        'user_id' => $user->id,
        'specialty_id' => $specialty->id,
        'license_number' => 'MP-99999',
    ]);
    $token1 = $user->generateInvitationToken();
    $user->save();
    $oldHash = $user->invitation_token;
    $originalUserId = $user->id;
    $userCountBefore = User::count();

    $this->actingAs($admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'doctor',
            'specialty_id' => $specialty->id,
            'license_number' => 'MP-99999',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Verify no new user was created (re-invitation reused existing user)
    expect(User::count())->toBe($userCountBefore);

    $user->refresh();
    // Verify same user was reused and token was regenerated
    expect($user->id)->toBe($originalUserId)
        ->and($user->invitation_token)->not->toBe($oldHash);
});