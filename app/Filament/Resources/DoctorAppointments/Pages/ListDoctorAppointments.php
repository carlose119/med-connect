<?php

namespace App\Filament\Resources\DoctorAppointments\Pages;

use App\Filament\Resources\DoctorAppointments\DoctorAppointmentResource;
use Filament\Resources\Pages\ListRecords;

class ListDoctorAppointments extends ListRecords
{
    protected static string $resource = DoctorAppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
