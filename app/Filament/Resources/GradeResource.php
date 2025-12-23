<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GradeResource\Pages\CreateGrade;
use App\Filament\Resources\GradeResource\Pages\EditGrade;
use App\Filament\Resources\GradeResource\Pages\ListGrades;
use App\Filament\Resources\GradeResource\Schemas\GradeForm;
use App\Filament\Resources\GradeResource\Tables\GradesTable;
use App\Models\Grade;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GradeResource extends Resource
{
    protected static ?string $model = Grade::class;

    protected static ?string $resource = 'grade';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $recordTitleAttribute = 'value';

    protected static ?string $pluralModelLabel = 'Оценки';

    protected static ?string $modelLabel = 'оценка';

    public static function form(Schema $schema): Schema
    {
        return GradeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GradesTable::configure($table);
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
            'index' => ListGrades::route('/'),
            'create' => CreateGrade::route('/create'),
            'edit' => EditGrade::route('/{record}/edit'),
        ];
    }
}
