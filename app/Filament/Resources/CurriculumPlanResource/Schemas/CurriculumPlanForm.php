<?php

namespace App\Filament\Resources\CurriculumPlanResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CurriculumPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('school_class_id')->label('Класс')->relationship('schoolClass', 'name')->required(),
            Select::make('subject_id')->label('Предмет')->relationship('subject', 'name')->required(),
            Select::make('teacher_id')
                ->label('Учитель')
                ->relationship('teacher', 'id')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                ->nullable(),
            TextInput::make('academic_year')->label('Академический год')->required(),
            TextInput::make('hours_per_week')->label('Часов в неделю')->numeric()->minValue(1)->required(),
        ]);
    }
}
