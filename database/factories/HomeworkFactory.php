<?php

namespace Database\Factories;

use App\Models\Homework;
use App\Models\User;
use App\Models\Subject;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

class HomeworkFactory extends Factory
{
    protected $model = Homework::class;

    public function definition()
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
            'school_class_id' => SchoolClass::factory(),
            'due_date' => $this->faker->date(),
            'max_points' => $this->faker->numberBetween(10, 100),
            'instructions' => $this->faker->paragraph,
        ];
    }
}
