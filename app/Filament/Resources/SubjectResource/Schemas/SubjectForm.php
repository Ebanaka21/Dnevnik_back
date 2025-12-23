<?php

namespace App\Filament\Resources\SubjectResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название предмета')
                    ->required()
                    ->maxLength(100),

                TextInput::make('short_name')
                    ->label('Краткое название')
                    ->required()
                    ->maxLength(20)
                    ->helperText('Например: МАТ, ФИЗ, ЛИТ'),

                Textarea::make('description')
                    ->label('Описание предмета')
                    ->rows(3)
                    ->helperText('Краткое описание содержания предмета'),

                TextInput::make('subject_code')
                    ->label('Код предмета')
                    ->required()
                    ->maxLength(10)
                    ->helperText('Например: MATH, PHYS, LIT'),

                TextInput::make('hours_per_week')
                    ->label('Часов в неделю')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10)
                    ->default(1),

                Toggle::make('is_active')
                    ->label('Активный')
                    ->default(true)
                    ->helperText('Отображать предмет в списках'),
            ]);
    }
}
