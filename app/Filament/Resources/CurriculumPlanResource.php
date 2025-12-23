<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurriculumPlanResource\Pages\CreateCurriculumPlan;
use App\Filament\Resources\CurriculumPlanResource\Pages\EditCurriculumPlan;
use App\Filament\Resources\CurriculumPlanResource\Pages\ListCurriculumPlans;
use App\Filament\Resources\CurriculumPlanResource\Relations\ThematicBlocksRelationManager;
use App\Filament\Resources\CurriculumPlanResource\Schemas\CurriculumPlanForm;
use App\Filament\Resources\CurriculumPlanResource\Tables\CurriculumPlansTable;
use App\Models\CurriculumPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CurriculumPlanResource extends Resource
{
    protected static ?string $model = CurriculumPlan::class;

    protected static ?string $resource = 'curriculum-plan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $recordTitleAttribute = 'subject.name';

    protected static ?string $pluralModelLabel = 'Учебные планы';

    protected static ?string $modelLabel = 'учебный план';

    public static function form(Schema $schema): Schema
    {
        return CurriculumPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CurriculumPlansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ThematicBlocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCurriculumPlans::route('/'),
            'create' => CreateCurriculumPlan::route('/create'),
            'edit' => EditCurriculumPlan::route('/{record}/edit'),
        ];
    }
}
