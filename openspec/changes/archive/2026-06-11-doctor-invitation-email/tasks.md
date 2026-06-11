---
change: doctor-invitation-email
status: tasks
created: 2026-06-10
---

# Tasks: Doctor Invitation Email (RF-1.2)

## Overview

Replace the plaintext temp password email flow with a token-based account activation flow. When an admin creates a doctor, the system generates a UUID v4 token (SHA-256 hash stored in DB), queues an `InvitationActivated` mailable, and the doctor activates via a link.

## Task List

---

### T-INV-01 · Create database migration ✅ COMPLETE

**Summary**: Add `invitation_token` (string 64, nullable, indexed) + `invitation_sent_at` (timestamp, nullable) to `users` table.

**Type**: structural

**STEPS**:
1. Run: `php artisan make:migration add_invitation_fields_to_users`
2. In the generated migration, add to `up()`:
   ```php
   $table->string('invitation_token', 64)->nullable()->index()->after('password');
   $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token');
   ```
3. Add to `down()`:
   ```php
   $table->dropColumn(['invitation_token', 'invitation_sent_at']);
   ```
4. Run: `php artisan migrate`

**Affected files**:
- `database/migrations/YYYY_MM_DD_HHMMSS_add_invitation_fields_to_users.php` (create)

---

### T-INV-02 · Update User model ✅ COMPLETE

**Prerequisite**: Add `unactivated()` factory state to `UserFactory.php` first:
```php
public function unactivated(): static
{
    return $this->state(fn (array $attributes) => [
        'is_active' => false,
    ]);
}
```

**Summary**: Add invitation fields to fillable, casts, and helper methods.

**Type**: unit

**TDD Steps**:

**RED**: Write `tests/Unit/UserInvitationTest.php`
```php
test('generate_invitation_token_creates_hash_and_sets_sent_at', function () {
    $user = User::factory()->doctor()->make();
    $rawToken = $user->generateInvitationToken();

    expect($rawToken)->toBeUuid();
    expect($user->invitation_token)->toHaveLength(64); // SHA-256 hex
    expect($user->invitation_sent_at)->not->toBeNull();
    expect($user->hasPendingInvitation())->toBeTrue();
});

test('is_invitation_expired_returns_false_for_fresh_token', function () {
    $user = User::factory()->doctor()->make();
    $user->generateInvitationToken();

    expect($user->isInvitationExpired())->toBeFalse();
});

test('is_invitation_expired_returns_true_for_old_token', function () {
    $user = User::factory()->doctor()->make();
    $user->generateInvitationToken();
    $user->invitation_sent_at = now()->subDays(8);

    expect($user->isInvitationExpired())->toBeTrue();
});

test('clear_invitation_token_removes_fields', function () {
    $user = User::factory()->doctor()->make();
    $user->generateInvitationToken();
    $user->clearInvitationToken();

    expect($user->invitation_token)->toBeNull();
    expect($user->invitation_sent_at)->toBeNull();
    expect($user->hasPendingInvitation())->toBeFalse();
});
```

**GREEN**: Update `app/Models/User.php`
```php
// In #[Fillable]:
'invitation_token', 'invitation_sent_at'

// In casts():
'invitation_sent_at' => 'datetime',

// Add methods:
public function generateInvitationToken(): string
{
    $rawToken = Str::uuid()->toString();
    $this->invitation_token = hash('sha256', $rawToken);
    $this->invitation_sent_at = now();
    return $rawToken;
}

public function hasPendingInvitation(): bool
{
    return $this->invitation_token !== null;
}

public function isInvitationExpired(int $days = 7): bool
{
    return $this->invitation_sent_at !== null
        && $this->invitation_sent_at->addDays($days)->isPast();
}

public function clearInvitationToken(): void
{
    $this->invitation_token = null;
    $this->invitation_sent_at = null;
}
```

**REFACTOR**: Add PHPDoc to helper methods. Run tests until green.

**Affected files**:
- `app/Models/User.php` (modify)
- `tests/Unit/UserInvitationTest.php` (create)

---

### T-INV-03 · Create InvitationController ✅ COMPLETE

