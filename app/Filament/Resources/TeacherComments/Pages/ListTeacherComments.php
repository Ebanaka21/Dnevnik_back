<?php

namespace App\Filament\Resources\TeacherComments\Pages;

use App\Filament\Resources\TeacherComments\TeacherCommentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeacherComments extends ListRecords
{
    protected static string $resource = TeacherCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
