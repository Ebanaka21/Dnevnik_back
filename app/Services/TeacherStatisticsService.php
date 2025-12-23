<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TeacherStatisticsService
{
    /**
     * Вычисляет среднюю оценку класса по предмету
     */
    public function calculateClassAverageGrade(int $classId, int $subjectId, ?Carbon $startDate = null, ?Carbon $endDate = null): float
    {
        $query = Grade::where('school_class_id', $classId)
            ->where('subject_id', $subjectId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $average = $query->avg('grade');

        return round($average ?? 0, 2);
    }

    /**
     * Вычисляет статистику посещаемости
     */
    public function calculateAttendanceStats(int $classId, int $subjectId): array
    {
        $attendanceRecords = Attendance::where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->get();

        $totalLessons = $attendanceRecords->groupBy('date')->count();
        $studentsCount = $attendanceRecords->groupBy('user_id')->count();
        $presentCount = $attendanceRecords->where('status', 'present')->count();
        $absentCount = $attendanceRecords->where('status', 'absent')->count();
        $lateCount = $attendanceRecords->where('status', 'late')->count();

        $totalRecords = $attendanceRecords->count();
        $attendancePercentage = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0;

        return [
            'attendance_percentage' => $attendancePercentage,
            'total_lessons' => $totalLessons,
            'students_count' => $studentsCount,
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'late_count' => $lateCount
        ];
    }

    /**
     * Вычисляет статистику по домашнему заданию
     */
    public function calculateHomeworkStats(int $homeworkId): array
    {
        $homework = Homework::findOrFail($homeworkId);
        $submissions = HomeworkSubmission::where('homework_id', $homeworkId)->get();

        $totalStudents = $homework->schoolClass->students->count();
        $submittedCount = $submissions->where('status', 'submitted')->count();
        $pendingCount = $totalStudents - $submittedCount;
        $lateCount = $submissions->where('status', 'late')->count();

        $completionRate = $totalStudents > 0 ? round(($submittedCount / $totalStudents) * 100, 1) : 0;

        return [
            'completion_rate' => $completionRate,
            'total_students' => $totalStudents,
            'submitted_count' => $submittedCount,
            'pending_count' => $pendingCount,
            'late_count' => $lateCount
        ];
    }

    /**
     * Генерирует тренд успеваемости ученика
     */
    public function generatePerformanceTrend(int $studentId, int $subjectId, int $weeks = 4): array
    {
        $startDate = Carbon::now()->subWeeks($weeks);

        $grades = Grade::where('user_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at')
            ->get()
            ->groupBy(function ($grade) {
                return $grade->created_at->startOfWeek()->format('Y-m-d');
            });

        $trend = [];
        foreach ($grades as $weekStart => $weekGrades) {
            $trend[] = [
                'week' => $weekStart,
                'grade' => round($weekGrades->avg('grade'), 1),
                'count' => $weekGrades->count()
            ];
        }

        return $trend;
    }

    /**
     * Определяет учеников, нуждающихся в внимании
     */
    public function identifyStudentsNeedingAttention(int $classId, int $subjectId): \Illuminate\Database\Eloquent\Collection
    {
        $students = User::whereHas('schoolClasses', function ($query) use ($classId) {
            $query->where('school_class_id', $classId);
        })->where('role', 'student')->get();

        $studentsNeedingAttention = collect();

        foreach ($students as $student) {
            $averageGrade = $this->calculateStudentAverageGrade($student->id, $subjectId);
            $attendancePercentage = $this->calculateStudentAttendancePercentage($student->id, $classId, $subjectId);

            // Критерии для внимания: низкие оценки (< 3.5) или низкая посещаемость (< 70%)
            if ($averageGrade < 3.5 || $attendancePercentage < 70) {
                $studentsNeedingAttention->push([
                    'id' => $student->id,
                    'name' => $student->name,
                    'average_grade' => $averageGrade,
                    'attendance_percentage' => $attendancePercentage,
                    'reasons' => [
                        $averageGrade < 3.5 ? 'Низкие оценки' : null,
                        $attendancePercentage < 70 ? 'Низкая посещаемость' : null
                    ]
                ]);
            }
        }

        return $studentsNeedingAttention->values();
    }

    /**
     * Вычисляет статистику нагрузки учителя
     */
    public function calculateTeacherWorkload(int $teacherId): array
    {
        $teacher = User::findOrFail($teacherId);

        $totalClasses = $teacher->subjects()->distinct('school_class_id')->count('school_class_id');
        $totalSubjects = $teacher->subjects()->distinct('subject_id')->count('subject_id');

        $totalStudents = DB::table('teacher_student_relations')
            ->where('teacher_id', $teacherId)
            ->distinct('student_id')
            ->count('student_id');

        $gradesGiven = Grade::whereHas('subject', function ($query) use ($teacherId) {
            $query->whereHas('teachers', function ($q) use ($teacherId) {
                $q->where('user_id', $teacherId);
            });
        })->count();

        $attendanceRecords = Attendance::whereHas('subject', function ($query) use ($teacherId) {
            $query->whereHas('teachers', function ($q) use ($teacherId) {
                $q->where('user_id', $teacherId);
            });
        })->count();

        $homeworkAssigned = Homework::whereHas('subject', function ($query) use ($teacherId) {
            $query->whereHas('teachers', function ($q) use ($teacherId) {
                $q->where('user_id', $teacherId);
            });
        })->count();

        // Примерная оценка часов в неделю (1 час на класс в неделю)
        $weeklyHours = $totalClasses;

        // Оценка общей нагрузки (максимум 100)
        $workloadScore = min(100, ($weeklyHours * 10) + ($gradesGiven * 0.1) + ($attendanceRecords * 0.05));

        return [
            'total_classes' => $totalClasses,
            'total_subjects' => $totalSubjects,
            'total_students' => $totalStudents,
            'grades_given' => $gradesGiven,
            'attendance_records' => $attendanceRecords,
            'homework_assigned' => $homeworkAssigned,
            'weekly_hours' => $weeklyHours,
            'workload_score' => round($workloadScore, 1)
        ];
    }

    /**
     * Вычисляет среднюю оценку ученика по предмету
     */
    private function calculateStudentAverageGrade(int $studentId, int $subjectId): float
    {
        $average = Grade::where('user_id', $studentId)
            ->where('subject_id', $subjectId)
            ->avg('grade');

        return round($average ?? 0, 1);
    }

    /**
     * Вычисляет процент посещаемости ученика
     */
    private function calculateStudentAttendancePercentage(int $studentId, int $classId, int $subjectId): float
    {
        $attendanceRecords = Attendance::where('user_id', $studentId)
            ->where('school_class_id', $classId)
            ->where('subject_id', $subjectId)
            ->get();

        $totalRecords = $attendanceRecords->count();
        if ($totalRecords === 0) return 0;

        $presentCount = $attendanceRecords->where('status', 'present')->count();

        return round(($presentCount / $totalRecords) * 100, 1);
    }
}
