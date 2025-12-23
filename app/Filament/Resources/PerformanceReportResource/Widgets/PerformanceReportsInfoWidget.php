<?php

namespace App\Filament\Resources\PerformanceReportResource\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class PerformanceReportsInfoWidget extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public function render(): View
    {
        return view('filament.resources.performance-report-resource.widgets.performance-reports-info-widget');
    }
}
