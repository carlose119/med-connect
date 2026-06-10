<?php

namespace App\Filament\Resources\DoctorScheduleOverrides\Pages;

use App\Filament\Resources\DoctorScheduleOverrides\DoctorScheduleOverrideResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDoctorScheduleOverride extends CreateRecord
{
    protected static string $resource = DoctorScheduleOverrideResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['doctor_id'] ??= auth()->user()?->doctor?->id;

        return $data;
    }
}
