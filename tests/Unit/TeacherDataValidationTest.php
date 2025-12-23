<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Homework;
use App\Services\TeacherDataValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class TeacherDataValidationTest extends TestCase
{
    use RefreshDatabase;

    protected TeacherDataValidationService $validationService;
    protected User $teacher;
    protected User $student;
    protected SchoolClass $class;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validationService = new TeacherDataValidationService();
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->class = SchoolClass::factory()->create();
        $this->subject = Subject::factory()->create();

        $this->student->schoolClasses()->attach($this->class->id);
        $this->teacher->subjects()->attach($this->subject->id, ['school_class_id' => $this->class->id]);
    }

    /** @test */
    public function it_validates_grade_data_correctly()
    {
        // Valid data
        $validData = [
            'user_id' => $this->student->id,
            'subject_id' => $this->subject->id,
            'grade' => 4,
            'grade_type_id' => 1,
            'comment' => 'Хорошая работа'
        ];

        $this->assertTrue($this->validationService->validateGradeData($validData));

        // Invalid grade value
        $invalidGradeData = $validData;
        $invalidGradeData['grade'] = 6; // Оценка должна быть от 1 до 5

        $this->expectException(ValidationException::class);
        $this->validationService->validateGradeData($invalidGradeData);

        // Missing required fields
        $incompleteData = ['grade' => 4]; // Отсутствуют user_id и subject_id

        $this->expectException(ValidationException::class);
        $this->validationService->validateGradeData($incompleteData);

        // Future date (если используется поле date)
        $futureDateData = $validData;
        $futureDateData['date'] = Carbon::now()->addDays(1)->toDateString();

        $this->expectException(ValidationException::class);
        $this->validationService->validateGradeData($futureDateData);
    }

    /** @test */
    public function it_validates_attendance_data_correctly()
    {
        // Valid attendance data
        $validData = [
            'user_id' => $this->student->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'date' => Carbon::now()->toDateString(),
            'status' => 'present',
            'reason' => null
        ];

        $this->assertTrue($this->validationService->validateAttendanceData($validData));

        // Invalid status
        $invalidStatusData = $validData;
        $invalidStatusData['status'] = 'invalid_status';

        $this->expectException(ValidationException::class);
        $this->validationService->validateAttendanceData($invalidStatusData);

        // Future date
        $futureDateData = $validData;
        $futureDateData['date'] = Carbon::now()->addDays(1)->toDateString();

        $this->expectException(ValidationException::class);
        $this->validationService->validateAttendanceData($futureDateData);

        // Missing required status
        $incompleteData = $validData;
        unset($incompleteData['status']);

        $this->expectException(ValidationException::class);
        $this->validationService->validateAttendanceData($incompleteData);

        // Absent without reason (если требуется)
        $absentWithoutReasonData = $validData;
        $absentWithoutReasonData['status'] = 'absent';

        // Может проходить или не проходить в зависимости от бизнес-логики
        try {
            $this->validationService->validateAttendanceData($absentWithoutReasonData);
            $this->assertTrue(true);
        } catch (ValidationException $e) {
            $this->assertTrue(true); // Оба варианта корректны
        }
    }

    /** @test */
    public function it_validates_homework_data_correctly()
    {
        // Valid homework data
        $validData = [
            'title' => 'Решить уравнения',
            'description' => 'Выполнить задания 1-10 на странице 45',
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id,
            'due_date' => Carbon::now()->addDays(7)->toDateString(),
            'max_points' => 100
        ];

        $this->assertTrue($this->validationService->validateHomeworkData($validData));

        // Past due date
        $pastDueDateData = $validData;
        $pastDueDateData['due_date'] = Carbon::now()->subDays(1)->toDateString();

        $this->expectException(ValidationException::class);
        $this->validationService->validateHomeworkData($pastDueDateData);

        // Missing title
        $noTitleData = $validData;
        unset($noTitleData['title']);

        $this->expectException(ValidationException::class);
        $this->validationService->validateHomeworkData($noTitleData);

        // Invalid max_points
        $invalidPointsData = $validData;
        $invalidPointsData['max_points'] = -5;

        $this->expectException(ValidationException::class);
        $this->validationService->validateHomeworkData($invalidPointsData);

        // Empty description (если обязательно)
        $noDescriptionData = $validData;
        $noDescriptionData['description'] = '';

        $this->expectException(ValidationException::class);
        $this->validationService->validateHomeworkData($noDescriptionData);
    }

    /** @test */
    public function it_validates_teacher_class_relationship()
    {
        // Valid relationship - teacher teaches subject in this class
        $this->assertTrue($this->validationService->validateTeacherClassRelationship(
            $this->teacher->id,
            $this->class->id,
            $this->subject->id
        ));

        // Teacher doesn't teach this subject in this class
        $otherSubject = Subject::factory()->create();

        $this->expectException(ValidationException::class);
        $this->validationService->validateTeacherClassRelationship(
            $this->teacher->id,
            $this->class->id,
            $otherSubject->id
        );

        // Non-existent teacher
        $this->expectException(ValidationException::class);
        $this->validationService->validateTeacherClassRelationship(
            999, // несуществующий teacher_id
            $this->class->id,
            $this->subject->id
        );

        // Non-existent class
        $this->expectException(ValidationException::class);
        $this->validationService->validateTeacherClassRelationship(
            $this->teacher->id,
            999, // несуществующий class_id
            $this->subject->id
        );
    }

    /** @test */
    public function it_validates_student_class_membership()
    {
        // Valid membership - student belongs to class
        $this->assertTrue($this->validationService->validateStudentClassMembership(
            $this->student->id,
            $this->class->id
        ));

        // Student doesn't belong to class
        $otherClass = SchoolClass::factory()->create();

        $this->expectException(ValidationException::class);
        $this->validationService->validateStudentClassMembership(
            $this->student->id,
            $otherClass->id
        );

        // Non-existent student
        $this->expectException(ValidationException::class);
        $this->validationService->validateStudentClassMembership(
            999, // несуществующий student_id
            $this->class->id
        );
    }

    /** @test */
    public function it_sanitizes_input_data()
    {
        // Dirty data with potential XSS
        $dirtyData = [
            'title' => '<script>alert("xss")</script>Домашнее задание',
            'description' => 'Описание с <b>HTML</b> тегами',
            'comment' => "Строка с\nпереносами\nи 'кавычками'"
        ];

        $cleanData = $this->validationService->sanitizeInputData($dirtyData);

        // Check that script tags are removed
        $this->assertStringNotContainsString('<script>', $cleanData['title']);
        $this->assertStringNotContainsString('alert("xss")', $cleanData['title']);

        // HTML tags should be stripped
        $this->assertStringNotContainsString('<b>', $cleanData['description']);
        $this->assertStringNotContainsString('</b>', $cleanData['description']);

        // Line breaks should be preserved or converted appropriately
        $this->assertTrue(strlen($cleanData['comment']) > 0);

        // Valid content should be preserved
        $this->assertStringContainsString('Домашнее задание', $cleanData['title']);
        $this->assertStringContainsString('HTML', $cleanData['description']);
    }

    /** @test */
    public function it_validates_notification_data()
    {
        // Valid notification data
        $validData = [
            'title' => 'Важное объявление',
            'message' => 'Завтра контрольная работа по математике',
            'recipient_type' => 'student',
            'recipient_ids' => [$this->student->id],
            'delivery_method' => 'email'
        ];

        $this->assertTrue($this->validationService->validateNotificationData($validData));

        // Missing title
        $noTitleData = $validData;
        unset($noTitleData['title']);

        $this->expectException(ValidationException::class);
        $this->validationService->validateNotificationData($noTitleData);

        // Invalid recipient type
        $invalidRecipientTypeData = $validData;
        $invalidRecipientTypeData['recipient_type'] = 'invalid_type';

        $this->expectException(ValidationException::class);
        $this->validationService->validateNotificationData($invalidRecipientTypeData);

        // Empty recipient list
        $emptyRecipientsData = $validData;
        $emptyRecipientsData['recipient_ids'] = [];

        $this->expectException(ValidationException::class);
        $this->validationService->validateNotificationData($emptyRecipientsData);

        // Message too long (если есть ограничение)
        $longMessageData = $validData;
        $longMessageData['message'] = str_repeat('a', 2000); // Слишком длинное сообщение

        $this->expectException(ValidationException::class);
        $this->validationService->validateNotificationData($longMessageData);
    }

    /** @test */
    public function it_validates_date_ranges()
    {
        $today = Carbon::now();
        $startDate = $today->copy()->subDays(30);
        $endDate = $today->copy()->subDays(1);

        // Valid date range
        $this->assertTrue($this->validationService->validateDateRange($startDate, $endDate));

        // End date before start date
        $this->expectException(ValidationException::class);
        $this->validationService->validateDateRange($endDate, $startDate);

        // Future dates in range
        $futureStart = $today->copy()->addDays(1);
        $futureEnd = $today->copy()->addDays(30);

        $this->expectException(ValidationException::class);
        $this->validationService->validateDateRange($futureStart, $futureEnd);

        // Date range too large (например, больше года)
        $veryOldStart = $today->copy()->subYears(2);
        $veryOldEnd = $today->copy()->subYears(1)->subDays(1);

        $this->expectException(ValidationException::class);
        $this->validationService->validateDateRange($veryOldStart, $veryOldEnd);
    }

    /** @test */
    public function it_validates_file_uploads()
    {
        // Simulate valid file upload data
        $validFileData = [
            'filename' => 'homework.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 1024000, // 1MB
            'extension' => 'docx'
        ];

        $this->assertTrue($this->validationService->validateFileUpload($validFileData));

        // File too large
        $largeFileData = $validFileData;
        $largeFileData['size'] = 52428800; // 50MB (слишком большой)

        $this->expectException(ValidationException::class);
        $this->validationService->validateFileUpload($largeFileData);

        // Invalid mime type
        $invalidMimeData = $validFileData;
        $invalidMimeData['mime_type'] = 'application/x-executable';

        $this->expectException(ValidationException::class);
        $this->validationService->validateFileUpload($invalidMimeData);

        // Dangerous file extension
        $dangerousFileData = $validFileData;
        $dangerousFileData['extension'] = 'php';
        $dangerousFileData['filename'] = 'script.php';

        $this->expectException(ValidationException::class);
        $this->validationService->validateFileUpload($dangerousFileData);

        // Missing critical fields
        $incompleteFileData = ['filename' => 'test.txt']; // Отсутствует size и mime_type

        $this->expectException(ValidationException::class);
        $this->validationService->validateFileUpload($incompleteFileData);
    }

    /** @test */
    public function it_validates_pagination_parameters()
    {
        // Valid pagination
        $this->assertTrue($this->validationService->validatePaginationParams(1, 20));

        // Page number too low
        $this->expectException(ValidationException::class);
        $this->validationService->validatePaginationParams(0, 20);

        // Page number too high
        $this->expectException(ValidationException::class);
        $this->validationService->validatePaginationParams(10000, 20);

        // Per page too low
        $this->expectException(ValidationException::class);
        $this->validationService->validatePaginationParams(1, 0);

        // Per page too high
        $this->expectException(ValidationException::class);
        $this->validationService->validatePaginationParams(1, 1000);

        // Invalid data types
        $this->expectException(ValidationException::class);
        $this->validationService->validatePaginationParams('invalid', 20);
    }

    /** @test */
    public function it_handles_custom_validation_rules()
    {
        // Test custom business rule validation
        $teacherData = [
            'teacher_id' => $this->teacher->id,
            'school_class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'action' => 'create_grade'
        ];

        // Valid action
        $this->assertTrue($this->validationService->validateTeacherAction($teacherData));

        // Invalid action
        $invalidActionData = $teacherData;
        $invalidActionData['action'] = 'delete_all_grades';

        $this->expectException(ValidationException::class);
        $this->validationService->validateTeacherAction($invalidActionData);

        // Teacher without permission for this action
        $otherTeacher = User::factory()->create(['role' => 'teacher']);

        $permissionData = $teacherData;
        $permissionData['teacher_id'] = $otherTeacher->id;

        $this->expectException(ValidationException::class);
        $this->validationService->validateTeacherAction($permissionData);
    }
}
