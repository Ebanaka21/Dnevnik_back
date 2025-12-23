<?php

namespace App\Filament\Resources\HomeworkSubmissionResource\Pages;

use App\Filament\Resources\HomeworkSubmissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHomeworkSubmission extends CreateRecord
{
    protected static string $resource = HomeworkSubmissionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
