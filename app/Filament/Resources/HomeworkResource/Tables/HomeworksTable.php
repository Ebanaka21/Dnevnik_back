<?php

namespace App\Filament\Resources\HomeworkResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class HomeworksTable
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

                TextColumn::make('title')
                    ->label('Заголовок')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-document-text'),

                TextColumn::make('subject.name')
                    ->label('Предмет')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-book-open'),

                TextColumn::make('schoolClass.name')
                    ->label('Класс')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-building-office'),

                TextColumn::make('teacher.name')
                    ->label('Учитель')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-academic-cap'),

                TextColumn::make('assigned_date')
                    ->label('Дата выдачи')
                    ->date('d.m.Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),

                TextColumn::make('due_date')
                    ->label('Срок сдачи')
                    ->date('d.m.Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),

                TextColumn::make('max_points')
                    ->label('Макс. балл')
                    ->sortable()
                    ->icon('heroicon-o-star'),

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
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Обновлено')
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
            ->defaultSort('due_date', 'asc')
            ->paginated([10, 25, 50, 100]);
    }
}
