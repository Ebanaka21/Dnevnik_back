<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomeworkResource\Pages\CreateHomework;
use App\Filament\Resources\HomeworkResource\Pages\EditHomework;
use App\Filament\Resources\HomeworkResource\Pages\ListHomeworks;
use App\Filament\Resources\HomeworkResource\Schemas\HomeworkForm;
use App\Filament\Resources\HomeworkResource\Tables\HomeworksTable;
use App\Models\Homework;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HomeworkResource extends Resource
{
    protected static ?string $model = Homework::class;

    protected static ?string $resource = 'homework';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $pluralModelLabel = 'Домашние задания';

    protected static ?string $modelLabel = 'домашнее задание';

    public static function form(Schema $schema): Schema
    {
        return HomeworkForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeworksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeworks::route('/'),
            'create' => CreateHomework::route('/create'),
            'edit' => EditHomework::route('/{record}/edit'),
        ];
    }
}
