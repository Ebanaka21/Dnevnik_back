<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('№')
                    ->sortable()
                    ->icon('heroicon-o-hashtag')
                    ->toggleable(),

                TextColumn::make('guest_surname')->label('Фамилия')->searchable()->icon('heroicon-o-user-circle'),
                TextColumn::make('guest_name')->label('Имя')->searchable()->icon('heroicon-o-user'),
                TextColumn::make('guest_phone')->label('Телефон')->icon('heroicon-o-phone'),
                TextColumn::make('room.name')->label('Комната')->icon('heroicon-o-building-library'),
                TextColumn::make('check_in_date')->label('Заезд')->date('d.m.Y')->sortable()->icon('heroicon-o-calendar'),
                TextColumn::make('check_out_date')->label('Выезд')->date('d.m.Y')->sortable()->icon('heroicon-o-calendar'),
                TextColumn::make('total_price')->label('Сумма')->money('rub')->sortable()->icon('heroicon-o-credit-card'),
                TextColumn::make('guest_passport_series')->label('Паспорт')->formatStateUsing(fn($state, $record) => $record->guest_passport_series . ' ' . $record->guest_passport_number)->searchable()->icon('heroicon-o-identification'),
                BadgeColumn::make('status')
                    ->label('Статус')
                    ->colors([
                        'danger' => 'cancelled',
                        'warning' => 'pending_payment',
                        'success' => 'paid',
                    ])
                    ->icons([
                        'cancelled' => 'heroicon-o-x-circle',
                        'pending_payment' => 'heroicon-o-clock',
                        'paid' => 'heroicon-o-check-circle',
                    ]),

                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-calendar'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending_payment' => 'Ожидает оплаты',
                        'paid' => 'Оплачено',
                        'cancelled' => 'Отменено',
                    ])
                    ->placeholder('Все'),

                SelectFilter::make('room_id')
                    ->label('Комната')
                    ->relationship('room', 'name')
                    ->placeholder('Все комнаты'),
            ])
            ->actions([
                Action::make('pay')
                    ->label('Оплатить')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->button()
                    ->visible(fn(Booking $record) => $record->status === 'pending_payment')
                    ->requiresConfirmation()
                    ->modalHeading('Подтвердить оплату')
                    ->modalDescription('Гость оплатил бронирование?')
                    ->modalSubmitActionLabel('Да, оплатить')
                    ->action(function (Booking $record) {
                        $record->update(['status' => 'paid']);

                        Notification::make()
                            ->title('Бронь №' . $record->id . ' оплачена')
                            ->success()
                            ->send();
                    })
                    ->icon('heroicon-o-banknotes'),

                ViewAction::make()
                    ->icon('heroicon-o-eye'),
                EditAction::make()
                    ->icon('heroicon-o-pencil'),
                DeleteAction::make()
                    ->icon('heroicon-o-trash'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->icon('heroicon-o-trash'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
