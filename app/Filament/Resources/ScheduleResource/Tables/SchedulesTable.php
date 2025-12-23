<?php

namespace App\Filament\Resources\ScheduleResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SchedulesTable
{
    public static function configure(Table $table): Table
    {
        $daysOfWeek = [
            1 => 'Пн',
            2 => 'Вт',
            3 => 'Ср',
            4 => 'Чт',
            5 => 'Пт',
            6 => 'Сб',
            7 => 'Вс',
        ];

        return $table
            ->columns([
                TextColumn::make('schoolClass.name')
                    ->label('Класс')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-library')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->schoolClass->full_name;
                    }),

                TextColumn::make('subject.name')
                    ->label('Предмет')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-book-open'),

                TextColumn::make('teacher.name')
                    ->label('Преподаватель')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-user')
                    ->formatStateUsing(function ($state, $record) {
                        return $record->teacher->full_name;
                    }),

                TextColumn::make('day_of_week')
                    ->label('День')
                    ->formatStateUsing(function ($state) use ($daysOfWeek) {
                        return $daysOfWeek[$state] ?? $state;
                    })
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-calendar'),

                TextColumn::make('lesson_number')
                    ->label('Урок')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-o-list-bullet'),

                TextColumn::make('start_time')
                    ->label('Начало')
                    ->time('H:i')
                    ->sortable()
                    ->icon('heroicon-o-clock'),

                TextColumn::make('end_time')
                    ->label('Конец')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('classroom')
                    ->label('Кабинет')
                    ->placeholder('-')
                    ->icon('heroicon-o-home'),

                TextColumn::make('academic_year')
                    ->label('Уч. год')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-calendar-days'),

                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->sortable()
                    ->icon('heroicon-o-check-circle'),
            ])
            ->filters([
                SelectFilter::make('academic_year')
                    ->label('Учебный год')
                    ->options([
                        '2023-2024' => '2023-2024',
                        '2024-2025' => '2024-2025',
                        '2025-2026' => '2025-2026',
                    ]),

                SelectFilter::make('day_of_week')
                    ->label('День недели')
                    ->options([
                        1 => 'Понедельник',
                        2 => 'Вторник',
                        3 => 'Среда',
                        4 => 'Четверг',
                        5 => 'Пятница',
                        6 => 'Суббота',
                        7 => 'Воскресенье',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Активен'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('day_of_week', 'asc')
            ->defaultSort('lesson_number', 'asc');
    }
}




