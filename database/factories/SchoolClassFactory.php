<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    public function definition()
    {
        return [
            'name' => $this->faker->numberBetween(1, 11) . $this->faker->randomLetter,
            'academic_year' => $this->faker->year,
            'description' => $this->faker->sentence,
            'is_active' => true,
        ];
    }
}
