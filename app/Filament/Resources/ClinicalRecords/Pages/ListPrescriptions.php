<?php

namespace App\Filament\Resources\ClinicalRecords\Pages;

use Filament\Resources\Pages\ListRecords;

class ListPrescriptions extends ListRecords
{
    protected static string $resource = \App\Filament\Resources\ClinicalRecords\PrescriptionResource::class;
}