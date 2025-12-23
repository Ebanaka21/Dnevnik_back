<?php

namespace App\Filament\Resources\HomeworkSubmissionResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;

class HomeworkSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('homework_id')
                    ->label('Домашнее задание')
                    ->relationship('homework', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('student_id')
                    ->label('Ученик')
                    ->relationship('student', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Textarea::make('content')
                    ->label('Текст ответа')
                    ->rows(5)
                    ->helperText('Ответ на домашнее задание'),

                TextInput::make('file_path')
                    ->label('Путь к файлу')
                    ->helperText('Если есть прикрепленные файлы'),

                DateTimePicker::make('submitted_at')
                    ->label('Дата сдачи')
                    ->default(now()),

                TextInput::make('points_earned')
                    ->label('Полученные баллы')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),

                Textarea::make('teacher_comment')
                    ->label('Комментарий учителя')
                    ->rows(3),

                DateTimePicker::make('reviewed_at')
                    ->label('Дата проверки'),
            ]);
    }
}
