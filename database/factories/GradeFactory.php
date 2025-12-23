<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\User;
use App\Models\Subject;
use App\Models\GradeType;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition()
    {
        return [
            'student_id' => User::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
            'grade_type_id' => GradeType::factory(),
            'grade_value' => $this->faker->numberBetween(2, 5),
            'date' => $this->faker->date(),
            'comment' => $this->faker->sentence,
            'max_points' => $this->faker->numberBetween(10, 100),
            'earned_points' => $this->faker->numberBetween(5, 95),
        ];
    }
}
