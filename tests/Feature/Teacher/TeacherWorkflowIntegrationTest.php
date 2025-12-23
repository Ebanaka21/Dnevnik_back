<?php

namespace Tests\Feature\Teacher;

use Tests\TestCase;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class TeacherWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected User $parent;
    protected SchoolClass $class;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем основных пользователей
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->parent = User::factory()->create(['role' => 'parent']);

        // Создаем класс и предмет
        $this->class = SchoolClass::factory()->create();
        $this->subject = Subject::factory()->create();

        // Связываем пользователей с классом
        $this->student->schoolClasses()->attach($this->class->id);
        $this->parent->children()->attach($this->student->id);

        // Связываем учителя с предметом в классе
        $this->teacher->subjects()->attach($this->subject->id, ['school_class_id' => $this->class->id]);

        // Авторизуем учителя
        Auth::login($this->teacher);
    }

    /** @test */
    public function complete_teacher_workflow_from_login_to_notification()
    {
        // Step 1: Login (already done in setUp)
        $this->assertAuthenticatedAs($this->teacher);

        // Step 2: View classes overview
        $response = $this->getJson('/api/teacher/classes');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'student_count',
                    'subjects'
                ]
            ]
        ]);

        // Verify teacher can see their classes
        $classes = $response->json('data');
        $this->assertCount(1, $classes);
        $this->assertEquals($this->class->id, $classes[0]['id']);

        // Step 3: Create a new grade
        $gradeData = [
            'user_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'grade' => 4,
            'grade_type_id' => 1,
            'comment' => 'Хорошая работа на уроке'
        ];

        $gradeResponse = $this->postJson('/api/teacher/grades', $gradeData);
        $gradeResponse->assertStatus(201);
        $gradeResponse->assertJsonStructure([
            'data' => [
                'id',
                'user_id',
                'subject_id',
                'grade',
                'comment',
                'created_at'
            ]
        ]);

        $gradeId = $gradeResponse->json('data.id');
        $this->assertDatabaseHas('grades', [
            'id' => $gradeId,
            'user_id' => $this->student->id,
            'grade' => 4
        ]);

        // Step 4: Mark attendance for today
        $attendanceData = [
            'user_id' => $this->student->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'date' => Carbon::now()->toDateString(),
            'status' => 'present',
            'reason' => null
        ];

        $attendanceResponse = $this->postJson('/api/teacher/attendance', $attendanceData);
        $attendanceResponse->assertStatus(201);
        $attendanceResponse->assertJsonStructure([
            'data' => [
                'id',
                'user_id',
                'status',
                'date'
            ]
        ]);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $this->student->id,
            'status' => 'present',
            'date' => Carbon::now()->toDateString()
        ]);

        // Step 5: Create a homework assignment
        $homeworkData = [
            'title' => 'Решить уравнения',
            'description' => 'Выполните задания 1-10 на странице 45 учебника',
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id,
            'due_date' => Carbon::now()->addDays(7)->toDateString(),
            'max_points' => 100
        ];

        $homeworkResponse = $this->postJson('/api/teacher/homework', $homeworkData);
        $homeworkResponse->assertStatus(201);
        $homeworkResponse->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'description',
                'due_date',
                'max_points'
            ]
        ]);

        $homeworkId = $homeworkResponse->json('data.id');
        $this->assertDatabaseHas('homeworks', [
            'id' => $homeworkId,
            'title' => 'Решить уравнения'
        ]);

        // Step 6: Send notification to parent
        $notificationData = [
            'title' => 'Новая оценка',
            'message' => "Ваш ребенок получил оценку 4 по предмету {$this->subject->name}",
            'recipient_type' => 'parent',
            'recipient_ids' => [$this->parent->id],
            'delivery_method' => 'email'
        ];

        $notificationResponse = $this->postJson('/api/teacher/notifications', $notificationData);
        $notificationResponse->assertStatus(201);
        $notificationResponse->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'message',
                'status'
            ]
        ]);

        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'title' => 'Новая оценка',
            'recipient_id' => $this->parent->id,
            'status' => 'sent'
        ]);

        // Step 7: View statistics
        $statsResponse = $this->getJson('/api/teacher/classes/' . $this->class->id . '/statistics');
        $statsResponse->assertStatus(200);
        $statsResponse->assertJsonStructure([
            'data' => [
                'average_grade',
                'attendance_rate',
                'homework_completion_rate',
                'student_count'
            ]
        ]);

        $stats = $statsResponse->json('data');
        $this->assertEquals(4.0, $stats['average_grade']);
        $this->assertEquals(100.0, $stats['attendance_rate']);

        // Step 8: Verify all data relationships are correct
        $this->assertDatabaseHas('teacher_student_relations', [
            'teacher_id' => $this->teacher->id,
            'student_id' => $this->student->id
        ]);

        $this->assertDatabaseHas('parent_students', [
            'parent_id' => $this->parent->id,
            'student_id' => $this->student->id
        ]);

        // Step 9: Test pagination and filtering
        $gradesResponse = $this->getJson('/api/teacher/grades?page=1&per_page=10');
        $gradesResponse->assertStatus(200);
        $gradesResponse->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total'
            ]
        ]);

        $gradesMeta = $gradesResponse->json('meta');
        $this->assertEquals(1, $gradesMeta['total']); // We created one grade
    }

    /** @test */
    public function teacher_can_view_student_detailed_profile()
    {
        // Setup: Create additional data
        Grade::factory()->count(5)->create([
            'user_id' => $this->student->id,
            'subject_id' => $this->subject->id
        ]);

        Attendance::factory()->count(10)->create([
            'user_id' => $this->student->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'status' => 'present'
        ]);

        Homework::factory()->count(3)->create([
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id
        ]);

        // Test: View detailed student profile
        $response = $this->getJson('/api/teacher/classes/' . $this->class->id . '/students/' . $this->student->id);
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'grades' => [
                    '*' => [
                        'id',
                        'grade',
                        'subject',
                        'created_at'
                    ]
                ],
                'attendance' => [
                    'total_lessons',
                    'present_count',
                    'attendance_percentage'
                ],
                'homework' => [
                    'total_assigned',
                    'completed_count',
                    'completion_rate'
                ],
                'parents' => [
                    '*' => [
                        'id',
                        'name',
                        'email'
                    ]
                ]
            ]
        ]);

        $studentData = $response->json('data');
        $this->assertEquals($this->student->id, $studentData['id']);
        $this->assertEquals(5, count($studentData['grades']));
        $this->assertEquals(100.0, $studentData['attendance']['attendance_percentage']);
        $this->assertEquals(3, count($studentData['parents']));
    }

    /** @test */
    public function teacher_can_perform_bulk_operations()
    {
        // Setup: Create multiple students in the class
        $students = User::factory()->count(5)->create(['role' => 'student']);
        foreach ($students as $student) {
            $student->schoolClasses()->attach($this->class->id);
        }

        // Test: Bulk attendance marking
        $bulkAttendanceData = [
            'date' => Carbon::now()->toDateString(),
            'subject_id' => $this->subject->id,
            'attendance_records' => [
                ['user_id' => $students[0]->id, 'status' => 'present'],
                ['user_id' => $students[1]->id, 'status' => 'present'],
                ['user_id' => $students[2]->id, 'status' => 'absent', 'reason' => 'Болел'],
                ['user_id' => $students[3]->id, 'status' => 'present'],
                ['user_id' => $students[4]->id, 'status' => 'late']
            ]
        ];

        $response = $this->postJson('/api/teacher/attendance/bulk', $bulkAttendanceData);
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'created_count',
            'data' => [
                '*' => [
                    'user_id',
                    'status',
                    'date'
                ]
            ]
        ]);

        $result = $response->json();
        $this->assertEquals(5, $result['created_count']);

        // Verify all records were created
        foreach ($bulkAttendanceData['attendance_records'] as $record) {
            $this->assertDatabaseHas('attendances', [
                'user_id' => $record['user_id'],
                'status' => $record['status'],
                'date' => Carbon::now()->toDateString()
            ]);
        }

        // Test: Bulk grade assignment
        $bulkGradeData = [
            'subject_id' => $this->subject->id,
            'grade_type_id' => 1,
            'grades' => [
                ['user_id' => $students[0]->id, 'grade' => 5, 'comment' => 'Отлично'],
                ['user_id' => $students[1]->id, 'grade' => 4, 'comment' => 'Хорошо'],
                ['user_id' => $students[2]->id, 'grade' => 3, 'comment' => 'Удовлетворительно']
            ]
        ];

        $bulkResponse = $this->postJson('/api/teacher/grades/bulk', $bulkGradeData);
        $bulkResponse->assertStatus(201);
        $bulkResponse->assertJsonStructure([
            'message',
            'created_count',
            'data' => [
                '*' => [
                    'user_id',
                    'grade'
                ]
            ]
        ]);

        $bulkResult = $bulkResponse->json();
        $this->assertEquals(3, $bulkResult['created_count']);
    }

    /** @test */
    public function teacher_can_manage_class_schedules()
    {
        // Test: View class schedule
        $scheduleResponse = $this->getJson('/api/teacher/classes/' . $this->class->id . '/schedule');
        $scheduleResponse->assertStatus(200);
        $scheduleResponse->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'day_of_week',
                    'time_start',
                    'time_end',
                    'subject',
                    'classroom'
                ]
            ]
        ]);

        // Test: Create a new lesson
        $lessonData = [
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'day_of_week' => 1, // Monday
            'time_start' => '09:00',
            'time_end' => '10:00',
            'classroom' => 'Кабинет 101'
        ];

        $lessonResponse = $this->postJson('/api/teacher/lessons', $lessonData);
        $lessonResponse->assertStatus(201);
        $lessonResponse->assertJsonStructure([
            'data' => [
                'id',
                'day_of_week',
                'time_start',
                'time_end',
                'subject',
                'classroom'
            ]
        ]);

        $this->assertDatabaseHas('lessons', [
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'day_of_week' => 1,
            'classroom' => 'Кабинет 101'
        ]);
    }

    /** @test */
    public function teacher_can_send_targeted_notifications()
    {
        // Test: Send notification to specific students
        $targetedNotification = [
            'title' => 'Напоминание о домашнем задании',
            'message' => 'Не забудьте сдать домашнее задание до пятницы',
            'recipient_type' => 'student',
            'recipient_ids' => [$this->student->id],
            'delivery_method' => 'in-app'
        ];

        $response = $this->postJson('/api/teacher/notifications', $targetedNotification);
        $response->assertStatus(201);

        $notificationId = $response->json('data.id');
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'title' => 'Напоминание о домашнем задании',
            'recipient_id' => $this->student->id
        ]);

        // Test: Send notification to all parents in class
        $parentNotification = [
            'title' => 'Родительское собрание',
            'message' => 'Родительское собрание состоится в пятницу в 18:00',
            'recipient_type' => 'parent',
            'recipient_ids' => [$this->parent->id], // In real scenario, would be all parents
            'delivery_method' => 'email'
        ];

        $parentResponse = $this->postJson('/api/teacher/notifications', $parentNotification);
        $parentResponse->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'title' => 'Родительское собрание',
            'recipient_id' => $this->parent->id,
            'delivery_method' => 'email'
        ]);
    }

    /** @test */
    public function teacher_can_generate_and_view_reports()
    {
        // Setup: Create sample data for reporting
        Grade::factory()->count(10)->create([
            'user_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'created_at' => Carbon::now()->subDays(30)
        ]);

        Attendance::factory()->count(20)->create([
            'user_id' => $this->student->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'status' => 'present',
            'created_at' => Carbon::now()->subDays(30)
        ]);

        // Test: Generate performance report
        $reportResponse = $this->getJson('/api/teacher/reports/performance?school_class_id=' . $this->class->id . '&subject_id=' . $this->subject->id);
        $reportResponse->assertStatus(200);
        $reportResponse->assertJsonStructure([
            'data' => [
                'school_class_id',
                'subject_id',
                'period',
                'statistics' => [
                    'average_grade',
                    'grade_distribution',
                    'attendance_rate',
                    'homework_completion_rate'
                ],
                'students' => [
                    '*' => [
                        'id',
                        'name',
                        'average_grade',
                        'attendance_percentage'
                    ]
                ]
            ]
        ]);

        $report = $reportResponse->json('data');
        $this->assertEquals($this->class->id, $report['school_class_id']);
        $this->assertEquals($this->subject->id, $report['subject_id']);
        $this->assertNotNull($report['statistics']['average_grade']);
        $this->assertNotNull($report['statistics']['attendance_rate']);

        // Test: Export report
        $exportResponse = $this->getJson('/api/teacher/reports/performance/export?school_class_id=' . $this->class->id . '&format=pdf');
        $exportResponse->assertStatus(200);
        $exportResponse->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function unauthorized_access_is_prevented()
    {
        // Test: Student trying to access teacher endpoints
        Auth::logout();
        Auth::login($this->student);

        $response = $this->getJson('/api/teacher/classes');
        $response->assertStatus(403);

        // Test: Parent trying to access teacher endpoints
        Auth::logout();
        Auth::login($this->parent);

        $response = $this->getJson('/api/teacher/grades');
        $response->assertStatus(403);

        // Test: Teacher accessing wrong class data
        Auth::logout();
        Auth::login($this->teacher);

        $otherClass = SchoolClass::factory()->create();
        $response = $this->getJson('/api/teacher/classes/' . $otherClass->id);
        $response->assertStatus(403);

        // Test: Teacher accessing student from wrong class
        $otherStudent = User::factory()->create(['role' => 'student']);
        $otherClass->students()->attach($otherStudent->id);

        $response = $this->getJson('/api/teacher/classes/' . $this->class->id . '/students/' . $otherStudent->id);
        $response->assertStatus(404); // Student not found in this class
    }

    /** @test */
    public function data_validation_works_correctly_in_workflow()
    {
        // Test: Invalid grade data
        $invalidGradeData = [
            'user_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'grade' => 6, // Invalid grade (should be 1-5)
            'grade_type_id' => 1
        ];

        $response = $this->postJson('/api/teacher/grades', $invalidGradeData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['grade']);

        // Test: Invalid attendance data
        $invalidAttendanceData = [
            'user_id' => $this->student->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'date' => Carbon::now()->addDays(1)->toDateString(), // Future date
            'status' => 'invalid_status' // Invalid status
        ];

        $response = $this->postJson('/api/teacher/attendance', $invalidAttendanceData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date', 'status']);

        // Test: Invalid homework data
        $invalidHomeworkData = [
            'title' => '', // Empty title
            'description' => 'Too short', // Too short description
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id,
            'due_date' => Carbon::now()->subDays(1)->toDateString(), // Past date
            'max_points' => -5 // Negative points
        ];

        $response = $this->postJson('/api/teacher/homework', $invalidHomeworkData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'description', 'due_date', 'max_points']);
    }

    /** @test */
    public function system_handles_large_amounts_of_data_efficiently()
    {
        // Setup: Create large dataset
        $students = User::factory()->count(50)->create(['role' => 'student']);
        foreach ($students as $student) {
            $student->schoolClasses()->attach($this->class->id);
        }

        // Create 1000 grades
        Grade::factory()->count(1000)->create([
            'subject_id' => $this->subject->id
        ]);

        // Test: Pagination works with large datasets
        $response = $this->getJson('/api/teacher/grades?page=1&per_page=50');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(50, $data);

        $meta = $response->json('meta');
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(50, $meta['per_page']);
        $this->assertEquals(1000, $meta['total']);

        // Test: Filtering works with large datasets
        $filteredResponse = $this->getJson('/api/teacher/grades?grade=5&page=1&per_page=20');
        $filteredResponse->assertStatus(200);

        $filteredData = $filteredResponse->json('data');
        $this->assertCount(20, $filteredData);

        // Verify all returned grades are 5s
        foreach ($filteredData as $grade) {
            $this->assertEquals(5, $grade['grade']);
        }
    }

    /** @test */
    public function real_time_notifications_work_integration()
    {
        // Test: Create notification and verify real-time delivery
        $notificationData = [
            'title' => 'Экстренное уведомление',
            'message' => 'Занятия отменены из-за карантина',
            'recipient_type' => 'student',
            'recipient_ids' => [$this->student->id],
            'delivery_method' => 'in-app'
        ];

        $response = $this->postJson('/api/teacher/notifications', $notificationData);
        $response->assertStatus(201);

        $notificationId = $response->json('data.id');

        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'title' => 'Экстренное уведомление'
        ]);

        // Test: Mark notification as read (simulating student reading it)
        $readResponse = $this->patchJson('/api/teacher/notifications/' . $notificationId . '/read');
        $readResponse->assertStatus(200);

        // Verify notification is marked as read
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'is_read' => true,
            'read_at' => Carbon::now()->toDateTimeString()
        ]);
    }
}
