<?php

namespace App\Filament\Resources\GradeTypeResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GradeTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название типа оценки')
                    ->required()
                    ->maxLength(100)
                    ->helperText('Например: Контрольная работа, Тест, Домашняя работа'),

                TextInput::make('short_name')
                    ->label('Краткое название')
                    ->required()
                    ->maxLength(20)
                    ->helperText('Например: КР, Т, ДЗ'),

                Textarea::make('description')
                    ->label('Описание')
                    ->rows(3)
                    ->helperText('Краткое описание типа оценки'),

                TextInput::make('weight')
                    ->label('Вес в общей оценке (%)')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(100)
                    ->helperText('Влияние этого типа оценки на итоговую оценку'),

                Toggle::make('is_active')
                    ->label('Активный')
                    ->default(true)
                    ->helperText('Отображать тип оценки в списках'),
            ]);
    }
}
