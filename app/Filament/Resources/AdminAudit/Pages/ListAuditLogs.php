<?php

namespace App\Filament\Resources\AdminAudit\Pages;

use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = \App\Filament\Resources\AdminAudit\AuditLogResource::class;
}