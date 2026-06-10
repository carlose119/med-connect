<?php

namespace App\Filament\Resources\AdminAudit\Schemas;

use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Audit Entry')
                    ->schema([
                        TextInput::make('user_name')
                            ->label('Admin')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->user?->name ?? '—'),
                        TextInput::make('actor_type')
                            ->label('Actor Type')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => ucfirst($record?->actor_type ?? '—')),
                        TextInput::make('action')
                            ->label('Action')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->action ?? '—'),
                        TextInput::make('subject_type')
                            ->label('Subject Type')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->subject_type ?? '—'),
                        TextInput::make('subject_id')
                            ->label('Subject ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => (string) ($record?->subject_id ?? '—')),
                        TextInput::make('ip_address')
                            ->label('IP Address')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->ip_address ?? '—'),
                        TextInput::make('metadata')
                            ->label('Metadata')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->metadata ? json_encode($record->metadata, JSON_PRETTY_PRINT) : '{}'),
                        TextInput::make('created_at')
                            ->label('Timestamp')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                if (!$record?->created_at) {
                                    return '—';
                                }
                                // created_at may be string or Carbon depending on DB driver
                                $ts = $record->created_at;
                                return is_object($ts) && method_exists($ts, 'format')
                                    ? $ts->format('Y-m-d H:i:s')
                                    : (string) $ts;
                            }),
                    ]),
            ]);
    }
}