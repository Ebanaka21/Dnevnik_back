<?php

namespace App\Filament\Resources\Amenities\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AmenitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('icon_class')
                    ->label('Иконка')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => $state ? "<i class='{$state}'></i>" : '-')
                    ->html(),

                IconColumn::make('is_active')
                    ->label('Активно')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),

                TextColumn::make('rooms_count')
                    ->label('Используется в комнатах')
                    ->counts('rooms'),

                TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Активность')
                    ->placeholder('Все')
                    ->trueLabel('Активные')
                    ->falseLabel('Неактивные'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('Удалить')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->action(fn($records) => $records->each->delete()),
                    BulkAction::make('activate')
                        ->label('Активировать')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn($records) => $records->each->update(['is_active' => true])),
                    BulkAction::make('deactivate')
                        ->label('Деактивировать')
                        ->icon('heroicon-o-x-circle')
                        ->action(fn($records) => $records->each->update(['is_active' => false])),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Добавить преимущество'),
            ]);
    }
}
