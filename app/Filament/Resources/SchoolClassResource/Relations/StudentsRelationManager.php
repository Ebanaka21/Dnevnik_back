<?php

namespace App\Filament\Resources\SchoolClassResource\Relations;

use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'schoolClasses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('academic_year')
                    ->label('Учебный год')
                    ->default('2024-2025')
                    ->required(),

                \Filament\Forms\Components\Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),

                \Filament\Forms\Components\DatePicker::make('enrolled_at')
                    ->label('Дата зачисления')
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('ФИО')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('pivot.academic_year')
                    ->label('Учебный год')
                    ->sortable(),

                Tables\Columns\IconColumn::make('pivot.is_active')
                    ->label('Активен')
                    ->boolean(),

                Tables\Columns\TextColumn::make('pivot.enrolled_at')
                    ->label('Дата зачисления')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Добавить учеников')
                    ->form([
                        \Filament\Forms\Components\Select::make('recordId')
                            ->label('Ученики')
                            ->multiple()
                            ->options(\App\Models\User::where('role', 'student')->get()->mapWithKeys(function ($user) {
                                $fullName = trim(($user->surname ?? '') . ' ' . ($user->name ?? '') . ' ' . ($user->second_name ?? ''));
                                return [$user->id => $fullName ?: 'Неизвестный ученик'];
                            }))
                            ->searchable()
                            ->preload()
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('academic_year')
                            ->label('Учебный год')
                            ->default('2024-2025')
                            ->required(),
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                        \Filament\Forms\Components\DatePicker::make('enrolled_at')
                            ->label('Дата зачисления')
                            ->default(now()),
                    ]),
            ])
            ->actions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
