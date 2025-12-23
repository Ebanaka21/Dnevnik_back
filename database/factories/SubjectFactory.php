<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word . ' ' . $this->faker->randomElement(['Математика', 'Русский', 'Физика', 'Химия', 'Биология']),
            'short_name' => $this->faker->unique()->randomElement(['МАТ', 'РУС', 'ФИЗ', 'ХИМ', 'БИО']),
            'subject_code' => $this->faker->unique()->randomElement(['MATH', 'RUSS', 'PHYS', 'CHEM', 'BIO']),
            'description' => $this->faker->sentence,
            'is_active' => true,
        ];
    }
}
