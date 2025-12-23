<?php

namespace App\Filament\Resources\HomeworkSubmissionResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomeworkSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('№')->sortable()->icon('heroicon-o-hashtag'),
            TextColumn::make('homework.title')->label('Задание')->searchable()->icon('heroicon-o-document-text'),
            TextColumn::make('student.name')->label('Ученик')->searchable()->icon('heroicon-o-user'),
            TextColumn::make('points_earned')->label('Баллы')->sortable()->icon('heroicon-o-star'),
            TextColumn::make('submitted_at')->label('Сдано')->dateTime('d.m.Y H:i')->icon('heroicon-o-calendar'),
        ])->actions([
            EditAction::make()->icon('heroicon-o-pencil'),
            DeleteAction::make()->icon('heroicon-o-trash'),
        ])->bulkActions([
            BulkActionGroup::make([DeleteBulkAction::make()->icon('heroicon-o-trash')]),
        ])->paginated([10, 25, 50, 100]);
    }
}
