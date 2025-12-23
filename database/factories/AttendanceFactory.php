<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition()
    {
        return [
            'student_id' => User::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
            'date' => $this->faker->date(),
            'status' => $this->faker->randomElement(['present', 'absent', 'late', 'excused']),
            'reason' => $this->faker->sentence,
            'lesson_number' => $this->faker->numberBetween(1, 6),
            'comment' => $this->faker->sentence,
        ];
    }
}
