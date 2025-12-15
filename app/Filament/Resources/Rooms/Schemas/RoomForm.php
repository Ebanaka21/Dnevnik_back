<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Models\Room;
use App\Models\Amenity;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Основные данные')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название / Класс комнаты')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn(string $state, Set $set) =>
                                $set('slug', Str::slug($state))
                            )
                            ->placeholder('Люкс с видом на море'),

                        TextInput::make('slug')
                            ->label('Slug (для URL)')
                            ->unique(Room::class, 'slug', ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('room_number')
                            ->label('Номер комнаты')
                            ->unique(Room::class, 'room_number', ignoreRecord: true)
                            ->maxLength(20),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('capacity')
                                    ->label('Вместимость')
                                    ->numeric()
                                    ->required()
                                    ->suffix(' чел.')
                                    ->minValue(1),

                                TextInput::make('price_per_night')
                                    ->label('Цена за ночь')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₽')
                                    ->step(100),
                            ]),
                    ]),

                Section::make('Статус')
                    ->columnSpan(1)
                    ->schema([
                        ToggleButtons::make('is_active')
                            ->label('Отображать на сайте')
                            ->boolean()
                            ->grouped()
                            ->default(true)
                            ->icons([
                                true => 'heroicon-o-check-circle',
                                false => 'heroicon-o-x-circle',
                            ]),
                    ]),

                // === Правая колонка (на всю ширину ниже) ===
                Section::make('Описание')
                    ->columnSpanFull()
                    ->schema([
                        RichEditor::make('description')
                            ->label('Полное описание')
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'link',
                                'redo',
                                'undo',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Удобства')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('amenities')
                            ->label('Выберите удобства')
                            ->bulkToggleable()
                            ->searchable()
                            ->columns(3)
                            ->options(function () {
                                return Amenity::active()
                                    ->ordered()
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->helperText('Управляйте списком удобств в разделе "Преимущества"'),
                    ]),

                Section::make('Фотографии комнаты')
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('photos')
                            ->label('Фото')
                            ->multiple()
                            ->disk('public')      // ← Использовать public disk
                            ->visibility('public') // ← Доступно публично
                            ->directory('rooms')
                            ->preserveFilenames()
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->maxFiles(12)
                            ->previewable()
                            ->downloadable()
                            ->helperText('Первое фото будет главным в карточке на сайте'),
                    ]),
            ]);
    }
}
