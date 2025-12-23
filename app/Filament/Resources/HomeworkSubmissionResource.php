<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomeworkSubmissionResource\Pages\CreateHomeworkSubmission;
use App\Filament\Resources\HomeworkSubmissionResource\Pages\EditHomeworkSubmission;
use App\Filament\Resources\HomeworkSubmissionResource\Pages\ListHomeworkSubmissions;
use App\Filament\Resources\HomeworkSubmissionResource\Schemas\HomeworkSubmissionForm;
use App\Filament\Resources\HomeworkSubmissionResource\Tables\HomeworkSubmissionsTable;
use App\Models\HomeworkSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HomeworkSubmissionResource extends Resource
{
    protected static ?string $model = HomeworkSubmission::class;

    protected static ?string $resource = 'homework-submission';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $recordTitleAttribute = 'content';

    protected static ?string $pluralModelLabel = 'Выполненные задания';

    protected static ?string $modelLabel = 'выполненное задание';

    public static function form(Schema $schema): Schema
    {
        return HomeworkSubmissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeworkSubmissionsTable::configure($table);
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
            'index' => ListHomeworkSubmissions::route('/'),
            'create' => CreateHomeworkSubmission::route('/create'),
            'edit' => EditHomeworkSubmission::route('/{record}/edit'),
        ];
    }
}
