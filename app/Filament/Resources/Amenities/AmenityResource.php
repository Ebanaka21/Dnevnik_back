<?php

namespace App\Filament\Resources\Amenities;

use App\Filament\Resources\Amenities\Pages\CreateAmenity;
use App\Filament\Resources\Amenities\Pages\EditAmenity;
use App\Filament\Resources\Amenities\Pages\ListAmenities;
use App\Filament\Resources\Amenities\Schemas\AmenityForm;
use App\Filament\Resources\Amenities\Tables\AmenitiesTable;
use App\Models\Amenity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AmenityResource extends Resource
{
    protected static ?string $model = Amenity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AmenityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AmenitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAmenities::route('/'),
            'create' => CreateAmenity::route('/create'),
            'edit' => EditAmenity::route('/{record}/edit'),
        ];
    }
}
