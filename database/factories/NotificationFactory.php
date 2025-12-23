<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'message' => $this->faker->paragraph,
            'type' => $this->faker->randomElement(['grade', 'homework', 'attendance', 'schedule', 'system']),
            'related_id' => $this->faker->numberBetween(1, 100),
            'related_type' => $this->faker->randomElement(['grade', 'homework', 'attendance', 'schedule']),
            'is_read' => false,
            'created_at' => $this->faker->dateTime,
        ];
    }
}
