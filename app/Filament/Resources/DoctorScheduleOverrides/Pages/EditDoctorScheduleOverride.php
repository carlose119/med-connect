<?php

namespace App\Filament\Resources\DoctorScheduleOverrides\Pages;

use App\Filament\Resources\DoctorScheduleOverrides\DoctorScheduleOverrideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDoctorScheduleOverride extends EditRecord
{
    protected static string $resource = DoctorScheduleOverrideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['doctor_id'] ??= auth()->user()?->doctor?->id;

        return $data;
    }
}
