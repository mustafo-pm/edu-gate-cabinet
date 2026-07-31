<?php

namespace App\Filament\Resources\CommissionRules\Schemas;

use App\Enums\CommissionScope;
use App\Enums\MerchantType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CommissionRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('scope')
                    ->options(CommissionScope::class)
                    ->required(),
                Select::make('merchant_id')
                    ->relationship('merchant', 'name'),
                Select::make('psp_id')
                    ->relationship('psp', 'name'),
                Select::make('category')
                    ->options(MerchantType::class),
                TextInput::make('rate_bps')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('fixed_fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
