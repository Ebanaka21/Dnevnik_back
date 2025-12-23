<?php

namespace App\Filament\Resources\LessonResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LessonsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('№')->sortable()->icon('heroicon-o-hashtag'),
            TextColumn::make('title')->label('Название')->searchable()->icon('heroicon-o-academic-cap'),
            TextColumn::make('subject.name')->label('Предмет')->searchable()->icon('heroicon-o-book-open'),
            TextColumn::make('schoolClass.name')->label('Класс')->searchable()->icon('heroicon-o-building-office'),
            TextColumn::make('date')->label('Дата')->date('d.m.Y')->icon('heroicon-o-calendar'),
        ])->actions([
            EditAction::make()->icon('heroicon-o-pencil'),
            DeleteAction::make()->icon('heroicon-o-trash'),
        ])->bulkActions([
            BulkActionGroup::make([DeleteBulkAction::make()->icon('heroicon-o-trash')]),
        ])->paginated([10, 25, 50, 100]);
    }
}
