<?php

namespace App\Filament\Resources\Users\Relations;

use App\Models\SchoolClass;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TeacherClassesRelationManager extends RelationManager
{
    protected static string $relationship = 'teacherClasses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('school_class_id')
                    ->label('Класс')
                    ->options(SchoolClass::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                \Filament\Forms\Components\TextInput::make('academic_year')
                    ->label('Учебный год')
                    ->default('2024-2025')
                    ->required(),

                \Filament\Forms\Components\Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Класс')
                    ->sortable(),

                Tables\Columns\TextColumn::make('academic_year')
                    ->label('Учебный год')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
