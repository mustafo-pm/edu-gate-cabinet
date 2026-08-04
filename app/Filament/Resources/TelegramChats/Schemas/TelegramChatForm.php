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
            Section::make('Destination')
                ->description('A destination is a chat, optionally narrowed to one forum topic. Alerts go to every active destination unless a rule targets a specific one.')
                ->columns(2)
                ->schema([
                    TextInput::make('chat_id')
                        ->label('Chat ID')
                        ->required()
                        ->helperText('Group IDs are negative, e.g. -1001234567890. Use "Discover chats & topics" to fill this in.'),
                    TextInput::make('title')->label('Chat name'),

                    TextInput::make('message_thread_id')
                        ->label('Topic ID')
                        ->numeric()
                        ->helperText('Leave empty to post in the group\'s General topic.'),
                    TextInput::make('topic_name')
                        ->label('Topic name')
                        ->helperText('Label only — routing uses the Topic ID.'),

                    TextInput::make('type')->label('Type')->placeholder('group / supergroup / channel'),
                    Toggle::make('is_active')->default(true),
                ]),
        ]);
    }
}
