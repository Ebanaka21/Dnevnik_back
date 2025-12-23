<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Services\TeacherStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class TeacherStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected TeacherStatisticsService $statisticsService;
    protected User $teacher;
    protected SchoolClass $class;
    protected Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statisticsService = new TeacherStatisticsService();
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->class = SchoolClass::factory()->create();
        $this->subject = Subject::factory()->create();

        // Связываем учителя с предметом и классом
        $this->teacher->subjects()->attach($this->subject->id, ['school_class_id' => $this->class->id]);
    }

    /** @test */
    public function it_calculates_class_average_grade_correctly()
    {
        // Arrange
        $students = User::factory()->count(5)->create(['role' => 'student']);

        // Привязываем студентов к классу
        foreach ($students as $student) {
            $student->schoolClasses()->attach($this->class->id);
        }

        // Создаем оценки разного уровня
        Grade::factory()->create([
            'user_id' => $students[0]->id,
            'subject_id' => $this->subject->id,
            'grade' => 5,
            'grade_type_id' => 1
        ]);

        Grade::factory()->create([
            'user_id' => $students[1]->id,
            'subject_id' => $this->subject->id,
            'grade' => 4,
            'grade_type_id' => 1
        ]);

        Grade::factory()->create([
            'user_id' => $students[2]->id,
            'subject_id' => $this->subject->id,
            'grade' => 3,
            'grade_type_id' => 1
        ]);

        Grade::factory()->create([
            'user_id' => $students[3]->id,
            'subject_id' => $this->subject->id,
            'grade' => 5,
            'grade_type_id' => 1
        ]);

        Grade::factory()->create([
            'user_id' => $students[4]->id,
            'subject_id' => $this->subject->id,
            'grade' => 4,
            'grade_type_id' => 1
        ]);

        // Act
        $average = $this->statisticsService->calculateClassAverageGrade(
            $this->class->id,
            $this->subject->id
        );

        // Assert
        $this->assertEquals(4.2, round($average, 1));
    }

    /** @test */
    public function it_calculates_attendance_statistics_correctly()
    {
        // Arrange
        $students = User::factory()->count(3)->create(['role' => 'student']);

        foreach ($students as $student) {
            $student->schoolClasses()->attach($this->class->id);
        }

        $today = Carbon::now();

        // Создаем записи посещаемости за неделю
        for ($i = 0; $i < 5; $i++) {
            $date = $today->copy()->subDays($i);

            foreach ($students as $index => $student) {
                // Первые 2 ученика присутствуют всегда, третий пропускает 2 дня
                $status = ($index < 2 || $i >= 2) ? 'present' : 'absent';

                Attendance::factory()->create([
                    'user_id' => $student->id,
                    'school_class_id' => $this->class->id,
                    'subject_id' => $this->subject->id,
                    'date' => $date->toDateString(),
                    'status' => $status
                ]);
            }
        }

        // Act
        $stats = $this->statisticsService->calculateAttendanceStats(
            $this->class->id,
            $this->subject->id
        );

        // Assert
        $this->assertEquals(80.0, $stats['attendance_percentage']); // 12/15 = 80%
        $this->assertEquals(3, $stats['total_lessons']);
        $this->assertEquals(3, $stats['students_count']);
        $this->assertEquals(12, $stats['present_count']);
        $this->assertEquals(3, $stats['absent_count']);
    }

    /** @test */
    public function it_calculates_homework_completion_rate_correctly()
    {
        // Arrange
        $students = User::factory()->count(4)->create(['role' => 'student']);

        foreach ($students as $student) {
            $student->schoolClasses()->attach($this->class->id);
        }

        $homework = Homework::factory()->create([
            'subject_id' => $this->subject->id,
            'school_class_id' => $this->class->id,
            'max_points' => 100
        ]);

        // Первые 3 ученика сдали задание
        for ($i = 0; $i < 3; $i++) {
            HomeworkSubmission::factory()->create([
                'homework_id' => $homework->id,
                'user_id' => $students[$i]->id,
                'status' => 'submitted',
                'points_received' => rand(70, 100)
            ]);
        }

        // Четвертый ученик не сдал
        // HomeworkSubmission::factory()->create([...]) - не создаем

        // Act
        $stats = $this->statisticsService->calculateHomeworkStats($homework->id);

        // Assert
        $this->assertEquals(75.0, $stats['completion_rate']); // 3/4 = 75%
        $this->assertEquals(4, $stats['total_students']);
        $this->assertEquals(3, $stats['submitted_count']);
        $this->assertEquals(1, $stats['pending_count']);
        $this->assertEquals(0, $stats['late_count']);
    }

    /** @test */
    public function it_generates_performance_trend_correctly()
    {
        // Arrange
        $student = User::factory()->create(['role' => 'student']);
        $student->schoolClasses()->attach($this->class->id);

        // Создаем оценки за несколько недель с улучшающейся динамикой
        $dates = [
            Carbon::now()->subWeeks(3),
            Carbon::now()->subWeeks(2),
            Carbon::now()->subWeeks(1),
            Carbon::now()
        ];

        $grades = [2, 3, 4, 5]; // Улучшающаяся динамика

        foreach ($dates as $index => $date) {
            Grade::factory()->create([
                'user_id' => $student->id,
                'subject_id' => $this->subject->id,
                'grade' => $grades[$index],
                'grade_type_id' => 1,
                'created_at' => $date
            ]);
        }

        // Act
        $trend = $this->statisticsService->generatePerformanceTrend(
            $student->id,
            $this->subject->id,
            4 // 4 недели
        );

        // Assert
        $this->assertCount(4, $trend);
        $this->assertEquals(2, $trend[0]['grade']);
        $this->assertEquals(3, $trend[1]['grade']);
        $this->assertEquals(4, $trend[2]['grade']);
        $this->assertEquals(5, $trend[3]['grade']);

        // Проверяем, что последняя оценка лучше первой
        $this->assertGreaterThan($trend[0]['grade'], $trend[3]['grade']);
    }

    /** @test */
    public function it_identifies_students_needing_attention()
    {
        // Arrange
        $students = User::factory()->count(4)->create(['role' => 'student']);

        foreach ($students as $student) {
            $student->schoolClasses()->attach($this->class->id);
        }

        // Первый ученик - хорошие оценки
        Grade::factory()->count(5)->create([
            'user_id' => $students[0]->id,
            'subject_id' => $this->subject->id,
            'grade' => 5
        ]);

        // Второй ученик - низкие оценки
        Grade::factory()->count(3)->create([
            'user_id' => $students[1]->id,
            'subject_id' => $this->subject->id,
            'grade' => 2
        ]);

        // Третий ученик - много пропусков
        $today = Carbon::now();
        for ($i = 0; $i < 4; $i++) {
            Attendance::factory()->create([
                'user_id' => $students[2]->id,
                'school_class_id' => $this->class->id,
                'subject_id' => $this->subject->id,
                'date' => $today->copy()->subDays($i)->toDateString(),
                'status' => 'absent'
            ]);
        }

        // Четвертый ученик - нормальные показатели
        Grade::factory()->create([
            'user_id' => $students[3]->id,
            'subject_id' => $this->subject->id,
            'grade' => 4
        ]);

        // Act
        $studentsNeedingAttention = $this->statisticsService->identifyStudentsNeedingAttention(
            $this->class->id,
            $this->subject->id
        );

        // Assert
        $this->assertCount(2, $studentsNeedingAttention);

        $studentIds = $studentsNeedingAttention->pluck('id')->toArray();
        $this->assertContains($students[1]->id, $studentIds); // Низкие оценки
        $this->assertContains($students[2]->id, $studentIds); // Много пропусков
        $this->assertNotContains($students[0]->id, $studentIds); // Хорошие оценки
        $this->assertNotContains($students[3]->id, $studentIds); // Нормальные показатели
    }

    /** @test */
    public function it_calculates_teacher_workload_statistics()
    {
        // Arrange
        $classes = SchoolClass::factory()->count(3)->create();
        $subjects = Subject::factory()->count(2)->create();

        // Связываем учителя с классами и предметами
        foreach ($classes as $class) {
            foreach ($subjects as $subject) {
                $this->teacher->subjects()->attach($subject->id, ['school_class_id' => $class->id]);
            }
        }

        $students = User::factory()->count(10)->create(['role' => 'student']);

        // Привязываем студентов к классам
        for ($i = 0; $i < 10; $i++) {
            $students[$i]->schoolClasses()->attach($classes[$i % 3]->id);
        }

        // Создаем оценки
        Grade::factory()->count(50)->create([
            'subject_id' => $subjects[0]->id,
            'user_id' => $students->random()->id
        ]);

        // Создаем посещаемость
        for ($i = 0; $i < 20; $i++) {
            Attendance::factory()->create([
                'user_id' => $students->random()->id,
                'school_class_id' => $classes->random()->id,
                'subject_id' => $subjects->random()->id
            ]);
        }

        // Создаем домашние задания
        Homework::factory()->count(15)->create([
            'subject_id' => $subjects->random()->id,
            'school_class_id' => $classes->random()->id
        ]);

        // Act
        $workloadStats = $this->statisticsService->calculateTeacherWorkload($this->teacher->id);

        // Assert
        $this->assertEquals(3, $workloadStats['total_classes']);
        $this->assertEquals(2, $workloadStats['total_subjects']);
        $this->assertEquals(10, $workloadStats['total_students']);
        $this->assertEquals(50, $workloadStats['grades_given']);
        $this->assertEquals(20, $workloadStats['attendance_records']);
        $this->assertEquals(15, $workloadStats['homework_assigned']);

        // Проверяем, что нагрузка рассчитана корректно
        $this->assertGreaterThan(0, $workloadStats['weekly_hours']);
        $this->assertGreaterThan(0, $workloadStats['workload_score']);
    }

    /** @test */
    public function it_handles_empty_data_gracefully()
    {
        // Arrange - нет данных

        // Act
        $averageGrade = $this->statisticsService->calculateClassAverageGrade(
            $this->class->id,
            $this->subject->id
        );

        $attendanceStats = $this->statisticsService->calculateAttendanceStats(
            $this->class->id,
            $this->subject->id
        );

        $trend = $this->statisticsService->generatePerformanceTrend(
            999, // несуществующий студент
            $this->subject->id
        );

        // Assert
        $this->assertEquals(0, $averageGrade);
        $this->assertEquals(0, $attendanceStats['attendance_percentage']);
        $this->assertEquals(0, $attendanceStats['total_lessons']);
        $this->assertEmpty($trend);
    }

    /** @test */
    public function it_filters_data_by_date_range()
    {
        // Arrange
        $student = User::factory()->create(['role' => 'student']);
        $student->schoolClasses()->attach($this->class->id);

        $startDate = Carbon::now()->subWeeks(2);
        $endDate = Carbon::now();

        // Создаем оценки до и после указанного периода
        Grade::factory()->create([
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'grade' => 3,
            'created_at' => $startDate->copy()->subWeek() // До периода
        ]);

        Grade::factory()->create([
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'grade' => 5,
            'created_at' => $startDate->copy()->addDays(2) // В периоде
        ]);

        Grade::factory()->create([
            'user_id' => $student->id,
            'subject_id' => $this->subject->id,
            'grade' => 4,
            'created_at' => $endDate->copy()->subDays(1) // В периоде
        ]);

        // Act
        $average = $this->statisticsService->calculateClassAverageGrade(
            $this->class->id,
            $this->subject->id,
            $startDate,
            $endDate
        );

        // Assert
        // Среднее должно быть только по оценкам в периоде: (5+4)/2 = 4.5
        $this->assertEquals(4.5, $average);
    }
}
