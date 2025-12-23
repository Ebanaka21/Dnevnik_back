<?php

namespace App\Filament\Resources\CurriculumPlanResource\Relations;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ThematicBlocksRelationManager extends RelationManager
{
    protected static string $relationship = 'thematicBlocks';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('title')
                    ->label('Название темы')
                    ->required()
                    ->maxLength(255),

                \Filament\Forms\Components\Textarea::make('description')
                    ->label('Описание темы')
                    ->rows(3)
                    ->columnSpanFull(),

                \Filament\Forms\Components\TextInput::make('weeks_count')
                    ->label('Количество недель')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20)
                    ->required(),

                \Filament\Forms\Components\TextInput::make('order')
                    ->label('Порядок')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                \Filament\Forms\Components\KeyValue::make('learning_objectives')
                    ->label('Учебные цели')
                    ->keyLabel('Цель')
                    ->valueLabel('Описание')
                    ->columnSpanFull(),

                \Filament\Forms\Components\KeyValue::make('required_materials')
                    ->label('Необходимые материалы')
                    ->keyLabel('Материал')
                    ->valueLabel('Описание')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('order')
                    ->label('№')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Тема')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('weeks_count')
                    ->label('Недель')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Описание')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Автоматически устанавливаем следующий порядок
                        $maxOrder = $this->getOwnerRecord()
                            ->thematicBlocks()
                            ->max('order') ?? 0;

                        $data['order'] = $maxOrder + 1;
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order');
    }
}
