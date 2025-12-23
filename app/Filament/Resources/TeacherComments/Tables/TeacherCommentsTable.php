<?php

namespace App\Filament\Resources\TeacherComments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Icons\Heroicon;

class TeacherCommentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('teacher.name')
                    ->label('Учитель')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('commentable_type')
                    ->label('Тип сущности')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'App\Models\Grade' => 'Оценка',
                        'App\Models\Homework' => 'Задание',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('comment_text')
                    ->label('Текст комментария')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    })
                    ->searchable(),

                CheckboxColumn::make('visible_to_student')
                    ->label('Для ученика'),

                CheckboxColumn::make('visible_to_parent')
                    ->label('Для родителя'),

                TextColumn::make('created_at')
                    ->label('Дата создания')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('teacher_id')
                    ->label('Учитель')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('commentable_type')
                    ->label('Тип сущности')
                    ->options([
                        'App\Models\Grade' => 'Оценка',
                        'App\Models\Homework' => 'Задание',
                    ]),

                SelectFilter::make('visible_to_student')
                    ->label('Видимость для ученика')
                    ->options([
                        1 => 'Видимо',
                        0 => 'Скрыто',
                    ]),

                SelectFilter::make('visible_to_parent')
                    ->label('Видимость для родителя')
                    ->options([
                        1 => 'Видимо',
                        0 => 'Скрыто',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultPaginationPageOption(20)
            ->searchable()
            ->paginated(true);
    }
}
