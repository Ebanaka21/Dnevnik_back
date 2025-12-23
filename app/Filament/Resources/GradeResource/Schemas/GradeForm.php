<?php

namespace App\Filament\Resources\GradeResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GradeForm
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

                Select::make('grade_type_id')
                    ->label('Тип оценки')
                    ->relationship('gradeType', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('teacher_id')
                    ->label('Учитель')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('value')
                    ->label('Оценка')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5)
                    ->required()
                    ->helperText('От 1 до 5 баллов'),

                DatePicker::make('date')
                    ->label('Дата выставления')
                    ->default(now())
                    ->required(),

                TextInput::make('description')
                    ->label('Описание работы')
                    ->maxLength(255)
                    ->helperText('Например: Контрольная работа по теме "Квадратные уравнения"'),

                Textarea::make('comment')
                    ->label('Комментарий')
                    ->rows(3)
                    ->helperText('Комментарий учителя к оценке'),

                Toggle::make('is_final')
                    ->label('Итоговая оценка')
                    ->helperText('Отметить как итоговую оценку за четверть/полугодие'),
            ]);
    }
}
