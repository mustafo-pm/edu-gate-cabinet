<?php

namespace App\Filament\Resources\Legal\Pages;

use App\Filament\Resources\Legal\LegalDocumentResource;
use Filament\Resources\Pages\ListRecords;

class ListLegalDocuments extends ListRecords
{
    protected static string $resource = LegalDocumentResource::class;
}
