<?php

namespace App\Filament\Resources\SchoolClassResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolClassesTable
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
                    ->label('Название класса')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-building-office'),

                TextColumn::make('year')
                    ->label('Год обучения')
                    ->sortable()
                    ->icon('heroicon-o-academic-cap'),

                TextColumn::make('letter')
                    ->label('Буква')
                    ->sortable()
                    ->icon('heroicon-o-sparkles'),

                TextColumn::make('max_students')
                    ->label('Макс. учеников')
                    ->sortable()
                    ->icon('heroicon-o-users'),

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
            ->defaultSort('year', 'asc')
            ->paginated([10, 25, 50, 100]);
    }
}
