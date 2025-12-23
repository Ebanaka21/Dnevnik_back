<?php

namespace App\Filament\Resources\SchoolClassResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SchoolClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название класса')
                    ->required()
                    ->maxLength(50),

                TextInput::make('year')
                    ->label('Год обучения')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(11),

                TextInput::make('letter')
                    ->label('Буква класса')
                    ->required()
                    ->maxLength(1)
                    ->helperText('Например: А, Б, В'),

                TextInput::make('max_students')
                    ->label('Максимальное количество учеников')
                    ->numeric()
                    ->default(25),

                Textarea::make('description')
                    ->label('Описание класса')
                    ->rows(3),

                TextInput::make('academic_year')
                    ->label('Учебный год')
                    ->required()
                    ->maxLength(20)
                    ->helperText('Например: 2024-2025'),
            ]);
    }
}
