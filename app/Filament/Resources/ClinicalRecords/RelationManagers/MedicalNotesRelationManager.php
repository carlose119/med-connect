<?php

namespace App\Filament\Resources\ClinicalRecords\RelationManagers;

use App\Models\Appointment;
use App\Models\MedicalAttachment;
use App\Models\MedicalNote;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextArea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MedicalNotesRelationManager extends RelationManager
{
    public static function getRelationshipName(): string
    {
        return 'notes';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextArea::make('symptoms')
                    ->label('Symptoms')
                    ->required(),
                TextArea::make('physical_exam')
                    ->label('Physical Exam')
                    ->nullable(),
                TextArea::make('diagnosis')
                    ->label('Diagnosis')
                    ->required(),
                TextArea::make('treatment_notes')
                    ->label('Treatment Notes')
                    ->nullable(),
                Select::make('appointment_id')
                    ->label('Appointment')
                    ->options(function (): array {
                        $ownerRecord = $this->getOwnerRecord();

                        return Appointment::query()
                            ->where('patient_id', $ownerRecord->patient_id)
                            ->where('state', 'completed')
                            ->pluck('id', 'id')
                            ->toArray();
                    })
                    ->required(),
                Hidden::make('doctor_id')
                    ->default(fn () => auth()->user()?->doctor?->id),
            ]);
    }

    public function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            \Filament\Infolists\Components\RepeatableEntry::make('attachments')
                ->label('Attachments')
                ->schema([
                    \Filament\Infolists\Components\TextEntry::make('file_name'),
                    \Filament\Infolists\Components\TextEntry::make('mime_type'),
                    \Filament\Infolists\Components\TextEntry::make('size_bytes')
                        ->formatStateUsing(fn ($state) => $this->formatBytes($state)),
                    \Filament\Infolists\Components\TextEntry::make('uploader.name')
                        ->label('Uploaded By'),
                    \Filament\Infolists\Components\TextEntry::make('created_at')
                        ->label('Uploaded At')
                        ->dateTime(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('doctor.user.name')
                    ->label('Doctor'),
                TextColumn::make('appointment.start_time')
                    ->label('Date')
                    ->date(),
                TextColumn::make('symptoms')
                    ->limit(50),
                TextColumn::make('diagnosis')
                    ->limit(50),
                IconColumn::make('corrects_note_id')
                    ->label('Amended')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->corrects_note_id !== null),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    protected function getCreateAuthorizationResponse(): \Illuminate\Auth\Access\Response
    {
        return \Illuminate\Auth\Access\Response::allow();
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}