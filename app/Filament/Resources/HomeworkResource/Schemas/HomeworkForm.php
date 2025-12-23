<?php

namespace App\Filament\Resources\HomeworkResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HomeworkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subject_id')
                    ->label('Предмет')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('school_class_id')
                    ->label('Класс')
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('teacher_id')
                    ->label('Учитель')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('title')
                    ->label('Заголовок задания')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Краткое описание задания'),

                Textarea::make('description')
                    ->label('Подробное описание')
                    ->rows(5)
                    ->required()
                    ->helperText('Детальное описание домашнего задания'),

                DatePicker::make('assigned_date')
                    ->label('Дата выдачи')
                    ->default(now())
                    ->required(),

                DatePicker::make('due_date')
                    ->label('Срок сдачи')
                    ->required()
                    ->after('assigned_date'),

                TextInput::make('max_points')
                    ->label('Максимальный балл')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(5),

                Toggle::make('is_active')
                    ->label('Активное')
                    ->default(true)
                    ->helperText('Отображать задание ученикам'),
            ]);
    }
}
