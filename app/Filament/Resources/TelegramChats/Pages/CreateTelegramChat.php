<?php

namespace App\Filament\Resources\TelegramChats\Pages;

use App\Filament\Resources\TelegramChats\TelegramChatResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTelegramChat extends CreateRecord
{
    protected static string $resource = TelegramChatResource::class;
}
