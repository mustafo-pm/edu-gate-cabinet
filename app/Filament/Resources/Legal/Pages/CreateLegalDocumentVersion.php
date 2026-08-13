<?php

namespace App\Filament\Resources\Legal\Pages;

use App\Filament\Resources\Legal\LegalDocumentVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLegalDocumentVersion extends CreateRecord
{
    protected static string $resource = LegalDocumentVersionResource::class;
}
