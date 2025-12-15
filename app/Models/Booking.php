<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
        'check_in_date',
        'check_out_date',
        'status',
        'total_price',
        'guest_name',
        'guest_surname',
        'guest_second_name',
        'guest_birthday',
        'guest_phone',
        'guest_email',
        'guest_passport_series',
        'guest_passport_number',
        'guest_passport_issued_at',
        'guest_passport_issued_by',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'guest_birthday' => 'date',
        'guest_passport_issued_at' => 'date',
        'total_price' => 'decimal:2',
    ];

    // Отношения
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    // Метод для расчёта цены (при создании)
    public function calculatePrice(): self
    {
        if ($this->check_in_date && $this->check_out_date && $this->room) {
            $days = $this->check_in_date->diffInDays($this->check_out_date);
            $this->total_price = $days * $this->room->price_per_night;
        }
        return $this;
    }
}
