<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Mail\InvitationActivated;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Handle the user creation. When the chosen role is `doctor`, the User
     * and the linked Doctor profile are written in a single transaction so
     * we never end up with an orphaned user account. As a side effect we
     * also emit an `audit_logs` row — the admin-audit spec requires every
     * admin action to leave a trail (actor, action verb, subject, metadata).
     *
     * For doctors, no password is set here — the doctor activates their
     * account via the invitation link email, where they set their own password.
     * Re-invitation is handled: if an inactive doctor with the same email
     * already exists, their token is regenerated.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $isDoctor = ($data['role'] ?? '') === 'doctor';
            $rawToken = null;

            if ($isDoctor) {
                // Re-invitation: reuse existing inactive doctor with same email
                $existingUser = User::where('email', $data['email'])
                    ->where('role', 'doctor')
                    ->where(fn ($q) => $q->where('is_active', false)->orWhereNull('is_active'))
                    ->first();

                if ($existingUser !== null) {
                    $user = $existingUser;
                    $rawToken = $user->generateInvitationToken();
                    $user->save();
                    $doctor = $user->doctor;
                } else {
                    // Create new doctor (no password — doctor sets via invitation)
                    $data['password'] = null;
                    $data['is_active'] = false;
                    $user = static::getModel()::create($data);
                    $rawToken = $user->generateInvitationToken();
                    $user->save();

                    // Create Doctor profile
                    $doctor = Doctor::create([
                        'user_id' => $user->id,
                        'specialty_id' => $data['specialty_id'] ?? null,
                        'license_number' => $data['license_number'] ?? null,
                    ]);
                }

                // Queue invitation email
                $expiresAt = now()->addDays(7);
                Mail::to($user->email)->queue(new InvitationActivated($user, $rawToken, $expiresAt));

                // Audit log
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'actor_type' => 'admin',
                    'action' => 'doctor.created',
                    'subject_type' => Doctor::class,
                    'subject_id' => $doctor->id,
                    'metadata' => [
                        'user_id' => $user->id,
                        'specialty_id' => $doctor->specialty_id,
                        'license_number' => $doctor->license_number,
                        'invitation_sent' => true,
                    ],
                    'ip_address' => request()?->ip(),
                ]);

                return $user;
            }

            // Non-doctor: create normally
            $user = static::getModel()::create($data);

            AuditLog::create([
                'user_id' => auth()->id(),
                'actor_type' => 'admin',
                'action' => 'user.created',
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'metadata' => [
                    'role' => $user->role,
                    'email' => $user->email,
                ],
                'ip_address' => request()?->ip(),
            ]);

            return $user;
        });
    }

    /**
     * Redirect to the list after a successful create so the admin can
     * immediately see the new user/doctor row.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}