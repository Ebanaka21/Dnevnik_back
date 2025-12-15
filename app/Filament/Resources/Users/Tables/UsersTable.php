<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
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
                    ->label('Имя')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-user'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->icon('heroicon-o-envelope'),

                TextColumn::make('created_at')
                    ->label('Зарегистрирован')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),

                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-clock'),
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
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
