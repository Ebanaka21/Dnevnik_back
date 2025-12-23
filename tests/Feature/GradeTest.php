<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\GradeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function teacher_can_create_grade()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create();
        $gradeType = GradeType::factory()->create();

        $response = $this->actingAs($teacher, 'api')->postJson('/api/grades', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'grade_type_id' => $gradeType->id,
            'grade_value' => 5,
            'date' => '2025-12-18',
            'comment' => 'Отличная работа!'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'student_id',
                'subject_id',
                'teacher_id',
                'grade_type_id',
                'grade_value',
                'date',
                'comment'
            ]);

        $this->assertDatabaseHas('grades', [
            'grade_value' => 5,
            'comment' => 'Отличная работа!'
        ]);
    }

    /** @test */
    public function teacher_can_update_grade()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $grade = Grade::factory()->create(['teacher_id' => $teacher->id]);

        $response = $this->actingAs($teacher, 'api')->putJson("/api/grades/{$grade->id}", [
            'grade_value' => 4,
            'comment' => 'Хорошо, но можно лучше'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $grade->id,
                'grade_value' => 4,
                'comment' => 'Хорошо, но можно лучше'
            ]);
    }

    /** @test */
    public function teacher_can_delete_grade()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $grade = Grade::factory()->create(['teacher_id' => $teacher->id]);

        $response = $this->actingAs($teacher, 'api')->deleteJson("/api/grades/{$grade->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('grades', ['id' => $grade->id]);
    }

    /** @test */
    public function student_can_view_own_grades()
    {
        $student = User::factory()->create(['role' => 'student']);
        $grade = Grade::factory()->create(['student_id' => $student->id]);

        $response = $this->actingAs($student, 'api')->getJson("/api/grades/student/{$student->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $grade->id]);
    }

    /** @test */
    public function teacher_can_view_class_grades()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $grade = Grade::factory()->create(['teacher_id' => $teacher->id]);

        $response = $this->actingAs($teacher, 'api')->getJson('/api/grades');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $grade->id]);
    }
}
