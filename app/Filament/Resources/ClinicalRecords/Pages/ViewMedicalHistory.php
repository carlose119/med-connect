<?php

namespace App\Filament\Resources\ClinicalRecords\Pages;

use App\Models\MedicalHistory;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Auth\Access\AuthorizationException;

class ViewMedicalHistory extends ViewRecord
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->url(fn () => static::getResource()::getUrl('edit', ['record' => $this->getRecord()]))
                ->visible(fn () => static::getResource()::canEdit($this->getRecord())),
        ];
    }
}