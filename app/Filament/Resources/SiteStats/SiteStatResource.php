<?php

namespace App\Filament\Resources\SiteStats;

use App\Filament\Resources\SiteStats\Pages\CreateSiteStat;
use App\Filament\Resources\SiteStats\Pages\EditSiteStat;
use App\Filament\Resources\SiteStats\Pages\ListSiteStats;
use App\Filament\Resources\SiteStats\Schemas\SiteStatForm;
use App\Filament\Resources\SiteStats\Tables\SiteStatsTable;
use App\Models\SiteStat;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SiteStatResource extends Resource
{
    protected static ?string $model = SiteStat::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Trust figures';

    protected static ?string $modelLabel = 'trust figure';

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return SiteStatForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteStatsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteStats::route('/'),
            'create' => CreateSiteStat::route('/create'),
            'edit' => EditSiteStat::route('/{record}/edit'),
        ];
    }
}
