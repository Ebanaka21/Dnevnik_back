<?php

namespace App\Filament\Resources\Rooms\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('room_number')
                    ->label('№')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-hashtag')
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon('heroicon-o-building-library'),

                TextColumn::make('capacity')
                    ->label('Вместимость')
                    ->numeric()
                    ->suffix(' чел.')
                    ->icon('heroicon-o-users')
                    ->sortable(),

                TextColumn::make('price_per_night')
                    ->label('Цена за ночь')
                    ->money('rub')
                    ->sortable()
                    ->icon('heroicon-o-credit-card'),

                TextColumn::make('photos')
                    ->label('Фото')
                    ->formatStateUsing(function ($record) {
                        $photos = is_array($record->photos) ? $record->photos : [];
                        $count = count($photos);
                        return $count > 0 ? $count . ' фото' : 'Нет фото';
                    })
                    ->icon('heroicon-o-photo'),

                IconColumn::make('is_active')
                    ->label('На сайте')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-eye-slash')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-calendar'),

                TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-clock'),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Статус')
                    ->options([
                        '1' => 'Активные',
                        '0' => 'Скрытые',
                    ])
                    ->placeholder('Все'),

                TernaryFilter::make('has_photos')
                    ->label('Есть фото')
                    ->placeholder('Все')
                    ->trueLabel('С фото')
                    ->falseLabel('Без фото')
                    ->query(function ($query) {
                        $query->whereJsonLength('photos', '>', 0);
                    }),

                SelectFilter::make('capacity')
                    ->label('Вместимость')
                    ->options([
                        '1' => '1 чел.',
                        '2' => '2 чел.',
                        '3' => '3 чел.',
                        '4' => '4 чел.',
                        '5' => '5+ чел.',
                    ])
                    ->placeholder('Все'),
            ])
            ->actions([
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
                    BulkAction::make('activate')
                        ->label('Активировать')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn($records) => $records->each->update(['is_active' => true])),
                    BulkAction::make('deactivate')
                        ->label('Скрыть')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->action(fn($records) => $records->each->update(['is_active' => false])),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Добавить комнату')
                    ->icon('heroicon-o-plus-circle'),
            ])
            ->defaultSort('room_number', 'asc')
            ->paginated([10, 25, 50, 100]);
    }
}
