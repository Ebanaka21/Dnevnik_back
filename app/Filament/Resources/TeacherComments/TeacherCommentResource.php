<?php

namespace App\Filament\Resources\TeacherComments;

use App\Filament\Resources\TeacherComments\Pages\CreateTeacherComment;
use App\Filament\Resources\TeacherComments\Pages\EditTeacherComment;
use App\Filament\Resources\TeacherComments\Pages\ListTeacherComments;
use App\Filament\Resources\TeacherComments\Schemas\TeacherCommentForm;
use App\Filament\Resources\TeacherComments\Tables\TeacherCommentsTable;
use App\Models\TeacherComment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TeacherCommentResource extends Resource
{
    protected static ?string $model = TeacherComment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Комментарии учителей';
    protected static ?string $modelLabel = 'комментарий';
    protected static ?string $pluralModelLabel = 'комментарии';

    public static function form(Schema $schema): Schema
    {
        return TeacherCommentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeacherCommentsTable::configure($table);
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
            'index' => ListTeacherComments::route('/'),
            'create' => CreateTeacherComment::route('/create'),
            'edit' => EditTeacherComment::route('/{record}/edit'),
        ];
    }
}