**Summary**: Create controller with show() and activate() actions for `/invitation/{token}`.

**Type**: structural + feature

**TDD Steps**:

**RED**: Write `tests/Feature/InvitationFlowTest.php` — first 3 tests:
```php
test('show_renders_activation_form_for_valid_token', function () {
    $user = User::factory()->doctor()->unactivated()->create();
    $token = $user->generateInvitationToken();

    $response = $this->get("/invitation/{$token}");

    $response->assertStatus(200);
    $response->assertSee('Activar tu cuenta');
    $response->assertSee('password');
    $response->assertSee('password_confirmation');
});

test('show_renders_expired_page_for_old_token', function () {
    $user = User::factory()->doctor()->unactivated()->create();
    $token = $user->generateInvitationToken();
    $user->invitation_sent_at = now()->subDays(8);
    $user->save();

    $response = $this->get("/invitation/{$token}");

    $response->assertStatus(200);
    $response->assertSee('Enlace expirado');
});

test('show_redirects_for_consumed_or_invalid_token', function () {
    // Invalid token
    $response = $this->get('/invitation/non-existent-token');
    $response->assertRedirect('/doctor/login');

    // Consumed token (user already active)
    $user = User::factory()->doctor()->create(); // is_active = true
    $token = $user->generateInvitationToken();
    $response = $this->get("/invitation/{$token}");
    $response->assertRedirect('/doctor/login');
});
```

