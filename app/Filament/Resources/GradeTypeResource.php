<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GradeTypeResource\Pages\CreateGradeType;
use App\Filament\Resources\GradeTypeResource\Pages\EditGradeType;
use App\Filament\Resources\GradeTypeResource\Pages\ListGradeTypes;
use App\Filament\Resources\GradeTypeResource\Schemas\GradeTypeForm;
use App\Filament\Resources\GradeTypeResource\Tables\GradeTypesTable;
use App\Models\GradeType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GradeTypeResource extends Resource
{
    protected static ?string $model = GradeType::class;

    protected static ?string $resource = 'grade-type';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralModelLabel = 'Типы оценок';

    protected static ?string $modelLabel = 'тип оценки';

    public static function form(Schema $schema): Schema
    {
        return GradeTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GradeTypesTable::configure($table);
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
            'index' => ListGradeTypes::route('/'),
            'create' => CreateGradeType::route('/create'),
            'edit' => EditGradeType::route('/{record}/edit'),
        ];
    }
}
