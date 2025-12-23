<?php

namespace App\Filament\Resources\GradeTypeResource\Pages;

use App\Filament\Resources\GradeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateGradeType extends CreateRecord
{
    protected static string $resource = GradeTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Дополнительная логика перед созданием, если нужно
        return $data;
    }
}
