<?php

namespace Tests\Feature\Teacher;

use App\Models\User;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\Grade;
use App\Models\GradeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $teacher;
    protected $student;
    protected $subject;
    protected $class;
    protected $gradeType;
    protected $grade;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем роль учителя
        $teacherRole = \App\Models\Role::create(['name' => 'teacher']);

        // Создаем роль ученика
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

        // Добавляем ученика в класс
        $this->student->studentClasses()->attach($this->class->id);

        // Создаем тип оценки
        $this->gradeType = GradeType::create([
            'name' => 'Контрольная работа',
            'description' => 'Контрольная работа'
        ]);

        // Создаем оценку
        $this->grade = Grade::create([
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'grade_type_id' => $this->gradeType->id,
            'grade_value' => 5,
            'date' => now()->toDateString(),
            'comment' => 'Отлично'
        ]);

        // Авторизуемся как учитель
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'teacher@test.com',
            'password' => 'password'
        ]);

        $this->token = $loginResponse->json('token');
    }

    /** @test */
    public function teacher_can_get_all_grades()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/grades');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'grade_value',
                        'date',
                        'comment',
                        'student' => ['id', 'name', 'email'],
                        'teacher' => ['id', 'name', 'email'],
                        'subject' => ['id', 'name'],
                        'gradeType' => ['id', 'name']
                    ]
                ],
                'pagination'
            ]);
    }

    /** @test */
    public function teacher_can_create_grade()
    {
        $gradeData = [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'grade_type_id' => $this->gradeType->id,
            'grade_value' => 4,
            'date' => now()->toDateString(),
            'comment' => 'Хорошо'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/grades', $gradeData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'grade_value',
                'date',
                'comment',
                'student',
                'teacher',
                'subject',
                'gradeType'
            ]);

        $this->assertDatabaseHas('grades', [
            'student_id' => $this->student->id,
            'grade_value' => 4
        ]);
    }

    /** @test */
    public function teacher_cannot_create_grade_for_invalid_student()
    {
        $gradeData = [
            'student_id' => 999,
            'subject_id' => $this->subject->id,
            'grade_type_id' => $this->gradeType->id,
            'grade_value' => 4,
            'date' => now()->toDateString()
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/grades', $gradeData);

        $response->assertStatus(422);
    }

    /** @test */
    public function teacher_cannot_create_grade_for_subject_they_dont_teach()
    {
        // Создаем другой предмет
        $otherSubject = Subject::create([
            'name' => 'Физика',
            'description' => 'Другой предмет'
        ]);

        $gradeData = [
            'student_id' => $this->student->id,
            'subject_id' => $otherSubject->id,
            'grade_type_id' => $this->gradeType->id,
            'grade_value' => 4,
            'date' => now()->toDateString()
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/grades', $gradeData);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Учитель не ведет этот предмет']);
    }

    /** @test */
    public function teacher_can_update_grade()
    {
        $updateData = [
            'grade_value' => 3,
            'comment' => 'Удовлетворительно'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson('/api/grades/' . $this->grade->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->grade->id,
                'grade_value' => 3,
                'comment' => 'Удовлетворительно'
            ]);

        $this->assertDatabaseHas('grades', [
            'id' => $this->grade->id,
            'grade_value' => 3
        ]);
    }

    /** @test */
    public function teacher_can_delete_grade()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson('/api/grades/' . $this->grade->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Оценка успешно удалена']);

        $this->assertDatabaseMissing('grades', ['id' => $this->grade->id]);
    }

    /** @test */
    public function teacher_can_get_grades_by_student()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/grades/student/' . $this->student->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'student' => ['id', 'name', 'email'],
                'grades' => [
                    '*' => [
                        'id',
                        'grade_value',
                        'date',
                        'subject' => ['id', 'name'],
                        'teacher' => ['id', 'name', 'email'],
                        'gradeType' => ['id', 'name']
                    ]
                ],
                'statistics'
            ]);
    }

    /** @test */
    public function teacher_can_get_grades_by_subject()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/grades/subject/' . $this->subject->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'subject' => ['id', 'name', 'description'],
                'grades' => [
                    '*' => [
                        'id',
                        'grade_value',
                        'date',
                        'student' => ['id', 'name', 'email'],
                        'teacher' => ['id', 'name', 'email'],
                        'gradeType' => ['id', 'name']
                    ]
                ],
                'statistics'
            ]);
    }

    /** @test */
    public function teacher_can_get_grades_by_class()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/grades?school_class_id=' . $this->class->id);

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_filter_grades_by_date_range()
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/grades?date_from=' . $startDate . '&date_to=' . $endDate);

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_filter_grades_by_grade_type()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/grades?grade_type_id=' . $this->gradeType->id);

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_sort_grades()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/grades?sort_by=grade_value&sort_order=desc');

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_get_class_statistics()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/grades/statistics/' . $this->class->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'class' => ['id', 'name', 'academic_year'],
                'statistics' => [
                    'total_grades',
                    'average_grade',
                    'grade_distribution',
                    'by_students',
                    'by_subjects',
                    'by_months',
                    'by_grade_types'
                ]
            ]);
    }

    /** @test */
    public function unauthorized_user_cannot_access_grades()
    {
        $response = $this->getJson('/api/grades');

        $response->assertStatus(401);
    }

    /** @test */
    public function student_cannot_create_grades()
    {
        // Авторизуемся как ученик
        $studentLoginResponse = $this->postJson('/api/login', [
            'email' => 'student@test.com',
            'password' => 'password'
        ]);

        $studentToken = $studentLoginResponse->json('token');

        $gradeData = [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'grade_type_id' => $this->gradeType->id,
            'grade_value' => 4,
            'date' => now()->toDateString()
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $studentToken
        ])->postJson('/api/grades', $gradeData);

        $response->assertStatus(403);
    }

    /** @test */
    public function validation_works_for_grade_creation()
    {
        // Неполные данные
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/grades', []);

        $response->assertStatus(422);

        // Неверная оценка
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/grades', [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'grade_type_id' => $this->gradeType->id,
            'grade_value' => 6, // Неверная оценка
            'date' => now()->toDateString()
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function pagination_works_for_grades()
    {
        // Создаем много оценок
        for ($i = 0; $i < 25; $i++) {
            Grade::create([
                'student_id' => $this->student->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'grade_type_id' => $this->gradeType->id,
                'grade_value' => rand(2, 5),
                'date' => now()->subDays($i)->toDateString()
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/grades?per_page=10');

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
}
