<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Homework;
use App\Models\Subject;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeworkTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function teacher_can_create_homework()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $subject = Subject::factory()->create();
        $class = SchoolClass::factory()->create();

        $response = $this->actingAs($teacher, 'api')->postJson('/api/homework', [
            'title' => 'Математическое задание',
            'description' => 'Решить задачи 1-10',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'due_date' => '2025-12-25',
            'max_points' => 10
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'subject_id',
                'teacher_id',
                'school_class_id',
                'due_date',
                'max_points'
            ]);

        $this->assertDatabaseHas('homeworks', [
            'title' => 'Математическое задание',
            'description' => 'Решить задачи 1-10'
        ]);
    }

    /** @test */
    public function teacher_can_update_homework()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $homework = Homework::factory()->create(['teacher_id' => $teacher->id]);

        $response = $this->actingAs($teacher, 'api')->putJson("/api/homework/{$homework->id}", [
            'title' => 'Обновленное задание',
            'description' => 'Решить задачи 1-15',
            'due_date' => '2025-12-26'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $homework->id,
                'title' => 'Обновленное задание',
                'description' => 'Решить задачи 1-15'
            ]);
    }

    /** @test */
    public function teacher_can_delete_homework()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $homework = Homework::factory()->create(['teacher_id' => $teacher->id]);

        $response = $this->actingAs($teacher, 'api')->deleteJson("/api/homework/{$homework->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('homeworks', ['id' => $homework->id]);
    }

    /** @test */
    public function student_can_view_class_homework()
    {
        $student = User::factory()->create(['role' => 'student']);
        $class = SchoolClass::factory()->create();
        $homework = Homework::factory()->create(['school_class_id' => $class->id]);

        $response = $this->actingAs($student, 'api')->getJson("/api/homework/class/{$class->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $homework->id]);
    }

    /** @test */
    public function student_can_submit_homework()
    {
        $student = User::factory()->create(['role' => 'student']);
        $homework = Homework::factory()->create();

        $response = $this->actingAs($student, 'api')->postJson("/api/homework/{$homework->id}/submit", [
            'content' => 'Решенные задачи',
            'submission_text' => 'Я выполнил все задания'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'homework_id',
                'student_id',
                'content',
                'submission_text'
            ]);

        $this->assertDatabaseHas('homework_submissions', [
            'homework_id' => $homework->id,
            'student_id' => $student->id
        ]);
    }

    /** @test */
    public function teacher_can_review_homework_submission()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $homework = Homework::factory()->create(['teacher_id' => $teacher->id]);
        $submission = $homework->submissions()->create([
            'student_id' => User::factory()->create(['role' => 'student'])->id,
            'content' => 'Решенные задачи',
            'status' => 'submitted'
        ]);

        $response = $this->actingAs($teacher, 'api')->postJson(
            "/api/homework/{$homework->id}/submissions/{$submission->id}/review",
            [
                'earned_points' => 8,
                'feedback' => 'Хорошая работа!',
                'status' => 'reviewed'
            ]
        );

        $response->assertStatus(200)
            ->assertJson([
                'earned_points' => 8,
                'feedback' => 'Хорошая работа!',
                'status' => 'reviewed'
            ]);
    }
}
