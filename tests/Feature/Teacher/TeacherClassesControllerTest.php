<?php

namespace Tests\Feature\Teacher;

use App\Models\User;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherClassesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $teacher;
    protected $student;
    protected $subject;
    protected $class;
    protected $schedule;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

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

        // Создаем расписание
        $this->schedule = Schedule::create([
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'day_of_week' => 1,
            'lesson_number' => 1,
            'room' => 'Кабинет 101'
        ]);

        // Авторизуемся как учитель
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'teacher@test.com',
            'password' => 'password'
        ]);

        $this->token = $loginResponse->json('token');
    }

    /** @test */
    public function teacher_can_get_all_their_classes()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'classes' => [
                    '*' => [
                        'id',
                        'name',
                        'academic_year',
                        'student_count',
                        'teacher_subjects',
                        'students' => [
                            '*' => ['id', 'name', 'email', 'student_number']
                        ],
                        'teachers',
                        'gradeLevel'
                    ]
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function teacher_can_get_class_details()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $this->class->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'academic_year',
                'students' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'student_number',
                        'date_of_birth',
                        'phone',
                        'address',
                        'parents'
                    ]
                ],
                'teachers',
                'subjects',
                'schedules',
                'statistics' => [
                    'total_students',
                    'total_subjects',
                    'total_lessons_per_week'
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
        ])->getJson('/api/teacher/classes/' . $otherClass->id);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Класс не найден']);
    }

    /** @test */
    public function teacher_can_get_students_of_class()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $this->class->id . '/students');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'students' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'student_number',
                        'date_of_birth',
                        'phone',
                        'address',
                        'parent_count',
                        'parents'
                    ]
                ],
                'school_class_id',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);

        // Проверяем, что ученик содержит информацию о родителях
        $students = $response->json('students');
        if (count($students) > 0) {
            $this->assertArrayHasKey('parents', $students[0]);
        }
    }

    /** @test */
    public function teacher_can_get_class_schedule()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $this->class->id . '/schedule');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'school_class_id',
                'schedule_by_day' => [
                    'Понедельник' => [
                        '*' => [
                            'id',
                            'day_of_week',
                            'lesson_number',
                            'room',
                            'subject' => ['id', 'name', 'description'],
                            'teacher' => ['id', 'name', 'email']
                        ]
                    ]
                ],
                'raw_schedule',
                'total_lessons'
            ]);

        // Проверяем, что расписание сгруппировано по дням недели
        $scheduleByDay = $response->json('schedule_by_day');
        $this->assertIsArray($scheduleByDay);
        $this->assertNotEmpty($scheduleByDay);
    }

    /** @test */
    public function teacher_can_get_subjects_they_teach_in_class()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $this->class->id . '/subjects');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'school_class_id',
                'subjects' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'teachers' => [
                            '*' => ['id', 'name', 'email']
                        ]
                    ]
                ],
                'total_subjects'
            ]);

        // Проверяем, что возвращается только тот предмет, который ведет учитель
        $subjects = $response->json('subjects');
        $this->assertEquals(1, count($subjects));
        $this->assertEquals($this->subject->id, $subjects[0]['id']);
    }

    /** @test */
    public function pagination_works_for_classes()
    {
        // Создаем дополнительные классы
        for ($i = 0; $i < 5; $i++) {
            $class = SchoolClass::create([
                'name' => '10' . chr(65 + $i), // 10А, 10Б, 10В и т.д.
                'academic_year' => '2024-2025'
            ]);

            $class->subjects()->attach($this->subject->id);
            $class->teachers()->attach($this->teacher->id);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes?per_page=3');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'classes',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function pagination_works_for_students()
    {
        // Создаем много учеников
        for ($i = 0; $i < 25; $i++) {
            $student = User::factory()->create([
                'role_id' => $this->student->role_id,
                'name' => 'Ученик ' . ($i + 1),
                'email' => 'student' . ($i + 1) . '@test.com',
                'password' => bcrypt('password')
            ]);
            $student->studentClasses()->attach($this->class->id);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $this->class->id . '/students?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'students',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function unauthorized_user_cannot_access_classes()
    {
        $response = $this->getJson('/api/teacher/classes');

        $response->assertStatus(401);
    }

    /** @test */
    public function student_cannot_access_teacher_classes()
    {
        // Авторизуемся как ученик
        $studentLoginResponse = $this->postJson('/api/login', [
            'email' => 'student@test.com',
            'password' => 'password'
        ]);

        $studentToken = $studentLoginResponse->json('token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $studentToken
        ])->getJson('/api/teacher/classes');

        $response->assertStatus(403);
    }

    /** @test */
    public function class_details_include_comprehensive_information()
    {
        // Создаем родителя для ученика
        $parentRole = \App\Models\Role::create(['name' => 'parent']);
        $parent = User::factory()->create([
            'role_id' => $parentRole->id,
            'name' => 'Тестовый Родитель',
            'email' => 'parent@test.com',
            'password' => bcrypt('password')
        ]);

        // Связываем родителя с учеником
        \App\Models\ParentStudent::create([
            'parent_id' => $parent->id,
            'student_id' => $this->student->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $this->class->id);

        $response->assertStatus(200);

        $classData = $response->json();

        // Проверяем наличие всех ключевых данных
        $this->assertArrayHasKey('students', $classData);
        $this->assertArrayHasKey('teachers', $classData);
        $this->assertArrayHasKey('subjects', $classData);
        $this->assertArrayHasKey('schedules', $classData);
        $this->assertArrayHasKey('statistics', $classData);

        // Проверяем структуру учеников
        if (count($classData['students']) > 0) {
            $student = $classData['students'][0];
            $this->assertArrayHasKey('parents', $student);
        }
    }

    /** @test */
    public function schedule_includes_all_relevant_data()
    {
        // Добавляем еще несколько уроков в расписание
        for ($day = 2; $day <= 5; $day++) {
            Schedule::create([
                'school_class_id' => $this->class->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'day_of_week' => $day,
                'lesson_number' => 1,
                'room' => 'Кабинет 101'
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $this->class->id . '/schedule');

        $response->assertStatus(200);

        $scheduleData = $response->json();

        // Проверяем, что расписание сгруппировано по дням
        $scheduleByDay = $scheduleData['schedule_by_day'];
        $this->assertGreaterThanOrEqual(1, count($scheduleByDay));

        // Проверяем дни недели
        $expectedDays = ['Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница'];
        foreach ($expectedDays as $day) {
            if (isset($scheduleByDay[$day])) {
                $this->assertIsArray($scheduleByDay[$day]);
            }
        }
    }

    /** @test */
    public function subjects_include_teacher_information()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $this->class->id . '/subjects');

        $response->assertStatus(200);

        $subjects = $response->json('subjects');

        // Проверяем, что у каждого предмета есть информация о учителях
        foreach ($subjects as $subject) {
            $this->assertArrayHasKey('teachers', $subject);
            $this->assertIsArray($subject['teachers']);

            // У предмета должен быть наш учитель
            $teacherIds = collect($subject['teachers'])->pluck('id')->toArray();
            $this->assertContains($this->teacher->id, $teacherIds);
        }
    }

    /** @test */
    public function class_statistics_are_accurate()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $this->class->id);

        $response->assertStatus(200);

        $classData = $response->json();
        $stats = $classData['statistics'];

        // Проверяем структуру статистики
        $this->assertArrayHasKey('total_students', $stats);
        $this->assertArrayHasKey('total_subjects', $stats);
        $this->assertArrayHasKey('total_lessons_per_week', $stats);

        // Проверяем корректность данных
        $this->assertEquals(1, $stats['total_students']);
        $this->assertEquals(1, $stats['total_subjects']);
        $this->assertEquals(1, $stats['total_lessons_per_week']);
    }

    /** @test */
    public function teacher_can_filter_classes_pagination()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes?page=1&per_page=5');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'classes',
                'pagination'
            ]);

        $pagination = $response->json('pagination');
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(5, $pagination['per_page']);
    }

    /** @test */
    public function empty_class_handles_gracefully()
    {
        // Создаем пустой класс
        $emptyClass = SchoolClass::create([
            'name' => '11А',
            'academic_year' => '2024-2025'
        ]);

        $emptyClass->subjects()->attach($this->subject->id);
        $emptyClass->teachers()->attach($this->teacher->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $emptyClass->id . '/students');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'students',
                'school_class_id',
                'pagination'
            ]);

        // Проверяем, что учеников нет
        $students = $response->json('students');
        $this->assertEquals(0, count($students));
    }

    /** @test */
    public function schedule_without_lessons_handles_gracefully()
    {
        // Создаем класс без расписания
        $classWithoutSchedule = SchoolClass::create([
            'name' => '11Б',
            'academic_year' => '2024-2025'
        ]);

        $classWithoutSchedule->subjects()->attach($this->subject->id);
        $classWithoutSchedule->teachers()->attach($this->teacher->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/teacher/classes/' . $classWithoutSchedule->id . '/schedule');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'school_class_id',
                'schedule_by_day',
                'raw_schedule',
                'total_lessons'
            ]);

        // Проверяем, что расписание пустое
        $scheduleData = $response->json();
        $this->assertEquals(0, $scheduleData['total_lessons']);
        $this->assertEmpty($scheduleData['raw_schedule']);
    }

    /** @test */
    public function class_access_is_properly_restricted()
    {
        // Создаем другого учителя
        $otherTeacher = User::factory()->create([
            'role_id' => $this->teacher->role_id,
            'name' => 'Другой Учитель',
            'email' => 'other_teacher@test.com',
            'password' => bcrypt('password')
        ]);

        $otherTeacher->subjects()->attach($this->subject->id);

        // Авторизуемся как другой учитель
        $otherLoginResponse = $this->postJson('/api/login', [
            'email' => 'other_teacher@test.com',
            'password' => 'password'
        ]);

        $otherToken = $otherLoginResponse->json('token');

        // Пытаемся получить доступ к классу первого учителя
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $otherToken
        ])->getJson('/api/teacher/classes/' . $this->class->id);

        $response->assertStatus(404);
    }
}
