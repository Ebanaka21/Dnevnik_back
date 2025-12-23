<?php

namespace Tests\Feature\Teacher;

use App\Models\User;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HomeworkControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $teacher;
    protected $student;
    protected $subject;
    protected $class;
    protected $homework;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Создаем роли
        $teacherRole = \App\Models\Role::create(['name' => 'teacher']);
        $studentRole = \App\Models\Role::create(['name' => 'student']);

        // Создаем учителя
        $this->teacher = User::factory()->create([
            'role_id' => $teacherRole->id,
            'name' => 'Тестовый Учитель',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password')
        ]);

        // Создаем ученика
        $this->student = User::factory()->create([
            'role_id' => $studentRole->id,
            'name' => 'Тестовый Ученик',
            'email' => 'student@test.com',
            'password' => bcrypt('password')
        ]);

        // Создаем предмет
        $this->subject = Subject::create([
            'name' => 'Математика',
            'description' => 'Тестовый предмет'
        ]);

        // Связываем учителя с предметом
        $this->teacher->subjects()->attach($this->subject->id);

        // Создаем класс
        $this->class = SchoolClass::create([
            'name' => '10А',
            'academic_year' => '2024-2025'
        ]);

        // Связываем класс с предметом
        $this->class->subjects()->attach($this->subject->id);

        // Добавляем учителя в класс
        $this->class->teachers()->attach($this->teacher->id);

        // Добавляем ученика в класс
        $this->student->studentClasses()->attach($this->class->id);

        // Создаем домашнее задание
        $this->homework = Homework::create([
            'title' => 'Решить уравнения',
            'description' => 'Решите уравнения из учебника',
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'school_class_id' => $this->class->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'max_points' => 100,
            'instructions' => 'Внимательно изучите материал'
        ]);

        // Авторизуемся как учитель
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'teacher@test.com',
            'password' => 'password'
        ]);

        $this->token = $loginResponse->json('token');
    }

    /** @test */
    public function teacher_can_get_all_homeworks()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'due_date',
                        'max_points',
                        'subject' => ['id', 'name'],
                        'teacher' => ['id', 'name', 'email'],
                        'schoolClass' => ['id', 'name']
                    ]
                ],
                'pagination'
            ]);
    }

    /** @test */
    public function teacher_can_create_homework()
    {
        $homeworkData = [
            'title' => 'Новое задание',
            'description' => 'Описание нового задания',
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id,
            'due_date' => now()->addDays(14)->toDateString(),
            'max_points' => 50,
            'instructions' => 'Следуйте инструкциям'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/homework', $homeworkData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'due_date',
                'max_points',
                'subject',
                'teacher',
                'schoolClass'
            ]);

        $this->assertDatabaseHas('homeworks', [
            'title' => 'Новое задание',
            'teacher_id' => $this->teacher->id
        ]);
    }

    /** @test */
    public function teacher_can_create_homework_with_attachments()
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $homeworkData = [
            'title' => 'Задание с файлом',
            'description' => 'Описание задания с файлом',
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'max_points' => 100,
            'attachments' => [$file]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/homework', $homeworkData);

        $response->assertStatus(201);

        // Проверяем, что файл был сохранен
        $homework = Homework::where('title', 'Задание с файлом')->first();
        $this->assertNotNull($homework->attachments);

        $attachments = json_decode($homework->attachments, true);
        $this->assertCount(1, $attachments);
        $this->assertEquals('document.pdf', $attachments[0]['name']);
    }

    /** @test */
    public function teacher_cannot_create_homework_for_subject_they_dont_teach()
    {
        $otherSubject = Subject::create([
            'name' => 'Физика',
            'description' => 'Другой предмет'
        ]);

        $homeworkData = [
            'title' => 'Задание по физике',
            'description' => 'Описание задания',
            'subject_id' => $otherSubject->id,
            'school_class_id' => $this->class->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'max_points' => 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/homework', $homeworkData);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Учитель не ведет этот предмет']);
    }

    /** @test */
    public function teacher_can_update_homework()
    {
        $updateData = [
            'title' => 'Обновленное задание',
            'description' => 'Обновленное описание',
            'due_date' => now()->addDays(10)->toDateString(),
            'max_points' => 75
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson('/api/homework/' . $this->homework->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->homework->id,
                'title' => 'Обновленное задание',
                'max_points' => 75
            ]);
    }

    /** @test */
    public function teacher_cannot_update_overdue_homework_due_date()
    {
        // Создаем просроченное задание
        $overdueHomework = Homework::create([
            'title' => 'Просроченное задание',
            'description' => 'Просроченное описание',
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'school_class_id' => $this->class->id,
            'due_date' => now()->subDays(1)->toDateString(),
            'max_points' => 100
        ]);

        $updateData = [
            'due_date' => now()->addDays(1)->toDateString()
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson('/api/homework/' . $overdueHomework->id, $updateData);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Нельзя изменить дату сдачи просроченного задания']);
    }

    /** @test */
    public function teacher_can_delete_homework()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson('/api/homework/' . $this->homework->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Домашнее задание успешно удалено']);

        $this->assertDatabaseMissing('homeworks', ['id' => $this->homework->id]);
    }

    /** @test */
    public function teacher_cannot_delete_homework_with_submissions()
    {
        // Создаем сдачу задания
        HomeworkSubmission::create([
            'homework_id' => $this->homework->id,
            'student_id' => $this->student->id,
            'content' => 'Мой ответ',
            'submitted_at' => now()
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson('/api/homework/' . $this->homework->id);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Нельзя удалить задание, у которого есть сдачи']);
    }

    /** @test */
    public function teacher_can_get_homework_details()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework/' . $this->homework->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'description',
                'due_date',
                'max_points',
                'subject',
                'teacher',
                'schoolClass',
                'submissions',
                'statistics'
            ]);
    }

    /** @test */
    public function teacher_can_get_homeworks_by_class()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework/class/' . $this->class->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'class' => ['id', 'name', 'academic_year'],
                'homeworks',
                'statistics' => [
                    'total_homeworks',
                    'active_homeworks',
                    'completed_homeworks',
                    'by_subject'
                ]
            ]);
    }

    /** @test */
    public function teacher_can_get_homeworks_by_subject()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework/subject/' . $this->subject->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subject' => ['id', 'name', 'description'],
                'homeworks',
                'statistics'
            ]);
    }

    /** @test */
    public function teacher_can_get_homeworks_by_teacher()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework/teacher/' . $this->teacher->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'teacher' => ['id', 'name', 'email'],
                'homeworks',
                'statistics'
            ]);
    }

    /** @test */
    public function teacher_can_get_submissions_for_homework()
    {
        // Создаем сдачу задания
        HomeworkSubmission::create([
            'homework_id' => $this->homework->id,
            'student_id' => $this->student->id,
            'content' => 'Мой ответ',
            'submitted_at' => now()
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework/' . $this->homework->id . '/submissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'homework' => ['id', 'title', 'max_points'],
                'submissions' => [
                    '*' => [
                        'id',
                        'content',
                        'submitted_at',
                        'student' => ['id', 'name', 'email']
                    ]
                ],
                'statistics' => [
                    'total',
                    'reviewed',
                    'pending_review',
                    'average_score'
                ]
            ]);
    }

    /** @test */
    public function teacher_can_review_submission()
    {
        // Создаем сдачу задания
        $submission = HomeworkSubmission::create([
            'homework_id' => $this->homework->id,
            'student_id' => $this->student->id,
            'content' => 'Мой ответ',
            'submitted_at' => now()
        ]);

        $reviewData = [
            'earned_points' => 85,
            'feedback' => 'Хорошая работа',
            'status' => 'reviewed'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/homework/' . $this->homework->id . '/submissions/' . $submission->id . '/review', $reviewData);

        $response->assertStatus(200)
            ->assertJson([
                'earned_points' => 85,
                'feedback' => 'Хорошая работа',
                'reviewed_by' => $this->teacher->id
            ]);

        $this->assertDatabaseHas('homework_submissions', [
            'id' => $submission->id,
            'earned_points' => 85,
            'reviewed_by' => $this->teacher->id
        ]);
    }

    /** @test */
    public function teacher_can_filter_homeworks_by_status()
    {
        // Создаем завершенное задание
        Homework::create([
            'title' => 'Завершенное задание',
            'description' => 'Описание',
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'school_class_id' => $this->class->id,
            'due_date' => now()->subDays(7)->toDateString(),
            'max_points' => 100
        ]);

        // Тестируем фильтрацию по статусу
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework?status=active');

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework?status=completed');

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_search_homeworks()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework?search=уравнения');

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_sort_homeworks()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework?sort_by=due_date&sort_order=asc');

        $response->assertStatus(200);
    }

    /** @test */
    public function pagination_works_for_homeworks()
    {
        // Создаем много заданий
        for ($i = 0; $i < 20; $i++) {
            Homework::create([
                'title' => 'Задание ' . $i,
                'description' => 'Описание ' . $i,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'school_class_id' => $this->class->id,
                'due_date' => now()->addDays($i)->toDateString(),
                'max_points' => 100
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework?per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function unauthorized_user_cannot_access_homeworks()
    {
        $response = $this->getJson('/api/homework');

        $response->assertStatus(401);
    }

    /** @test */
    public function student_cannot_create_homeworks()
    {
        // Авторизуемся как ученик
        $studentLoginResponse = $this->postJson('/api/login', [
            'email' => 'student@test.com',
            'password' => 'password'
        ]);

        $studentToken = $studentLoginResponse->json('token');

        $homeworkData = [
            'title' => 'Задание от ученика',
            'description' => 'Описание',
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'max_points' => 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $studentToken
        ])->postJson('/api/homework', $homeworkData);

        $response->assertStatus(403);
    }

    /** @test */
    public function validation_works_for_homework_creation()
    {
        // Неполные данные
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/homework', []);

        $response->assertStatus(422);

        // Неверная дата
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/homework', [
            'title' => 'Задание',
            'description' => 'Описание',
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id,
            'due_date' => now()->subDays(1)->toDateString(), // Прошедшая дата
            'max_points' => 100
        ]);

        $response->assertStatus(422);

        // Неверный файл
        $largeFile = UploadedFile::fake()->create('large.txt', 15000); // 15MB
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/homework', [
            'title' => 'Задание с большим файлом',
            'description' => 'Описание',
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id,
            'due_date' => now()->addDays(7)->toDateString(),
            'max_points' => 100,
            'attachments' => [$largeFile]
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function teacher_can_get_homework_statistics()
    {
        // Создаем несколько заданий и сдач
        for ($i = 0; $i < 5; $i++) {
            $homework = Homework::create([
                'title' => 'Задание ' . $i,
                'description' => 'Описание ' . $i,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'school_class_id' => $this->class->id,
                'due_date' => now()->addDays($i + 1)->toDateString(),
                'max_points' => 100
            ]);

            HomeworkSubmission::create([
                'homework_id' => $homework->id,
                'student_id' => $this->student->id,
                'content' => 'Ответ ' . $i,
                'earned_points' => rand(70, 100),
                'submitted_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by' => $this->teacher->id
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/homework/statistics/' . $this->class->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_homeworks',
                'active_homeworks',
                'completed_homeworks',
                'submission_rate',
                'average_score',
                'by_subject',
                'by_class'
            ]);
    }
}
