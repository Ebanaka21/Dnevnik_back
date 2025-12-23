<?php

namespace App\Filament\Resources\PerformanceReportResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class PerformanceReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('№')
                    ->sortable()
                    ->icon('heroicon-o-hashtag')
                    ->toggleable(),

                TextColumn::make('student.full_name')
                    ->label('Ученик')
                    ->searchable(['name', 'surname', 'second_name'])
                    ->icon('heroicon-o-user')
                    ->getStateUsing(fn ($record) => $record->student?->full_name ?? 'Неизвестный ученик')
                    ->default('Неизвестный ученик'),

                TextColumn::make('schoolClass.full_name')
                    ->label('Класс')
                    ->searchable(['name', 'year', 'letter'])
                    ->icon('heroicon-o-building-office-2')
                    ->getStateUsing(fn ($record) => $record->schoolClass?->full_name ?? 'Неизвестный класс')
                    ->default('Неизвестный класс'),

                TextColumn::make('average_grade')
                    ->label('Средняя оценка')
                    ->sortable()
                    ->icon('heroicon-o-chart-bar')
                    ->getStateUsing(fn ($record) =>
                        $record->average_grade ? number_format($record->average_grade, 2, ',', ' ') : '-'
                    ),

                TextColumn::make('attendance_percentage')
                    ->label('Посещаемость')
                    ->sortable()
                    ->icon('heroicon-o-users')
                    ->getStateUsing(fn ($record) =>
                        $record->attendance_percentage ? number_format($record->attendance_percentage, 1, ',', ' ') . '%' : '-'
                    ),

                TextColumn::make('total_grades')
                    ->label('Всего оценок')
                    ->sortable()
                    ->icon('heroicon-o-clipboard-document-list')
                    ->default(0),

                TextColumn::make('period_start')
                    ->label('Начало периода')
                    ->date('d.m.Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar-days')
                    ->default('-'),

                TextColumn::make('period_end')
                    ->label('Конец периода')
                    ->date('d.m.Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar-days')
                    ->default('-'),

                TextColumn::make('period')
                    ->label('Период')
                    ->getStateUsing(function ($record) {
                        if (!$record->period_start || !$record->period_end) {
                            return '-';
                        }
                        return sprintf('%s - %s',
                            $record->period_start->format('d.m.Y'),
                            $record->period_end->format('d.m.Y')
                        );
                    })
                    ->icon('heroicon-o-clock'),

                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-clock')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('student_id')
                    ->label('Ученик')
                    ->relationship('student', 'surname')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name ?? 'Неизвестный ученик')
                    ->searchable(['name', 'surname', 'second_name'])
                    ->preload()
                    ->multiple(),

                SelectFilter::make('school_class_id')
                    ->label('Класс')
                    ->relationship('schoolClass', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name ?? 'Неизвестный класс')
                    ->searchable(['name', 'year', 'letter'])
                    ->preload()
                    ->multiple(),

                SelectFilter::make('period_year')
                    ->label('Год периода')
                    ->options(function () {
                        // Проверяем тип БД
                        $driver = config('database.default');
                        $connection = config("database.connections.{$driver}.driver");

                        if ($connection === 'sqlite') {
                            // Для SQLite используем strftime
                            return \App\Models\PerformanceReport::selectRaw("strftime('%Y', period_start) as year")
                                ->distinct()
                                ->whereNotNull('period_start')
                                ->pluck('year', 'year')
                                ->sortDesc();
                        } else {
                            // Для MySQL/PostgreSQL используем YEAR/EXTRACT
                            return \App\Models\PerformanceReport::selectRaw('YEAR(period_start) as year')
                                ->distinct()
                                ->whereNotNull('period_start')
                                ->pluck('year', 'year')
                                ->sortDesc();
                        }
                    })
                    ->query(function ($query, $data) {
                        if (!empty($data['value'])) {
                            $driver = config('database.default');
                            $connection = config("database.connections.{$driver}.driver");

                            if ($connection === 'sqlite') {
                                // Для SQLite
                                $query->whereRaw("strftime('%Y', period_start) = ?", [$data['value']]);
                            } else {
                                // Для MySQL/PostgreSQL
                                $query->whereYear('period_start', $data['value']);
                            }
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->icon('heroicon-o-pencil'),

                Action::make('export_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->color('danger')
                    ->action(function ($record) {
                        static::exportToPDF($record);
                    }),

                Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function ($record) {
                        static::exportToExcel($record);
                    }),

                DeleteAction::make()
                    ->icon('heroicon-o-trash'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->icon('heroicon-o-trash'),

                    Action::make('bulk_export_pdf')
                        ->label('Экспорт PDF')
                        ->icon('heroicon-o-document-text')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                static::exportToPDF($record);
                            }
                        }),

                    Action::make('bulk_export_excel')
                        ->label('Экспорт Excel')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                static::exportToExcel($record);
                            }
                        }),
                ]),
            ])
            ->defaultSort('period_start', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    protected static function exportToPDF($record)
    {
        try {
            if (!$record->student_id || !$record->period_start || !$record->period_end) {
                throw new \Exception('Недостаточно данных для экспорта отчета');
            }

            $reportController = new \App\Http\Controllers\Api\ReportController();
            $request = \Illuminate\Http\Request::create('', 'POST', [
                'report_type' => 'performance',
                'student_id' => $record->student_id,
                'period_start' => $record->period_start->format('Y-m-d'),
                'period_end' => $record->period_end->format('Y-m-d'),
            ]);

            $response = $reportController->exportToPDF($request);

            if ($response->getStatusCode() && $response->getStatusCode() < 400) {
                Notification::make()
                    ->title('PDF отчет создан')
                    ->body('Отчет успешно экспортирован в PDF')
                    ->success()
                    ->send();
            } else {
                throw new \Exception('Ошибка при экспорте PDF');
            }

        } catch (\Exception $e) {
            Log::error('Error exporting PDF', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Ошибка экспорта PDF')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected static function exportToExcel($record)
    {
        try {
            if (!$record->student_id || !$record->period_start || !$record->period_end) {
                throw new \Exception('Недостаточно данных для экспорта отчета');
            }

            $reportController = new \App\Http\Controllers\Api\ReportController();
            $request = \Illuminate\Http\Request::create('', 'POST', [
                'report_type' => 'performance',
                'student_id' => $record->student_id,
                'period_start' => $record->period_start->format('Y-m-d'),
                'period_end' => $record->period_end->format('Y-m-d'),
            ]);

            $response = $reportController->exportToExcel($request);

            if ($response->getStatusCode() && $response->getStatusCode() < 400) {
                Notification::make()
                    ->title('Excel отчет создан')
                    ->body('Отчет успешно экспортирован в Excel')
                    ->success()
                    ->send();
            } else {
                throw new \Exception('Ошибка при экспорте Excel');
            }

        } catch (\Exception $e) {
            Log::error('Error exporting Excel', ['error' => $e->getMessage()]);

            Notification::make()
                ->title('Ошибка экспорта Excel')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
