<?php

namespace App\Filament\Resources\CurriculumPlanResource\Pages;

use App\Filament\Resources\CurriculumPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCurriculumPlan extends CreateRecord
{
    protected static string $resource = CurriculumPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }
}
