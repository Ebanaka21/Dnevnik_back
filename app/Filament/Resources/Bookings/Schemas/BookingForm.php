<?php

namespace App\Filament\Resources\Bookings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Гость')
                    ->columns(2)
                    ->schema([
                        TextInput::make('guest_name')->label('Имя')->required()->maxLength(255),
                        TextInput::make('guest_surname')->label('Фамилия')->required()->maxLength(255),
                        TextInput::make('guest_second_name')->label('Отчество')->maxLength(255),
                        TextInput::make('guest_phone')->label('Телефон')->tel()->required()->maxLength(20),
                        TextInput::make('guest_email')->label('Email')->email()->maxLength(255),
                        DatePicker::make('guest_birthday')->label('Дата рождения'),
                        TextInput::make('guest_passport_series')->label('Серия паспорта')->required()->maxLength(10),
                        TextInput::make('guest_passport_number')->label('Номер паспорта')->required()->maxLength(10),
                        DatePicker::make('guest_passport_issued_at')->label('Дата выдачи')->required(),
                        TextInput::make('guest_passport_issued_by')->label('Кем выдан')->required()->maxLength(255),
                    ]),

                Section::make('Даты и комната')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('check_in_date')   // ← ИСПРАВЛЕНО
                            ->label('Заезд')
                            ->required(),
                        DatePicker::make('check_out_date')  // ← ИСПРАВЛЕНО
                            ->label('Выезд')
                            ->required(),
                        Select::make('room_id')
                            ->label('Комната')
                            ->relationship('room', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Статус и оплата')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending_payment' => 'Ожидает оплаты',
                                'paid' => 'Оплачено',
                                'cancelled' => 'Отменено',
                            ])
                            ->default('pending_payment')
                            ->required(),

                        TextInput::make('total_price')
                            ->label('Сумма')
                            ->numeric()
                            ->prefix('₽'),
                    ]),
            ]);
    }
}
