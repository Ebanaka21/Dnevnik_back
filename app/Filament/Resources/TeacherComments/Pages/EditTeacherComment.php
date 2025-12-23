<?php

namespace App\Filament\Resources\TeacherComments\Pages;

use App\Filament\Resources\TeacherComments\TeacherCommentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTeacherComment extends EditRecord
{
    protected static string $resource = TeacherCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
