<?php

namespace App\Filament\Resources\Amenities\Schemas;

use App\Models\Amenity;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AmenityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Wi-Fi, Кондиционер, Телевизор...'),

                        TextInput::make('icon_class')
                            ->label('Класс иконки')
                            ->maxLength(255)
                            ->placeholder('bx bx-wifi, bx bx-tv...')
                            ->helperText('Класс иконки из Boxicons (bx) или других библиотек иконок'),
                    ]),

                Section::make('Настройки отображения')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Активно')
                            ->default(true)
                            ->helperText('Неактивные преимущества не будут отображаться в формах и на сайте'),

                        TextInput::make('sort_order')
                            ->label('Порядок сортировки')
                            ->numeric()
                            ->default(0)
                            ->helperText('Преимущества будут отображаться в порядке возрастания этого числа'),
                    ]),
            ]);
    }
}
