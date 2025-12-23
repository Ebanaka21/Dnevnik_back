<?php

namespace App\Filament\Resources\GradeTypeResource\Pages;

use App\Filament\Resources\GradeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGradeTypes extends ListRecords
{
    protected static string $resource = GradeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Создать тип оценки')
                ->icon('heroicon-o-plus'),
        ];
    }
}
