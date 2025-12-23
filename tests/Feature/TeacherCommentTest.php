<?php

namespace Tests\Feature;

use App\Models\TeacherComment;
use App\Models\Grade;
use App\Models\Homework;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherCommentTest extends TestCase
{
    use RefreshDatabase;

    protected $teacher;
    protected $student;
    protected $grade;
    protected $homework;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем учителя
        $this->teacher = User::factory()->create(['role_id' => 2]); // Предполагаем, что role_id 2 - учитель

        // Создаем ученика
        $this->student = User::factory()->create(['role_id' => 3]); // Предполагаем, что role_id 3 - ученик

        // Создаем оценку
        $this->grade = Grade::factory()->create([
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => 1,
            'grade_type_id' => 1,
            'value' => 5,
            'date' => now(),
        ]);

        // Создаем домашнее задание
        $this->homework = Homework::factory()->create([
            'teacher_id' => $this->teacher->id,
            'subject_id' => 1,
            'school_class_id' => 1,
            'title' => 'Test Homework',
            'description' => 'Test Description',
            'due_date' => now()->addDays(7),
        ]);
    }

    public function test_teacher_can_create_comment_on_grade()
    {
        $response = $this->actingAs($this->teacher)->post('/api/teacher-comments', [
            'commentable_type' => 'App\\Models\\Grade',
            'commentable_id' => $this->grade->id,
            'content' => 'Test comment on grade',
            'is_visible_to_student' => true,
            'is_visible_to_parent' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('teacher_comments', [
            'user_id' => $this->teacher->id,
            'commentable_type' => 'App\\Models\\Grade',
            'commentable_id' => $this->grade->id,
            'content' => 'Test comment on grade',
        ]);
    }

    public function test_teacher_can_create_comment_on_homework()
    {
        $response = $this->actingAs($this->teacher)->post('/api/teacher-comments', [
            'commentable_type' => 'App\\Models\\Homework',
            'commentable_id' => $this->homework->id,
            'content' => 'Test comment on homework',
            'is_visible_to_student' => true,
            'is_visible_to_parent' => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('teacher_comments', [
            'user_id' => $this->teacher->id,
            'commentable_type' => 'App\\Models\\Homework',
            'commentable_id' => $this->homework->id,
            'content' => 'Test comment on homework',
        ]);
    }

    public function test_teacher_can_get_all_comments()
    {
        // Создаем несколько комментариев
        TeacherComment::factory()->count(3)->create([
            'user_id' => $this->teacher->id,
            'commentable_type' => 'App\\Models\\Grade',
            'commentable_id' => $this->grade->id,
        ]);

        $response = $this->actingAs($this->teacher)->get('/api/teacher-comments');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_teacher_can_get_comments_by_grade()
    {
        // Создаем комментарии для разных оценок
        TeacherComment::factory()->create([
            'user_id' => $this->teacher->id,
            'commentable_type' => 'App\\Models\\Grade',
            'commentable_id' => $this->grade->id,
        ]);

        $response = $this->actingAs($this->teacher)->get("/api/teacher-comments/grade/{$this->grade->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['grade', 'comments']);
    }

    public function test_teacher_can_update_comment()
    {
        $comment = TeacherComment::factory()->create([
            'user_id' => $this->teacher->id,
            'commentable_type' => 'App\\Models\\Grade',
            'commentable_id' => $this->grade->id,
            'content' => 'Original content',
        ]);

        $response = $this->actingAs($this->teacher)->put("/api/teacher-comments/{$comment->id}", [
            'content' => 'Updated content',
            'is_visible_to_student' => false,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('teacher_comments', [
            'id' => $comment->id,
            'content' => 'Updated content',
            'is_visible_to_student' => false,
        ]);
    }

    public function test_teacher_can_delete_comment()
    {
        $comment = TeacherComment::factory()->create([
            'user_id' => $this->teacher->id,
            'commentable_type' => 'App\\Models\\Grade',
            'commentable_id' => $this->grade->id,
        ]);

        $response = $this->actingAs($this->teacher)->delete("/api/teacher-comments/{$comment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('teacher_comments', ['id' => $comment->id]);
    }

    public function test_teacher_can_get_comments_visible_to_student()
    {
        $student = User::factory()->create(['role_id' => 3]);

        // Создаем комментарии с разной видимостью
        TeacherComment::factory()->create([
            'user_id' => $this->teacher->id,
            'commentable_type' => 'App\\Models\\Grade',
            'commentable_id' => $this->grade->id,
            'is_visible_to_student' => true,
        ]);

        TeacherComment::factory()->create([
            'user_id' => $this->teacher->id,
            'commentable_type' => 'App\\Models\\Grade',
            'commentable_id' => $this->grade->id,
            'is_visible_to_student' => false,
        ]);

        $response = $this->actingAs($this->teacher)->get("/api/teacher-comments/student/{$student->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'comments'); // Должен вернуть только видимые комментарии
    }

    public function test_non_teacher_cannot_create_comment()
    {
        $student = User::factory()->create(['role_id' => 3]);

        $response = $this->actingAs($student)->post('/api/teacher-comments', [
            'commentable_type' => 'App\\Models\\Grade',
            'commentable_id' => $this->grade->id,
            'content' => 'Test comment',
        ]);

        $response->assertStatus(403);
    }
}
