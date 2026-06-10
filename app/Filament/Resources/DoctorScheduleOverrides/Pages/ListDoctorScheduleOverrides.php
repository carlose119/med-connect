<?php

namespace App\Filament\Resources\DoctorScheduleOverrides\Pages;

use App\Filament\Resources\DoctorScheduleOverrides\DoctorScheduleOverrideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDoctorScheduleOverrides extends ListRecords
{
    protected static string $resource = DoctorScheduleOverrideResource::class;

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('doctor_id', auth()->user()?->doctor?->id);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
