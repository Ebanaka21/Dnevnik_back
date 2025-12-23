<?php

namespace App\Filament\Resources\PerformanceReportResource\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Schema;

class PerformanceReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Ученик')
                    ->relationship('student', 'full_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->full_name ?? 'Неизвестный ученик')
                    ->searchable(['name', 'surname', 'second_name'])
                    ->preload()
                    ->required(),

                Select::make('school_class_id')
                    ->label('Класс')
                    ->relationship('schoolClass', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->full_name ?? 'Неизвестный класс')
                    ->searchable(['name', 'year', 'letter'])
                    ->preload()
                    ->required(),

                Select::make('report_type')
                    ->label('Тип отчета')
                    ->options([
                        'performance' => 'Отчет по успеваемости',
                        'attendance' => 'Отчет по посещаемости',
                        'both' => 'Оба отчета'
                    ])
                    ->default('performance')
                    ->required(),

                DatePicker::make('period_start')
                    ->label('Начало периода')
                    ->required()
                    ->native(false)
                    ->displayFormat('d.m.Y'),

                DatePicker::make('period_end')
                    ->label('Конец периода')
                    ->required()
                    ->after('period_start')
                    ->native(false)
                    ->displayFormat('d.m.Y'),
            ]);
    }
}
