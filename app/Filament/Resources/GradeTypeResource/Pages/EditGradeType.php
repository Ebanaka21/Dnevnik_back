<?php

namespace App\Filament\Resources\GradeTypeResource\Pages;

use App\Filament\Resources\GradeTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGradeType extends EditRecord
{
    protected static string $resource = GradeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('Удалить'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Дополнительная логика перед сохранением, если нужно
        return $data;
    }
}
