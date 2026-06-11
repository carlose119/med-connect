<?php

namespace App\Filament\Resources\ClinicalRecords;

use App\Filament\Resources\ClinicalRecords\Pages\ListPrescriptions;
use App\Filament\Resources\ClinicalRecords\Pages\ViewPrescription;
use App\Filament\Resources\ClinicalRecords\Pages\EditPrescription;
use App\Filament\Resources\ClinicalRecords\RelationManagers\PrescriptionItemsRelationManager;
use App\Filament\Resources\ClinicalRecords\Schemas\PrescriptionForm;
use App\Filament\Resources\ClinicalRecords\Tables\PrescriptionsTable;
use App\Models\Prescription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PrescriptionResource extends Resource
{
    protected static ?string $model = Prescription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string | \UnitEnum | null $navigationGroup = 'Clinical';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PrescriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrescriptionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                auth()->user()?->isDoctor(),
                fn (Builder $q) => $q->where('doctor_id', auth()->user()->doctor->id),
            )
            ->with(['patient.user', 'doctor.user']);
    }

    public static function getRelations(): array
    {
        return [
            PrescriptionItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrescriptions::route('/'),
            'view' => ViewPrescription::route('/{record}'),
            'edit' => EditPrescription::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        return $user?->isDoctor() || $user?->isAdmin();
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}