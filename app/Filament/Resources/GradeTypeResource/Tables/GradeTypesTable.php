<?php

namespace App\Filament\Resources\GradeTypeResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class GradeTypesTable
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

                TextColumn::make('name')
                    ->label('Название типа')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-document-chart-bar'),

                TextColumn::make('short_name')
                    ->label('Краткое название')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-sparkles'),

                TextColumn::make('weight')
                    ->label('Вес (%)')
                    ->sortable()
                    ->icon('heroicon-o-scale'),

                IconColumn::make('is_active')
                    ->label('Статус')
                    ->boolean()
                    ->sortable()
                    ->icon(function ($state) {
                        return $state
                            ? 'heroicon-o-check-circle'
                            : 'heroicon-o-x-circle';
                    })
                    ->color(function ($state) {
                        return $state ? 'success' : 'danger';
                    }),

                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-clock')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
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
            ->defaultSort('name', 'asc')
            ->paginated([10, 25, 50, 100]);
    }
}
