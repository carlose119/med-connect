<?php

namespace App\Filament\Resources\ClinicalRecords;

use App\Filament\Resources\ClinicalRecords\Pages\EditMedicalHistory;
use App\Filament\Resources\ClinicalRecords\Pages\ListMedicalHistories;
use App\Filament\Resources\ClinicalRecords\RelationManagers\MedicalNotesRelationManager;
use App\Filament\Resources\ClinicalRecords\Schemas\MedicalHistoryForm;
use App\Filament\Resources\ClinicalRecords\Tables\MedicalHistoriesTable;
use App\Models\MedicalHistory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MedicalHistoryResource extends Resource
{
    protected static ?string $model = MedicalHistory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string | \UnitEnum | null $navigationGroup = 'Clinical';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return MedicalHistoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicalHistoriesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                auth()->user()?->isDoctor(),
                fn (Builder $q) => $q->whereHas(
                    'patient.appointments',
                    fn (Builder $q) => $q->where('doctor_id', auth()->user()->doctor->id),
                ),
            )
            ->with(['patient.user', 'primaryDoctor']);
    }

    public static function getRelations(): array
    {
        return [
            MedicalNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalHistories::route('/'),
            'edit' => EditMedicalHistory::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}