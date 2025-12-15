<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Room extends Model
{
    protected $fillable = [
        'name',
        'capacity',
        'price_per_night',
        'description',
        'amenities',
        'photos',
        'room_number',
        'is_active',
    ];
    protected $casts = [
        'price_per_night' => 'integer',
        'photos' => 'array',
        'amenities' => 'array',
        'is_active' => 'boolean',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    // Связь с amenities для будущего использования
    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }
}
