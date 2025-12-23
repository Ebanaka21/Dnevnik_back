<?php

namespace App\Filament\Resources\TeacherComments\Schemas;

use App\Models\User;
use App\Models\Grade;
use App\Models\Homework;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Checkbox;

class TeacherCommentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('teacher_id')
                    ->label('Учитель')
                    ->options(User::where('role_id', 2)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Select::make('commentable_type')
                    ->label('Связанная сущность')
                    ->options([
                        Grade::class => 'Оценка',
                        Homework::class => 'Задание',
                    ])
                    ->required()
                    ->reactive(),

                Select::make('commentable_id')
                    ->label('Связанная запись')
                    ->options(function (callable $get) {
                        $type = $get('commentable_type');
                        if ($type === Grade::class) {
                            return Grade::with(['student', 'subject'])->get()->pluck('display_name', 'id');
                        } elseif ($type === Homework::class) {
                            return Homework::with(['subject', 'schoolClass'])->get()->pluck('display_name', 'id');
                        }
                        return [];
                    })
                    ->required()
                    ->searchable(),

                Textarea::make('comment_text')
                    ->label('Текст комментария')
                    ->required()
                    ->maxLength(150)
                    ->helperText('Максимум 150 символов'),

                Checkbox::make('visible_to_student')
                    ->label('Видимость для ученика')
                    ->default(true),

                Checkbox::make('visible_to_parent')
                    ->label('Видимость для родителя')
                    ->default(false),
            ]);
    }
}
