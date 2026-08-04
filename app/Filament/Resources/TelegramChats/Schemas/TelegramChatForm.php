<?php

namespace App\Filament\Resources\TelegramChats\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TelegramChatForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Telegram chat')
                ->description('Alerts are broadcast to every active chat listed here.')
                ->columns(2)
                ->schema([
                    TextInput::make('chat_id')
                        ->label('Chat ID')
                        ->required()
                        ->helperText('Group IDs are negative, e.g. -1001234567890. Use "Discover chats" to fill this automatically.'),
                    TextInput::make('title')->label('Name'),
                    TextInput::make('type')->label('Type')->placeholder('group / supergroup / channel'),
                    Toggle::make('is_active')->default(true),
                ]),
        ]);
    }
}
