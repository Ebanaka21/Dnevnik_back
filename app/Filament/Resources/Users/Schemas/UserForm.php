<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Имя')
                    ->required()
                    ->maxLength(255),

                TextInput::make('surname')
                    ->label('Фамилия')
                    ->required()
                    ->maxLength(255),

                TextInput::make('second_name')
                    ->label('Отчество')
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                TextInput::make('phone')
                    ->label('Телефон')
                    ->tel(),

                DatePicker::make('birthday')
                    ->label('Дата рождения'),

                TextInput::make('gender')
                    ->label('Пол')
                    ->placeholder('male | female'),

                Select::make('role')
                    ->label('Роль')
                    ->options([
                        'admin' => 'Администратор',
                        'teacher' => 'Учитель',
                        'student' => 'Ученик',
                        'parent' => 'Родитель',
                    ])
                    ->required()
                    ->searchable(),

                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),

                TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->required(fn ($context) => $context === 'create')
                    ->minLength(8)
                    ->dehydrated(fn ($state) => filled($state))
                    ->dehydrateStateUsing(fn ($state) => bcrypt($state)),

                TextInput::make('passport_series')
                    ->label('Серия паспорта'),

                TextInput::make('passport_number')
                    ->label('Номер паспорта'),

                DatePicker::make('passport_issued_at')
                    ->label('Дата выдачи'),

                TextInput::make('passport_issued_by')
                    ->label('Кем выдан'),

                TextInput::make('passport_code')
                    ->label('Код подразделения'),

                Toggle::make('two_factor_enabled')
                    ->label('Двухфакторная аутентификация')
                    ->default(false),

                TextInput::make('two_factor_secret')
                    ->label('Секрет 2FA')
                    ->visible(fn ($get) => $get('two_factor_enabled')),
            ]);
    }
}
