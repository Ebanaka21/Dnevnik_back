<?php

namespace App\Filament\Resources\AttendanceResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class AttendancesTable
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

                TextColumn::make('student.name')
                    ->label('Ученик')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-user'),

                TextColumn::make('subject.name')
                    ->label('Предмет')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-book-open'),

                TextColumn::make('teacher.name')
                    ->label('Учитель')
                    ->sortable()
                    ->searchable()
                    ->icon('heroicon-o-academic-cap'),

                TextColumn::make('date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'present' => 'Присутствует',
                            'absent' => 'Отсутствует',
                            'late' => 'Опоздал',
                            'sick' => 'Болеет',
                            'excused' => 'Уважительная причина',
                            default => $state,
                        };
                    })
                    ->color(function ($state) {
                        return match ($state) {
                            'present' => 'success',
                            'absent' => 'danger',
                            'late' => 'warning',
                            'sick' => 'info',
                            'excused' => 'primary',
                            default => 'gray',
                        };
                    })
                    ->icon(function ($state) {
                        return match ($state) {
                            'present' => 'heroicon-o-check-circle',
                            'absent' => 'heroicon-o-x-circle',
                            'late' => 'heroicon-o-clock',
                            'sick' => 'heroicon-o-heart',
                            'excused' => 'heroicon-o-shield-check',
                            default => 'heroicon-o-question-mark-circle',
                        };
                    }),

                TextColumn::make('arrival_time')
                    ->label('Время прихода')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reason')
                    ->label('Причина')
                    ->limit(50)
                    ->toggleable(),

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
            ->defaultSort('date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }
}
