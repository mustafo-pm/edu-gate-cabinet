<?php

namespace App\Filament\Resources\Legal\Pages;

use App\Filament\Resources\Legal\LegalDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLegalDocument extends CreateRecord
{
    protected static string $resource = LegalDocumentResource::class;
}
