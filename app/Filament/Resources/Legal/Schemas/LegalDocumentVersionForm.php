<?php

namespace App\Filament\Resources\Legal\Schemas;

use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class LegalDocumentVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        // Every field goes read-only the moment the version is published. The
        // model would refuse the save anyway; disabling the inputs means an
        // admin finds out before they retype a page of text.
        $frozen = fn (?LegalDocumentVersion $record) => $record?->published_at !== null;

        return $schema->components([
            Section::make('Version')
                ->columns(3)
                ->schema([
                    Select::make('legal_document_id')
                        ->label('Document')
                        ->options(fn () => LegalDocument::orderBy('slug')->pluck('slug', 'id'))
                        ->required()
                        ->disabled(fn (?LegalDocumentVersion $record) => $record !== null)
                        ->helperText('Fixed once created — a version belongs to one document.'),

                    TextInput::make('version')
                        ->disabled()
                        ->placeholder('assigned on save')
                        ->helperText('Next number for this document.'),

                    DatePicker::make('effective_from')
                        ->native(false)
                        ->disabled($frozen)
                        ->helperText('When it starts binding. A future date lets you announce a change before it applies. Empty means immediately on publication.'),

                    TextInput::make('change_note')
                        ->columnSpanFull()
                        ->disabled($frozen)
                        ->helperText('Internal only, never shown publicly. e.g. "corrected bank details", "counsel review".'),
                ]),

            Section::make('Text')
                ->description('Markdown. Headings with ##, lists with -. HTML is stripped when rendered, so it will not work.')
                ->schema([
                    Tabs::make('locales')->tabs([
                        Tab::make('Uzbek')->schema([
                            TextInput::make('title_uz')->label('Title')->disabled($frozen),
                            MarkdownEditor::make('body_uz')->label('Body')->disabled($frozen),
                        ]),
                        Tab::make('Russian')->schema([
                            TextInput::make('title_ru')->label('Title')->disabled($frozen),
                            MarkdownEditor::make('body_ru')->label('Body')->disabled($frozen),
                        ]),
                        Tab::make('English')->schema([
                            TextInput::make('title_en')->label('Title')->disabled($frozen),
                            MarkdownEditor::make('body_en')->label('Body')->disabled($frozen),
                        ]),
                    ])->columnSpanFull(),
                ]),
        ]);
    }
}
