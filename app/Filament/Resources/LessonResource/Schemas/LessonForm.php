<?php

namespace App\Filament\Resources\LessonResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('subject_id')->label('Предмет')->relationship('subject', 'name')->required(),
            Select::make('school_class_id')->label('Класс')->relationship('schoolClass', 'name')->required(),
            Select::make('teacher_id')->label('Учитель')->relationship('teacher', 'name')->required(),
            TextInput::make('title')->label('Название урока')->required(),
            Textarea::make('description')->label('Описание')->rows(3),
            DatePicker::make('date')->label('Дата')->required(),
            TextInput::make('lesson_number')->label('Номер урока')->numeric()->required(),
            TimePicker::make('start_time')->label('Время начала'),
            TimePicker::make('end_time')->label('Время окончания'),
            Textarea::make('homework_assignment')->label('Домашнее задание')->rows(3),
        ]);
    }
}
