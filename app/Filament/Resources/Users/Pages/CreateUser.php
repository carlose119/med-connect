<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Mail\DoctorAccountCreated;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
     * For doctors, a temporary password is auto-generated and the account
     * activation email is dispatched (queued) after the transaction commits.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $tempPassword = null;

        // Auto-generate temp password for doctors if none provided
        if (($data['role'] ?? '') === 'doctor' && empty($data['password'])) {
            $tempPassword = Str::upper(Str::random(8));
            $data['password'] = bcrypt($tempPassword);
        }

        return DB::transaction(function () use ($data, $tempPassword) {
            /** @var User $user */
            $user = static::getModel()::create($data);

            $action = 'user.created';
            $subjectType = User::class;
            $subjectId = $user->id;
            $metadata = [
                'role' => $user->role,
                'email' => $user->email,
            ];

            if ($user->role === 'doctor') {
                $doctor = Doctor::create([
                    'user_id' => $user->id,
                    'specialty_id' => $data['specialty_id'] ?? null,
                    'license_number' => $data['license_number'] ?? null,
                ]);
                $action = 'doctor.created';
                $subjectType = Doctor::class;
                $subjectId = $doctor->id;
                $metadata = [
                    'user_id' => $user->id,
                    'specialty_id' => $doctor->specialty_id,
                    'license_number' => $doctor->license_number,
                    'temp_password_sent' => $tempPassword !== null,
                ];

                // Send the doctor account activation email (queued)
                if ($tempPassword) {
                    Mail::to($user->email)->queue(new DoctorAccountCreated($user, $tempPassword));
                }
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'actor_type' => 'admin',
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'metadata' => $metadata,
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
