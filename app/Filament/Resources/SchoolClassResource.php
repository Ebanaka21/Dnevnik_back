<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolClassResource\Pages\CreateSchoolClass;
use App\Filament\Resources\SchoolClassResource\Pages\EditSchoolClass;
use App\Filament\Resources\SchoolClassResource\Pages\ListSchoolClasses;
use App\Filament\Resources\SchoolClassResource\Schemas\SchoolClassForm;
use App\Filament\Resources\SchoolClassResource\Tables\SchoolClassesTable;
use App\Models\SchoolClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SchoolClassResource extends Resource
{
    protected static ?string $model = SchoolClass::class;

    protected static ?string $resource = 'school-class';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $pluralModelLabel = 'Классы';

    protected static ?string $modelLabel = 'класс';

    public static function form(Schema $schema): Schema
    {
        return SchoolClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolClassesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\SchoolClassResource\Relations\StudentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSchoolClasses::route('/'),
            'create' => CreateSchoolClass::route('/create'),
            'edit' => EditSchoolClass::route('/{record}/edit'),
        ];
    }
}
