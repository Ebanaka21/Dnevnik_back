<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerformanceReportResource\Pages\CreatePerformanceReport;
use App\Filament\Resources\PerformanceReportResource\Pages\EditPerformanceReport;
use App\Filament\Resources\PerformanceReportResource\Pages\ListPerformanceReports;
use App\Filament\Resources\PerformanceReportResource\Schemas\PerformanceReportForm;
use App\Filament\Resources\PerformanceReportResource\Tables\PerformanceReportsTable;
use App\Models\PerformanceReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceReportResource extends Resource
{
    protected static ?string $model = PerformanceReport::class;

    protected static ?string $resource = 'performance-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $pluralModelLabel = 'Отчеты об успеваемости';

    protected static ?string $modelLabel = 'отчет об успеваемости';

    protected static ?string $navigationLabel = 'Отчеты об успеваемости';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return PerformanceReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Включаем создание через форму
     */
    public static function canCreate(): bool
    {
        return true;
    }

    /**
     * Включаем редактирование
     */
    public static function canEdit($record): bool
    {
        return true;
    }

    /**
     * Включаем маршруты создания и редактирования
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPerformanceReports::route('/'),
            'create' => CreatePerformanceReport::route('/create'),
            'edit' => EditPerformanceReport::route('/{record}/edit'),
        ];
    }

    /**
     * Какие действия показывать в навигации
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
