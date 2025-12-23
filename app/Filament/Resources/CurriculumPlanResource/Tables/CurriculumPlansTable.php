<?php

namespace App\Filament\Resources\CurriculumPlanResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CurriculumPlansTable
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

                TextColumn::make('schoolClass.name')
                    ->label('Класс')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-user-group'),

                TextColumn::make('subject.name')
                    ->label('Предмет')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-book-open'),

                TextColumn::make('teacher_id')
                    ->label('Учитель')
                    ->formatStateUsing(fn ($state, $record) => $record->teacher?->full_name ?? 'Не назначен')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-user'),

                TextColumn::make('academic_year')
                    ->label('Академический год')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-calendar'),

                TextColumn::make('hours_per_week')
                    ->label('Часов в неделю')
                    ->sortable()
                    ->icon('heroicon-o-clock'),

                TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Обновлена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->icon('heroicon-o-clock')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Класс')
                    ->relationship('schoolClass', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('subject_id')
                    ->label('Предмет')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('teacher_id')
                    ->label('Учитель')
                    ->relationship('teacher', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable()
                    ->preload(),
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
            ->defaultSort('academic_year', 'desc')
            ->paginated([10, 20, 25, 50, 100]);
    }
}
