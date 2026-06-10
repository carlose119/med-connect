<?php

namespace App\Filament\Resources\ClinicalRecords\Pages;

use App\Models\MedicalHistory;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;

class EditMedicalHistory extends EditRecord
{
    protected static string $resource = \App\Filament\Resources\ClinicalRecords\MedicalHistoryResource::class;

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