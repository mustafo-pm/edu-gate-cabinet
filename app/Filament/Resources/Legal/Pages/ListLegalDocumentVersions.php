<?php

namespace App\Filament\Resources\Legal\Pages;

use App\Filament\Resources\Legal\LegalDocumentVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListLegalDocumentVersions extends ListRecords
{
    protected static string $resource = LegalDocumentVersionResource::class;
}
