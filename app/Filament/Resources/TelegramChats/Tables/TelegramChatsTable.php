<?php

namespace App\Filament\Resources\TelegramChats\Tables;

use App\Models\TelegramChat;
use App\Support\Telegram;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelegramChatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('title')->label('Chat')->searchable()->placeholder('—'),
                TextColumn::make('chat_id')->label('Chat ID')->fontFamily('mono')->searchable(),
                TextColumn::make('type')->badge()->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('last_sent_at')->label('Last sent')->since()->placeholder('never'),
                TextColumn::make('last_error')->label('Last error')->limit(30)->placeholder('—')->color('danger'),
            ])
            ->headerActions([
                // Telegram only reveals a chat once the bot has seen an update
                // from it, so this is a pull, not a push.
                Action::make('discover')
                    ->label('Discover chats')
                    ->icon('heroicon-o-magnifying-glass')
                    ->action(function (): void {
                        $found = Telegram::discoverChats();

                        if (! $found) {
                            Notification::make()
                                ->title('No chats found')
                                ->body('Post "/start@edu_gate_bot" in the group, then try again.')
                                ->warning()->persistent()->send();

                            return;
                        }

                        $new = 0;
                        foreach ($found as $chat) {
                            $record = TelegramChat::updateOrCreate(
                                ['chat_id' => $chat['chat_id']],
                                ['title' => $chat['title'], 'type' => $chat['type']],
                            );
                            $new += $record->wasRecentlyCreated ? 1 : 0;
                        }

                        Notification::make()
                            ->title(count($found).' chat(s) found'.($new ? ", {$new} new" : ''))
                            ->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Send test')
                    ->icon('heroicon-o-paper-airplane')
                    ->action(function (TelegramChat $record): void {
                        $ok = Telegram::send(
                            $record->chat_id,
                            "✅ <b>EduGate test alert</b>\n\nIf you can read this, alerts are working.",
                            'test',
                        );

                        Notification::make()
                            ->title($ok ? 'Test message sent' : 'Send failed — check the alert log')
                            ->{$ok ? 'success' : 'danger'}()
                            ->send();
                    }),
                EditAction::make(),
            ]);
    }
}
