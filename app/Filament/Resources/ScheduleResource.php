<?php

namespace App\Filament\Resources;

use App\Models\Schedule;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use BackedEnum;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static ?string $resource = 'schedule';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $pluralModelLabel = 'Расписание уроков';

    protected static ?string $modelLabel = 'расписание урока';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\ScheduleResource\Tables\SchedulesTable::configure($table);
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
            'index' => \App\Filament\Resources\ScheduleResource\Pages\ListSchedules::route('/'),
            'create' => \App\Filament\Resources\ScheduleResource\Pages\CreateSchedule::route('/create'),
        ];
    }
}


