<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'invitation_token', 'invitation_sent_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'invitation_sent_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDoctor(): bool
    {
        return $this->role === 'doctor';
    }

    public function isPatient(): bool
    {
        return $this->role === 'patient';
    }

    public function isActive(): bool
    {
        return $this->is_active !== false;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isAdmin(),
            'doctor' => $this->isDoctor(),
            default => false,
        };
    }

    /**
     * Patient profile attached to this user (when role=patient). The
     * patient table holds the medical profile; the user table holds
     * the auth surface. The relation is `HasOne` because one user
     * owns at most one patient profile.
     */
    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    /**
     * Doctor profile attached to this user (when role=doctor). Same
     * shape as `patient()` — one user owns at most one doctor profile.
     */
    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    /**
     * Generate a UUID v4 invitation token, store its SHA-256 hash,
     * and return the raw token for use in URLs and emails.
     */
    public function generateInvitationToken(): string
    {
        $rawToken = Str::uuid()->toString();
        $this->invitation_token = hash('sha256', $rawToken);
        $this->invitation_sent_at = now();
        return $rawToken;
    }

    /**
     * Whether this user has a pending (non-null) invitation token.
     */
    public function hasPendingInvitation(): bool
    {
        return $this->invitation_token !== null;
    }

    /**
     * Whether the invitation token has expired.
     * Default expiration: 7 days after invitation was sent.
     */
    public function isInvitationExpired(int $days = 7): bool
    {
        return $this->invitation_sent_at !== null
            && $this->invitation_sent_at->addDays($days)->isPast();
    }

    /**
     * Clear the invitation token and sent-at timestamp after activation.
     */
    public function clearInvitationToken(): void
    {
        $this->invitation_token = null;
        $this->invitation_sent_at = null;
    }
}
