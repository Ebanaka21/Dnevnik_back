<?php

namespace Database\Factories;

use App\Models\GradeType;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeTypeFactory extends Factory
{
    protected $model = GradeType::class;

    public function definition()
    {
        return [
            'name' => $this->faker->randomElement(['Экзамен', 'Контрольная работа', 'Домашнее задание', 'Устный ответ', 'Проект']),
            'description' => $this->faker->sentence,
            'weight' => $this->faker->randomFloat(2, 0.5, 2.0),
            'is_active' => true,
        ];
    }
}
