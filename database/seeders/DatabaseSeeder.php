<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Преимущества
        $this->call(AmenitySeeder::class);

        // 2. Админ и тестовый гость
        User::create([
            'name' => 'Администратор',
            'email' => 'admin@mail.ru',
            'password' => Hash::make('1234567890'),
        ]);

        User::create([
            'name' => 'Гость',
            'email' => 'guest@example.com',
            'password' => Hash::make('password'),
        ]);

        // 3. Один хостел (только те поля, что реально есть!)
        Hostel::create([
            'name' => 'HostelStay Волгоград',
            'address' => 'ул. М. Балонина, 7, Волгоград',
            'phone' => '+7 (903) 338-41-41',
        ]);

        // 4. Несколько комнат
        Room::create([
            'name' => 'Стандартный двухместный',
            'room_number' => '101',
            'capacity' => 2,
            'price_per_night' => 2500,
            'description' => 'Уютный номер на двоих',
            'amenities' => json_encode(['Wi-Fi', 'Кондиционер', 'Телевизор']),
            'photos' => json_encode(['1.png']),
            'is_active' => true,
        ]);

        Room::create([
            'name' => 'Стандартный двухместный',
            'room_number' => '102',
            'capacity' => 2,
            'price_per_night' => 2600,
            'description' => 'Номер с видом во двор',
            'amenities' => json_encode(['Wi-Fi', 'Кондиционер', 'Чайник']),
            'photos' => json_encode(['women.png']),
            'is_active' => true,
        ]);

        Room::create([
            'name' => 'Люкс',
            'room_number' => '301',
            'capacity' => 2,
            'price_per_night' => 5500,
            'description' => 'Роскошный номер с большой кроватью',
            'amenities' => json_encode(['Wi-Fi', 'Кондиционер', 'Джакузи', 'Мини-бар']),
            'photos' => json_encode(['pivo.png']),
            'is_active' => true,
        ]);

        Room::create([
            'name' => 'Общий 8-местный',
            'room_number' => '001',
            'capacity' => 8,
            'price_per_night' => 800,
            'description' => 'Чистый общий номер',
            'amenities' => json_encode(['Wi-Fi', 'Шкафчики', 'Розетки у кровати']),
            'photos' => json_encode(['1.png']),
            'is_active' => true,
        ]);
    }
}
