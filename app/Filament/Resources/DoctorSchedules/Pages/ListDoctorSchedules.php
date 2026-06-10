<?php

namespace App\Filament\Resources\DoctorSchedules\Pages;

use App\Filament\Resources\DoctorSchedules\DoctorScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDoctorSchedules extends ListRecords
{
    protected static string $resource = DoctorScheduleResource::class;

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
