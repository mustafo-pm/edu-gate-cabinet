<?php

namespace App\Filament\Resources\Legal\Pages;

use App\Filament\Resources\Legal\LegalDocumentVersionResource;
use Filament\Resources\Pages\EditRecord;

class EditLegalDocumentVersion extends EditRecord
{
    protected static string $resource = LegalDocumentVersionResource::class;
}
