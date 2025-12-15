<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Получаем первую комнату и первые основные amenities
        $room = DB::table('rooms')->first();

        if ($room) {
            // Получаем основные amenities
            $wifiAmenity = DB::table('amenities')->where('name', 'Wi-Fi')->first();
            $acAmenity = DB::table('amenities')->where('name', 'Кондиционер')->first();
            $tvAmenity = DB::table('amenities')->where('name', 'Телевизор')->first();
            $fridgeAmenity = DB::table('amenities')->where('name', 'Холодильник')->first();
            $safeAmenity = DB::table('amenities')->where('name', 'Сейф')->first();
            $showerAmenity = DB::table('amenities')->where('name', 'Душ')->first();

            $amenities = array_filter([$wifiAmenity, $acAmenity, $tvAmenity, $fridgeAmenity, $safeAmenity, $showerAmenity]);

            foreach ($amenities as $amenity) {
                // Проверяем, не привязана ли уже
                $exists = DB::table('amenity_room')
                    ->where('room_id', $room->id)
                    ->where('amenity_id', $amenity->id)
                    ->exists();

                if (!$exists) {
                    DB::table('amenity_room')->insert([
                        'room_id' => $room->id,
                        'amenity_id' => $amenity->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    echo "Привязан: " . $amenity->name . " к комнате " . $room->name . "\n";
                }
            }
        }
    }

    public function down(): void
    {
        // Удаляем все связи с amenities
        DB::table('amenity_room')->truncate();
    }
};
