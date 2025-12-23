<?php

namespace App\Filament\Resources\AttendanceResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label('Ученик')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('subject_id')
                    ->label('Предмет')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('teacher_id')
                    ->label('Учитель')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                DatePicker::make('date')
                    ->label('Дата')
                    ->default(now())
                    ->required(),

                Select::make('status')
                    ->label('Статус')
                    ->options([
                        'present' => 'Присутствует',
                        'absent' => 'Отсутствует',
                        'late' => 'Опоздал',
                        'sick' => 'Болеет',
                        'excused' => 'Уважительная причина',
                    ])
                    ->default('present')
                    ->required(),

                TimePicker::make('arrival_time')
                    ->label('Время прихода')
                    ->helperText('Время прихода на урок (для опоздавших)'),

                Textarea::make('reason')
                    ->label('Причина отсутствия/опоздания')
                    ->rows(3)
                    ->helperText('Укажите причину, если ученик отсутствует или опоздал'),
            ]);
    }
}