**GREEN**: Create `app/Http/Controllers/InvitationController.php`:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function show(string $token)
    {
        $hash = hash('sha256', $token);
        $user = User::where('invitation_token', $hash)->first();

        if (! $user || $user->isActive()) {
            return redirect()->route('doctor.login');
        }

        if ($user->isInvitationExpired()) {
            return view('invitation.expired');
        }

        return view('invitation.activate', ['token' => $token]);
    }

    public function activate(Request $request, string $token)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $hash = hash('sha256', $token);
        $user = User::where('invitation_token', $hash)->first();

        if (! $user || $user->isActive()) {
            return redirect()->route('doctor.login');
        }

        if ($user->isInvitationExpired()) {
            return view('invitation.expired');
        }

        $user->password = bcrypt($request->password);
        $user->is_active = true;
        $user->clearInvitationToken();
        $user->save();

        return redirect()->route('doctor.login')
            ->with('success', 'Cuenta activada. Iniciá sesión.');
    }
}
```

**REFACTOR**: Extract token validation into a private `findValidUser(string $token)` method. Run tests until green.

**Affected files**:
- `app/Http/Controllers/InvitationController.php` (create)
- `tests/Feature/InvitationFlowTest.php` (create — partial, add more tests in T-INV-11)

---

### T-INV-04 · Add web routes ✅ COMPLETE

**Summary**: Add GET + POST routes for `/invitation/{token}`.

**Type**: structural

**STEPS**:
1. Read `routes/web.php`
2. Add:
   ```php
   Route::get('/invitation/{token}', [InvitationController::class, 'show'])
       ->middleware('guest')
       ->name('invitation.show');

   Route::post('/invitation/{token}', [InvitationController::class, 'activate'])
       ->middleware('guest')
       ->name('invitation.activate');
   ```
3. Verify routes: `php artisan route:list | grep invitation`

**Affected files**:
- `routes/web.php` (modify)

---

### T-INV-05 · Create activate.blade.php view ✅ COMPLETE

**Summary**: Create the account activation form with password + confirm fields.

**Type**: structural

**STEPS**:
1. Create `resources/views/invitation/` directory
2. Create `activate.blade.php`:
   - Use same design language as `emails/doctor-account-created.blade.php` (header, content, footer)
   - Header: MedConnect branding
   - H2: "Activar tu cuenta"
   - Paragraph: "Establecé tu contraseña para ingresar a MedConnect"
   - Form: POST to `/invitation/{token}`, @csrf, password field, password_confirmation field, submit button "Activar mi cuenta"
   - Info box: "Una vez activada, tu cuenta queda lista para usar."
   - @error('password') and @error('password_confirmation') for validation feedback
   - @if(session('success')) for flash message

**Affected files**:
- `resources/views/invitation/activate.blade.php` (create)

---

### T-INV-06 · Create expired.blade.php view ✅ COMPLETE

**Summary**: Create the "Enlace expirado" page with instructions.

**Type**: structural

**STEPS**:
1. Create `expired.blade.php`:
   - Same header/footer as activate.blade.php
   - H2: "Enlace expirado"
   - Paragraph: "Este enlace de activación venció hace más de 7 días."
   - Info box: "Contactá al administrador para solicitar un nuevo enlace."
   - Link: "Volver al inicio" → `/`

**Affected files**:
- `resources/views/invitation/expired.blade.php` (create)

---

### T-INV-07 · Create InvitationActivated mailable ✅ COMPLETE

**Summary**: Create the mailable replacing DoctorAccountCreated. Uses raw token + expiry date.

**Type**: structural

**STEPS**:
1. Create `app/Mail/InvitationActivated.php`:
   ```php
   class InvitationActivated extends Mailable implements ShouldQueue
   {
       use Queueable, SerializesModels;

       public function __construct(
           public User $doctorUser,
           public string $invitationToken,
           public Carbon $expiresAt,
       ) {}

       public function envelope(): Envelope
       {
           return new Envelope(
               subject: 'Tu cuenta de médico ha sido creada — MedConnect',
           );
       }

       public function content(): Content
       {
           return new Content(
               view: 'emails.doctor-invitation',
               with: [
                   'doctorName' => $this->doctorUser->name,
                   'email' => $this->doctorUser->email,
                   'invitationToken' => $this->invitationToken,
                   'expiresAt' => $this->expiresAt,
                   'activationUrl' => url("/invitation/{$this->invitationToken}"),
               ],
           );
       }
   }
   ```

**Affected files**:
- `app/Mail/InvitationActivated.php` (create)

---

### T-INV-08 · Create doctor-invitation email view ✅ COMPLETE

**Summary**: Redesign the email template — remove credential box, add CTA button.

**Type**: structural

**STEPS**:
1. Create `resources/views/emails/doctor-invitation.blade.php`:
   - Same overall structure as `doctor-account-created.blade.php` (header, content, footer)
   - Remove: `<div class="credential-box">` with temp password
   - Add: "Activar mi cuenta" button linking to `{activationUrl}`
   - Add: "Este enlace expira el {expiresAt->format('d/m/Y')}" (Argentine date format)
   - Keep: info box with email and role
   - Keep: login link button for after activation
   - Add: warning about expiration

**Affected files**:
- `resources/views/emails/doctor-invitation.blade.php` (create)

---

### T-INV-09 · Update CreateUser.php ✅ COMPLETE

**Summary**: Replace temp password generation with token generation; queue InvitationActivated; handle re-invitation.

**Type**: structural

**TDD Steps**:

**RED**: Add to `tests/Feature/Admin/UserResourceAuditLogTest.php`:
```php
test('admin_creating_doctor_sends_invitation_email_not_temp_password', function () {
    Mail::fake();

    livewire(CreateUser::class, [
        'data' => [
            'name' => 'Dr. Test',
            'email' => 'doctor.test@example.com',
            'role' => 'doctor',
            'specialty_id' => $this->specialty->id,
            'license_number' => 'MP-12345',
        ],
    ])->assertSuccessful();

    Mail::assertQueued(InvitationActivated::class, function ($mail) {
        return $mail->hasTo('doctor.test@example.com')
            && Str::isUuid($mail->invitationToken);
    });
});
```

**GREEN**: Update `app/Filament/Resources/Users/Pages/CreateUser.php`:
```php
// In handleRecordCreation():
// Remove: temp password auto-generation
// Replace with:

// Handle re-invitation: reuse existing inactive doctor with same email
$existingUser = User::where('email', $data['email'])
    ->where('role', 'doctor')
    ->where('is_active', false)
    ->first();

if ($existingUser) {
    $user = $existingUser;
    $rawToken = $user->generateInvitationToken();
} else {
    // Create new doctor user (no password set)
    $data['password'] = null; // doctors set their own password via invitation
    $data['is_active'] = false;
    $user = static::getModel()::create($data);
    $rawToken = $user->generateInvitationToken();
}

