<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Doctor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Handle the user creation. When the chosen role is `doctor`,
     * the User and the linked Doctor profile are written in a single
     * transaction so we never end up with an orphaned user account.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $user = static::getModel()::create($data);

            if (($data['role'] ?? null) === 'doctor') {
                Doctor::create([
                    'user_id' => $user->id,
                    'specialty_id' => $data['specialty_id'] ?? null,
                    'license_number' => $data['license_number'] ?? null,
                ]);
            }

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
