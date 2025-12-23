<?php

namespace App\Filament\Resources\PerformanceReportResource\Pages;

use App\Filament\Resources\PerformanceReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceReports extends ListRecords
{
    protected static string $resource = PerformanceReportResource::class;

    protected function getHeaderActions(): array
    {
        // Отключаем создание через форму (только через API)
        return [];
    }

    /**
     * Добавляем информативный инфоблок
     */
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\PerformanceReportResource\Widgets\PerformanceReportsInfoWidget::class,
        ];
    }
}
