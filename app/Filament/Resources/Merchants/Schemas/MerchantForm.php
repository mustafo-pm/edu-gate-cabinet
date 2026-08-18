<?php

namespace App\Filament\Resources\Merchants\Schemas;

use App\Enums\MerchantStatus;
use App\Enums\MerchantType;
use App\Models\Merchant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class MerchantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->helperText('Internal label, and the fallback when a translation is missing.'),
                    TextInput::make('legal_name')
                        ->helperText('Sent to the bank on settlements.'),

                    TextInput::make('name_uz')->label('Name (Uzbek)'),
                    TextInput::make('name_ru')->label('Name (Russian)'),
                    TextInput::make('name_en')->label('Name (English)'),

                    Select::make('type')->options(MerchantType::class)->required(),
                    Select::make('status')->options(MerchantStatus::class)->default('pending')->required(),
                    TextInput::make('stir')->label('Tax ID (STIR)'),
                ]),

            Section::make('Public profile')
                ->description('What a payer sees. The institution edits these itself in its cabinet.')
                ->columns(2)
                ->schema([
                    TextInput::make('website_url')->url()->label('Website'),
                    TextInput::make('address'),
                    Toggle::make('show_on_receipt')
                        ->label('Logo and website appear on receipts')
                        ->helperText('The institution chooses this; shown here so support can see the current setting.'),
                ]),

            Section::make('Commission and contact')
                ->columns(2)
                ->schema([
                    TextInput::make('commission_bps')->required()->numeric()->default(0)
                        ->label('Commission (bps)')
                        ->helperText('150 = 1.5%.'),
                    TextInput::make('contact_name'),
                    TextInput::make('contact_phone')->tel(),
                    TextInput::make('contact_email')->email(),
                ]),

            /*
             * Settlements no longer read these. An institution may hold several
             * accounts — universities change bank mid-year and run both for a
             * term — so they live in their own table with an approval step, and
             * editing a field here would not change where money goes.
             *
             * Kept visible, read-only, because they are still the fallback for
             * institutions nobody has migrated yet: while merchant_bank_accounts
             * is empty for a merchant, this is the account being paid.
             */
            Section::make('Legacy bank details')
                ->description('Read-only. Money is routed by Banking → Institution accounts.')
                ->collapsed()
                ->columns(3)
                ->schema([
                    Text::make(fn (?Merchant $record) => $record && $record->bankAccounts()->approved()->exists()
                        ? 'An approved account exists, so these fields are ignored.'
                        : 'No approved account yet — these fields are what settlements currently use.')
                        ->columnSpanFull(),

                    TextInput::make('mfo')->disabled()->dehydrated(false),
                    TextInput::make('bank_account')->disabled()->dehydrated(false),
                    TextInput::make('bank_name')->disabled()->dehydrated(false),
                ]),
        ]);
    }
}
