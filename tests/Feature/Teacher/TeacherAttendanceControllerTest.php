<?php

namespace Tests\Feature\Teacher;

use App\Models\User;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherAttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $teacher;
    protected $student;
    protected $subject;
    protected $class;
    protected $attendance;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем учителя
        $this->teacher = User::factory()->create([
            'role' => 'teacher',
            'name' => 'Тестовый Учитель',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password')
        ]);

        // Создаем ученика
        $this->student = User::factory()->create([
            'role' => 'student',
            'name' => 'Тестовый Ученик',
            'email' => 'student@test.com',
            'password' => bcrypt('password')
        ]);

        // Создаем предмет
        $this->subject = Subject::create([
            'name' => 'Математика',
            'subject_code' => 'MATH',
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

        // Создаем запись посещаемости
        $this->attendance = Attendance::create([
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'lesson_number' => 1,
            'comment' => 'Присутствовал'
        ]);

        // Авторизуемся как учитель
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'teacher@test.com',
            'password' => 'password'
        ]);

        $this->token = $loginResponse->json('token');
    }

    /** @test */
    public function teacher_can_get_all_attendance_records()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'attendance' => [
                    '*' => [
                        'id',
                        'date',
                        'status',
                        'lesson_number',
                        'reason',
                        'comment',
                        'student' => ['id', 'name', 'email'],
                        'subject' => ['id', 'name']
                    ]
                ],
                'pagination',
                'statistics' => [
                    'total_records',
                    'present',
                    'absent',
                    'late',
                    'excused',
                    'attendance_percentage'
                ]
            ]);
    }

    /** @test */
    public function teacher_can_create_attendance_record()
    {
        $attendanceData = [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'lesson_number' => 2,
            'comment' => 'Отлично'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance', $attendanceData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'date',
                'status',
                'lesson_number',
                'reason',
                'comment',
                'student',
                'subject'
            ]);

        $this->assertDatabaseHas('attendances', [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'status' => 'present'
        ]);
    }

    /** @test */
    public function teacher_cannot_create_duplicate_attendance_record()
    {
        $attendanceData = [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'lesson_number' => 1 // Такой же номер урока как у существующей записи
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance', $attendanceData);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Запись посещаемости уже существует']);
    }

    /** @test */
    public function teacher_can_update_attendance_record()
    {
        $updateData = [
            'status' => 'absent',
            'reason' => 'Болезнь',
            'comment' => 'Отсутствовал по болезни'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson('/api/teacher/attendance/' . $this->attendance->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->attendance->id,
                'status' => 'absent',
                'reason' => 'Болезнь'
            ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $this->attendance->id,
            'status' => 'absent'
        ]);
    }

    /** @test */
    public function teacher_can_delete_attendance_record()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson('/api/teacher/attendance/' . $this->attendance->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Запись посещаемости успешно удалена']);

        $this->assertDatabaseMissing('attendances', ['id' => $this->attendance->id]);
    }

    /** @test */
    public function teacher_can_get_class_attendance_by_date()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance/classes/' . $this->class->id . '/date/' . now()->toDateString());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'class' => ['id', 'name', 'academic_year'],
                'date',
                'student_attendance' => [
                    '*' => [
                        'student' => ['id', 'name', 'email'],
                        'attendance_records',
                        'has_attendance'
                    ]
                ],
                'attendance_records',
                'statistics' => [
                    'date',
                    'total_students',
                    'total_attendance_records',
                    'present',
                    'absent',
                    'late',
                    'excused',
                    'attendance_percentage'
                ]
            ]);
    }

    /** @test */
    public function teacher_cannot_access_class_they_dont_teach()
    {
        // Создаем другой класс
        $otherClass = SchoolClass::create([
            'name' => '10Б',
            'academic_year' => '2024-2025'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance/classes/' . $otherClass->id . '/date/' . now()->toDateString());

        $response->assertStatus(403)
            ->assertJson(['error' => 'У вас нет доступа к этому классу']);
    }

    /** @test */
    public function teacher_can_create_bulk_attendance_records()
    {
        // Создаем еще одного ученика
        $anotherStudent = User::factory()->create([
            'role' => 'student',
            'name' => 'Другой Ученик',
            'email' => 'another@test.com',
            'password' => bcrypt('password')
        ]);
        $anotherStudent->studentClasses()->attach($this->class->id);

        $bulkData = [
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'date' => now()->toDateString(),
            'lesson_number' => 3,
            'attendance_data' => [
                [
                    'student_id' => $this->student->id,
                    'status' => 'present'
                ],
                [
                    'student_id' => $anotherStudent->id,
                    'status' => 'late',
                    'reason' => 'Опоздал'
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance/bulk', $bulkData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'created',
                'errors',
                'created_count',
                'errors_count'
            ]);

        $this->assertEquals(2, $response->json('created_count'));
    }

    /** @test */
    public function teacher_cannot_create_bulk_attendance_for_subject_they_dont_teach()
    {
        $otherSubject = Subject::create([
            'name' => 'Физика',
            'subject_code' => 'PHYS',
            'description' => 'Другой предмет'
        ]);

        $bulkData = [
            'school_class_id' => $this->class->id,
            'subject_id' => $otherSubject->id,
            'date' => now()->toDateString(),
            'lesson_number' => 1,
            'attendance_data' => [
                [
                    'student_id' => $this->student->id,
                    'status' => 'present'
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance/bulk', $bulkData);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Вы не ведете этот предмет в данном классе']);
    }

    /** @test */
    public function teacher_can_get_attendance_statistics()
    {
        // Создаем дополнительные записи для статистики
        for ($i = 0; $i < 5; $i++) {
            Attendance::create([
                'student_id' => $this->student->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'date' => now()->subDays($i)->toDateString(),
                'status' => ['present', 'absent', 'late'][array_rand(['present', 'absent', 'late'])],
                'lesson_number' => $i + 1
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'overview' => [
                    'total_records',
                    'present',
                    'absent',
                    'late',
                    'excused',
                    'overall_attendance_percentage'
                ],
                'by_subjects' => [
                    '*' => [
                        'subject' => ['id', 'name'],
                        'total_records',
                        'present',
                        'absent',
                        'late',
                        'excused',
                        'attendance_percentage'
                    ]
                ],
                'by_students' => [
                    '*' => [
                        'student' => ['id', 'name', 'email'],
                        'total_lessons',
                        'present',
                        'absent',
                        'late',
                        'excused',
                        'attendance_percentage'
                    ]
                ],
                'monthly'
            ]);
    }

    /** @test */
    public function teacher_can_filter_attendance_by_student()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance?student_id=' . $this->student->id);

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_filter_attendance_by_subject()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance?subject_id=' . $this->subject->id);

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_filter_attendance_by_status()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance?status=present');

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_filter_attendance_by_date_range()
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance?date_from=' . $startDate . '&date_to=' . $endDate);

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_search_attendance_by_student_name()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance?student_search=Ученик');

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_filter_attendance_by_lesson_number()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance?lesson_number=1');

        $response->assertStatus(200);
    }

    /** @test */
    public function teacher_can_sort_attendance_records()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance?sort_by=status&sort_order=asc');

        $response->assertStatus(200);
    }

    /** @test */
    public function pagination_works_for_attendance()
    {
        // Создаем много записей посещаемости
        for ($i = 0; $i < 25; $i++) {
            Attendance::create([
                'student_id' => $this->student->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'date' => now()->subDays($i)->toDateString(),
                'status' => 'present',
                'lesson_number' => 1
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/attendance?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'attendance',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function unauthorized_user_cannot_access_attendance()
    {
        $response = $this->getJson('/api/teacher/attendance');

        $response->assertStatus(401);
    }

    /** @test */
    public function student_cannot_access_attendance_management()
    {
        // Авторизуемся как ученик
        $studentLoginResponse = $this->postJson('/api/login', [
            'email' => 'student@test.com',
            'password' => 'password'
        ]);

        $studentToken = $studentLoginResponse->json('token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $studentToken
        ])->getJson('/api/teacher/attendance');

        $response->assertStatus(403);
    }

    /** @test */
    public function validation_works_for_attendance_creation()
    {
        // Неполные данные
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance', []);

        $response->assertStatus(422);

        // Неверный статус
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance', [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'date' => now()->toDateString(),
            'status' => 'invalid_status'
        ]);

        $response->assertStatus(422);

        // Неверный номер урока
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance', [
            'student_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'lesson_number' => 10 // Неверный номер урока
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function teacher_cannot_mark_non_existent_student_as_present()
    {
        $attendanceData = [
            'student_id' => 999,
            'subject_id' => $this->subject->id,
            'date' => now()->toDateString(),
            'status' => 'present'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance', $attendanceData);

        $response->assertStatus(422);
    }

    /** @test */
    public function teacher_cannot_mark_student_for_subject_they_dont_teach()
    {
        $otherSubject = Subject::create([
            'name' => 'Физика',
            'description' => 'Другой предмет'
        ]);

        $attendanceData = [
            'student_id' => $this->student->id,
            'subject_id' => $otherSubject->id,
            'date' => now()->toDateString(),
            'status' => 'present'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance', $attendanceData);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Вы не ведете этот предмет']);
    }

    /** @test */
    public function teacher_can_mark_student_as_different_statuses()
    {
        $statuses = ['present', 'absent', 'late', 'excused'];

        foreach ($statuses as $status) {
            $attendanceData = [
                'student_id' => $this->student->id,
                'subject_id' => $this->subject->id,
                'date' => now()->toDateString(),
                'status' => $status,
                'lesson_number' => rand(1, 8),
                'reason' => $status === 'absent' ? 'Болезнь' : null
            ];

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token
            ])->postJson('/api/teacher/attendance', $attendanceData);

            $response->assertStatus(201);
        }
    }

    /** @test */
    public function bulk_attendance_handles_errors_gracefully()
    {
        // Создаем ученика не из класса
        $outsiderStudent = User::factory()->create([
            'role' => 'student',
            'name' => 'Чужой Ученик',
            'email' => 'outsider@test.com',
            'password' => bcrypt('password')
        ]);

        $bulkData = [
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'date' => now()->toDateString(),
            'lesson_number' => 1,
            'attendance_data' => [
                [
                    'student_id' => $this->student->id, // Корректный ученик
                    'status' => 'present'
                ],
                [
                    'student_id' => $outsiderStudent->id, // Неправильный ученик
                    'status' => 'absent'
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/teacher/attendance/bulk', $bulkData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'created',
                'errors',
                'created_count',
                'errors_count'
            ]);

        // Должно быть 1 созданная запись и 1 ошибка
        $this->assertEquals(1, $response->json('created_count'));
        $this->assertEquals(1, $response->json('errors_count'));
    }
}