// Create Doctor profile in same transaction
$doctor = Doctor::create([...]);

// Queue invitation email
$expiresAt = now()->addDays(7);
Mail::to($user->email)->queue(new InvitationActivated($user, $rawToken, $expiresAt));

// AuditLog: update metadata key from 'temp_password_sent' to 'invitation_sent'
$metadata = [..., 'invitation_sent' => true];
```

**REFACTOR**: Extract re-invitation logic into a private method. Run tests until green.

**Affected files**:
- `app/Filament/Resources/Users/Pages/CreateUser.php` (modify)
- `tests/Feature/Admin/UserResourceAuditLogTest.php` (modify)

---

### T-INV-10 · Complete InvitationFlowTest ✅ COMPLETE

**Summary**: Add remaining feature tests for the full invitation lifecycle.

**Type**: feature

**Add to `tests/Feature/InvitationFlowTest.php`**:

```php
test('activate_sets_password_and_activates_account', function () {
    $user = User::factory()->doctor()->unactivated()->create();
    $token = $user->generateInvitationToken();

    $response = $this->post("/invitation/{$token}", [
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $response->assertRedirect('/doctor/login');
    $response->assertSessionHas('success', 'Cuenta activada. Iniciá sesión.');

    $user->refresh();
    expect($user->is_active)->toBeTrue();
    expect($user->invitation_token)->toBeNull();
    expect(Hash::check('Password123', $user->password))->toBeTrue();
});

test('activate_validates_password_confirmation', function () {
    $user = User::factory()->doctor()->unactivated()->create();
    $token = $user->generateInvitationToken();

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

    $response = $this->post('/doctor/login', [
        'email' => $user->email,
        'password' => 'Password123',
    ]);

    $response->assertRedirect('/doctor');
    $this->assertAuthenticatedAs($user);
});

test('re_invitation_invalidates_previous_token', function () {
    Mail::fake();

    // First invitation
    $user = User::factory()->doctor()->unactivated()->create();
    $token1 = $user->generateInvitationToken();
    $oldHash = $user->invitation_token;

    // Admin re-invites (re-creates with same email)
    Livewire::test(CreateUser::class, [
        'data' => [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'doctor',
            'specialty_id' => $user->doctor->specialty_id,
            'license_number' => $user->doctor->license_number,
        ],
    ])->assertSuccessful();

    $user->refresh();
    expect($user->invitation_token)->not->toBe($oldHash);
    expect($user->invitation_sent_at->greaterThan(now()->subSecond(5)))->toBeTrue();
});
```

**Run**: `.\vendor\bin\pest --filter=InvitationFlowTest`

**Affected files**:
- `tests/Feature/InvitationFlowTest.php` (modify)

---

### T-INV-11 · Run full test suite ✅ COMPLETE

**Summary**: Verify all tests pass; confirm no regressions.

**Type**: verification

**STEPS**:
1. Run: `.\vendor\bin\pest`
2. If any test fails, fix the implementation before proceeding
3. Run with coverage: `.\vendor\bin\pest --coverage`

**Expected**: All tests green.

---

### T-INV-12 · Delete old mail files ✅ COMPLETE

**Summary**: Remove the deprecated DoctorAccountCreated mailable and email template.

**Type**: cleanup

**STEPS**:
1. Delete: `app/Mail/DoctorAccountCreated.php`
2. Delete: `resources/views/emails/doctor-account-created.blade.php`
3. Run tests to confirm nothing breaks: `.\vendor\bin\pest`

**Affected files**:
- `app/Mail/DoctorAccountCreated.php` (delete)
- `resources/views/emails/doctor-account-created.blade.php` (delete)

---

## Review Workload Estimate

| Category | Lines |
|----------|-------|
| New files (migration, controller, mailable, views, tests) | ~180 |
| Modified files (User model, CreateUser, routes, test) | ~120 |
| **Total** | **~300** |
| Chained PRs recommended | No |
| 400-line budget risk | **Low** |
| Decision needed before apply | No |

