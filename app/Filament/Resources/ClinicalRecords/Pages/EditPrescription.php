<?php

namespace App\Filament\Resources\ClinicalRecords\Pages;

use App\Models\Prescription;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;

class EditPrescription extends EditRecord
{
    protected static string $resource = \App\Filament\Resources\ClinicalRecords\PrescriptionResource::class;

    protected function authorizeAccess(): void
    {
        abort_unless(
            static::getResource()::canViewAny(),
            403,
        );

        $record = $this->getRecord();

        abort_unless(
            auth()->user()->can('view', $record),
            403,
        );
    }
}