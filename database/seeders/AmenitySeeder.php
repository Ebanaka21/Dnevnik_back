<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            ['name' => 'Wi-Fi', 'icon_class' => 'bx bx-wifi', 'sort_order' => 1],
            ['name' => 'Кондиционер', 'icon_class' => 'bx bx-wind', 'sort_order' => 2],
            ['name' => 'Телевизор', 'icon_class' => 'bx bx-tv', 'sort_order' => 3],
            ['name' => 'Холодильник', 'icon_class' => 'bx bx-snowflake', 'sort_order' => 4],
            ['name' => 'Мини-бар', 'icon_class' => 'bx bx-drink', 'sort_order' => 5],
            ['name' => 'Сейф', 'icon_class' => 'bx bx-lock-alt', 'sort_order' => 6],
            ['name' => 'Фен', 'icon_class' => 'bx bx-hair-dryer', 'sort_order' => 7],
            ['name' => 'Душ', 'icon_class' => 'bx bx-shower', 'sort_order' => 8],
            ['name' => 'Ванна', 'icon_class' => 'bx bx-bath', 'sort_order' => 9],
            ['name' => 'Балкон', 'icon_class' => 'bx bx-expand', 'sort_order' => 10],
            ['name' => 'Вид на море', 'icon_class' => 'bx bx-water', 'sort_order' => 11],
            ['name' => 'Кухня', 'icon_class' => 'bx bx-restaurant', 'sort_order' => 12],
            ['name' => 'Чайник', 'icon_class' => 'bx bx-coffee', 'sort_order' => 13],
            ['name' => 'Шкафчики', 'icon_class' => 'bx bx-closet', 'sort_order' => 14],
            ['name' => 'Розетки у кровати', 'icon_class' => 'bx bx-plug', 'sort_order' => 15],
            ['name' => 'Джакузи', 'icon_class' => 'bx bx-bath', 'sort_order' => 16],
        ];

        foreach ($amenities as $amenity) {
            Amenity::create($amenity);
        }
    }
}
