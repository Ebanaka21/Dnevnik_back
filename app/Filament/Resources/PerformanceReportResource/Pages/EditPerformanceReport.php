<?php

namespace App\Filament\Resources\PerformanceReportResource\Pages;

use App\Filament\Resources\PerformanceReportResource;
use App\Models\PerformanceReport;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EditPerformanceReport extends EditRecord
{
    protected static string $resource = PerformanceReportResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Отчеты по успеваемости нельзя редактировать вручную
        // Они генерируются автоматически на основе данных оценок и посещаемости

        Notification::make()
            ->title('Редактирование отчетов недоступно')
            ->body('Отчеты по успеваемости генерируются автоматически и не подлежат редактированию')
            ->warning()
            ->send();

        // Возвращаем данные без изменений
        return $data;
    }

    protected function afterSave(): void
    {
        // Отчеты не подлежат редактированию
    }
}
