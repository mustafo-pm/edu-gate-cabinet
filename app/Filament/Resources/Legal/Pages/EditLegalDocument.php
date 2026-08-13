<?php

namespace App\Filament\Resources\Legal\Pages;

use App\Filament\Resources\Legal\LegalDocumentResource;
use Filament\Resources\Pages\EditRecord;

class EditLegalDocument extends EditRecord
{
    protected static string $resource = LegalDocumentResource::class;
}
