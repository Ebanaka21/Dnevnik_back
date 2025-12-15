<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class RoomController extends Controller
{
    // Функция для обработки URL фотографий
    private function formatPhotoUrl($photo)
    {
        // Если путь пустой или null, возвращаем placeholder
        if (empty($photo)) {
            return 'https://via.placeholder.com/1200x900/1f1f1f/ffffff?text=HostelStay';
        }

        // Если это уже полный URL, возвращаем как есть
        if (strpos($photo, 'http') === 0) {
            return $photo;
        }

        // Если путь начинается с /storage/, возвращаем полный URL
        if (strpos($photo, '/storage/') === 0) {
            return url($photo);
        }

        // Если путь начинается с storage/, добавляем слеш в начало и возвращаем полный URL
        if (strpos($photo, 'storage/') === 0) {
            return url('/' . $photo);
        }

        // Если путь уже содержит 'rooms/' (например, rooms/filename.jpg), просто добавляем /storage/ в начало
        if (strpos($photo, 'rooms/') === 0) {
            $fullPath = storage_path('app/public/' . $photo);
            if (!file_exists($fullPath)) {
                Log::warning("Photo file not found", ['path' => $fullPath, 'photo' => $photo]);

            }
            return url('/storage/' . $photo);
        }

        // Остальные файлы ищем в public/rooms/ папке
        $fullPath = public_path('storage/rooms/' . $photo);
        if (!file_exists($fullPath)) {
            Log::warning("Photo file not found", ['path' => $fullPath, 'photo' => $photo]);

        }
        return url('/storage/rooms/' . $photo);
    }

    // Главная страница — все типы номеров
    public function types()
    {
        try {
            $rooms = Room::where('is_active', true)
                ->get()
                ->groupBy('name');

            $result = $rooms->map(function ($group) {
                $cheapest = $group->sortBy('price_per_night')->first();

                // Форматируем amenities - сначала пробуем новый формат, потом fallback
                $amenities = [];
                if (is_string($cheapest->amenities)) {
                    // Старый JSON формат
                    $decoded = json_decode($cheapest->amenities, true);
                    if (is_array($decoded)) {
                        $amenities = $decoded;
                    }
                } elseif (is_array($cheapest->amenities)) {
                    // Уже массив - проверяем что это
                    $first = $cheapest->amenities[0] ?? null;

                    if (is_numeric($first)) {
                        // Это ID - конвертируем в названия
                        try {
                            $amenityNames = \App\Models\Amenity::whereIn('id', $cheapest->amenities)
                                ->pluck('name')
                                ->toArray();
                            $amenities = $amenityNames;
                        } catch (\Exception $e) {
                            Log::warning("Error loading amenity names", ['error' => $e->getMessage()]);
                            $amenities = [];
                        }
                    } else {
                        // Это уже названия
                        $amenities = $cheapest->amenities;
                    }
                }

                // Форматируем фотографии
                $photos = is_string($cheapest->photos)
                    ? json_decode($cheapest->photos, true) ?? []
                    : ($cheapest->photos ?? []);

                $formattedPhotos = array_map([$this, 'formatPhotoUrl'], $photos);

                return [
                    'id'              => $cheapest->id,
                    'type_name'       => $cheapest->name,
                    'name'            => $cheapest->name,
                    'slug'            => Str::slug($cheapest->name),
                    'cheapest_price'  => (int) $cheapest->price_per_night,
                    'price_per_night' => (int) $cheapest->price_per_night,
                    'capacity'        => $group->max('capacity'),
                    'available_count' => $group->count(),
                    'photos'          => $formattedPhotos,
                    'amenities'       => $amenities,
                    'description'     => $cheapest->description ?? 'Уютный номер',
                    'cheapest_room_id' => $cheapest->id,
                ];
            })->values();

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("Error in RoomController::types", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    // Поиск по датам
    public function available(Request $request)
    {
        try {
            $request->validate([
                'check_in'  => 'required|date',
                'check_out' => 'required|date|after:check_in',
                'guests'    => 'nullable|integer|min:1',
            ]);

            $checkIn  = $request->input('check_in');
            $checkOut = $request->input('check_out');
            $guests   = $request->input('guests', 1);

            Log::info("Searching available rooms", [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'guests' => $guests
            ]);

            $bookedRoomIds = Booking::where('status', '!=', 'cancelled')
                ->where(function ($q) use ($checkIn, $checkOut) {
                    $q->whereBetween('check_in_date', [$checkIn, $checkOut])
                        ->orWhereBetween('check_out_date', [$checkIn, $checkOut])
                        ->orWhereRaw('? BETWEEN check_in_date AND check_out_date', [$checkIn])
                        ->orWhereRaw('? BETWEEN check_in_date AND check_out_date', [$checkOut]);
                })
                ->pluck('room_id');

            Log::info("Booked room IDs found", ['count' => $bookedRoomIds->count()]);

            $availableRooms = Room::where('is_active', true)
                ->where('capacity', '>=', $guests)
                ->whereNotIn('id', $bookedRoomIds)
                ->get()
                ->groupBy('name');

            $result = $availableRooms->values()->map(function ($group) {
                $cheapest = $group->sortBy('price_per_night')->first();

                // Форматируем amenities - с конвертацией ID в названия
                $amenities = [];
                if (is_string($cheapest->amenities)) {
                    $decoded = json_decode($cheapest->amenities, true);
                    if (is_array($decoded)) {
                        $amenities = $decoded;
                    }
                } elseif (is_array($cheapest->amenities)) {
                    $first = $cheapest->amenities[0] ?? null;

                    if (is_numeric($first)) {
                        // Это ID - конвертируем в названия
                        try {
                            $amenityNames = \App\Models\Amenity::whereIn('id', $cheapest->amenities)
                                ->pluck('name')
                                ->toArray();
                            $amenities = $amenityNames;
                        } catch (\Exception $e) {
                            Log::warning("Error loading amenity names", ['error' => $e->getMessage()]);
                            $amenities = [];
                        }
                    } else {
                        // Это уже названия
                        $amenities = $cheapest->amenities;
                    }
                }

                // Форматируем фотографии
                $photos = is_string($cheapest->photos)
                    ? json_decode($cheapest->photos, true) ?? []
                    : ($cheapest->photos ?? []);

                if (empty($photos)) {
                    Log::warning("Room has no photos", [
                        'room_id' => $cheapest->id,
                        'room_name' => $cheapest->name,
                    ]);
                }

                $formattedPhotos = array_map([$this, 'formatPhotoUrl'], $photos);

                return [
                    'id'              => $cheapest->id,
                    'name'            => $cheapest->name,
                    'slug'            => Str::slug($cheapest->name),
                    'price_per_night' => $cheapest->price_per_night,
                    'capacity'        => $group->max('capacity'),
                    'available_count' => $group->count(),
                    'photos'          => $formattedPhotos,
                    'amenities'       => $amenities,
                    'description'     => $cheapest->description ?? 'Уютный номер',
                ];
            });

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("Error in available rooms search: " . $e->getMessage(), [
                'check_in' => $request->input('check_in'),
                'check_out' => $request->input('check_out'),
                'guests' => $request->input('guests'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    // Детали типа по slug
    public function show($slug)
    {
        try {
            $rooms = Room::where('is_active', true)
                ->whereRaw('LOWER(REPLACE(name, " ", "-")) = ?', [strtolower($slug)])
                ->get()
                ->groupBy('name');

            if ($rooms->isEmpty()) {
                return response()->json(['message' => 'Room type not found'], 404);
            }

            $group = $rooms->first();
            $cheapest = $group->sortBy('price_per_night')->first();

            // Форматируем amenities - с конвертацией ID в названия
            $amenities = [];
            if (is_string($cheapest->amenities)) {
                $decoded = json_decode($cheapest->amenities, true);
                if (is_array($decoded)) {
                    $amenities = $decoded;
                }
            } elseif (is_array($cheapest->amenities)) {
                $first = $cheapest->amenities[0] ?? null;

                if (is_numeric($first)) {
                    // Это ID - конвертируем в названия
                    try {
                        $amenityNames = \App\Models\Amenity::whereIn('id', $cheapest->amenities)
                            ->pluck('name')
                            ->toArray();
                        $amenities = $amenityNames;
                    } catch (\Exception $e) {
                        Log::warning("Error loading amenity names", ['error' => $e->getMessage()]);
                        $amenities = [];
                    }
                } else {
                    // Это уже названия
                    $amenities = $cheapest->amenities;
                }
            }

            // Форматируем фотографии
            $photos = is_string($cheapest->photos)
                ? json_decode($cheapest->photos, true) ?? []
                : ($cheapest->photos ?? []);

            $formattedPhotos = array_map([$this, 'formatPhotoUrl'], $photos);

            $result = [
                'id'              => $cheapest->id,
                'name'            => $cheapest->name,
                'slug'            => Str::slug($cheapest->name),
                'price_per_night' => $cheapest->price_per_night,
                'capacity'        => $group->max('capacity'),
                'available_count' => $group->count(),
                'photos'          => $formattedPhotos,
                'amenities'       => $amenities,
                'description'     => $cheapest->description ?? 'Уютный номер',
            ];

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("Error in RoomController::show", [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    // Детали конкретного номера по ID
    public function getById($id)
    {
        try {
            $room = Room::where('is_active', true)
                ->find($id);

            if (!$room) {
                return response()->json(['message' => 'Room not found'], 404);
            }

            // Форматируем amenities - с конвертацией ID в названия
            $amenities = [];
            if (is_string($room->amenities)) {
                $decoded = json_decode($room->amenities, true);
                if (is_array($decoded)) {
                    $amenities = $decoded;
                }
            } elseif (is_array($room->amenities)) {
                $first = $room->amenities[0] ?? null;

                if (is_numeric($first)) {
                    // Это ID - конвертируем в названия
                    try {
                        $amenityNames = \App\Models\Amenity::whereIn('id', $room->amenities)
                            ->pluck('name')
                            ->toArray();
                        $amenities = $amenityNames;
                    } catch (\Exception $e) {
                        Log::warning("Error loading amenity names", ['error' => $e->getMessage()]);
                        $amenities = [];
                    }
                } else {
                    // Это уже названия
                    $amenities = $room->amenities;
                }
            }

            // Форматируем фотографии
            $photos = is_string($room->photos)
                ? json_decode($room->photos, true) ?? []
                : ($room->photos ?? []);

            $formattedPhotos = array_map([$this, 'formatPhotoUrl'], $photos);

            $result = [
                'id'              => $room->id,
                'name'            => $room->name,
                'slug'            => Str::slug($room->name),
                'price_per_night' => $room->price_per_night,
                'capacity'        => $room->capacity,
                'available_count' => 1,
                'photos'          => $formattedPhotos,
                'amenities'       => $amenities,
                'description'     => $room->description ?? 'Уютный номер',
            ];

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error("Error in RoomController::getById", [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }
}
