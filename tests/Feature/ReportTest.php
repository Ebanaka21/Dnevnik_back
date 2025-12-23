<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Subject;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function teacher_can_generate_student_grades_report()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create();
        Grade::factory()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'grade_value' => 5
        ]);

        $response = $this->actingAs($teacher, 'api')->getJson("/api/reports/student/{$student->id}/grades");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'student_id',
                'student_name',
                'grades',
                'average_grade'
            ]);
    }

    /** @test */
    public function teacher_can_generate_student_attendance_report()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create();
        Attendance::factory()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'present'
        ]);

        $response = $this->actingAs($teacher, 'api')->getJson("/api/reports/student/{$student->id}/attendance");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'student_id',
                'student_name',
                'attendance_records',
                'total_present',
                'total_absent',
                'attendance_percentage'
            ]);
    }

    /** @test */
    public function teacher_can_generate_class_summary_report()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = SchoolClass::factory()->create();
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create();

        Grade::factory()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'grade_value' => 4
        ]);

        $response = $this->actingAs($teacher, 'api')->getJson("/api/reports/class/{$class->id}/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'school_class_id',
                'class_name',
                'students_count',
                'average_grade',
                'subjects'
            ]);
    }

    /** @test */
    public function teacher_can_generate_performance_report()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create();

        Grade::factory()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'grade_value' => 3
        ]);

        $response = $this->actingAs($teacher, 'api')->postJson('/api/reports/performance', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'student_id',
                'student_name',
                'subject_id',
                'subject_name',
                'grades',
                'average_grade',
                'trend'
            ]);
    }

    /** @test */
    public function teacher_can_export_report_to_pdf()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create();

        Grade::factory()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'grade_value' => 5
        ]);

        $response = $this->actingAs($teacher, 'api')->postJson('/api/reports/export/pdf', [
            'report_type' => 'student_grades',
            'student_id' => $student->id,
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31'
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function teacher_can_export_report_to_excel()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create();

        Grade::factory()->create([
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'grade_value' => 4
        ]);

        $response = $this->actingAs($teacher, 'api')->postJson('/api/reports/export/excel', [
            'report_type' => 'student_grades',
            'student_id' => $student->id,
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31'
        ]);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
