<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PerformanceReport;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Grade;
use App\Models\Attendance;
use Carbon\Carbon;

class PerformanceReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Получаем учеников и классы
        $students = User::where('role', 'student')->get();

        $classes = SchoolClass::all();

        if ($students->isEmpty() || $classes->isEmpty()) {
            $this->command->info('Не найдено учеников или классов для создания отчетов');
            return;
        }

        foreach ($students as $student) {
            // Получаем классы ученика
            $studentClasses = $student->studentClasses()->pluck('school_class_id')->toArray();

            if (empty($studentClasses)) {
                continue; // Пропускаем ученика без классов
            }

            foreach ($studentClasses as $classId) {
                $class = $classes->find($classId);
                if (!$class) continue;

                // Создаем отчеты за разные периоды
                $periods = [
                    [
                        'start' => Carbon::now()->startOfMonth()->subMonths(2),
                        'end' => Carbon::now()->endOfMonth()->subMonths(2)
                    ],
                    [
                        'start' => Carbon::now()->startOfMonth()->subMonth(),
                        'end' => Carbon::now()->endOfMonth()->subMonth()
                    ],
                    [
                        'start' => Carbon::now()->startOfMonth(),
                        'end' => Carbon::now()->endOfMonth()
                    ]
                ];

                foreach ($periods as $period) {
                    $this->createPerformanceReport($student, $class, $period['start'], $period['end']);
                }
            }
        }

        $this->command->info('Создано ' . PerformanceReport::count() . ' отчетов по успеваемости');
    }

    /**
     * Создание отчета по успеваемости для ученика
     */
    private function createPerformanceReport($student, $class, $periodStart, $periodEnd)
    {
        // Получаем оценки за период
        $grades = Grade::where('student_id', $student->id)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();

        // Получаем посещаемость за период
        $attendance = Attendance::where('student_id', $student->id)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();

        // Рассчитываем статистику
        $totalGrades = $grades->count();
        $averageGrade = $totalGrades > 0 ? round($grades->avg('grade_value'), 2) : 0;

        $totalLessons = $attendance->count();
        $presentLessons = $attendance->where('status', 'present')->count();
        $attendancePercentage = $totalLessons > 0
            ? round(($presentLessons / $totalLessons) * 100, 2)
            : 0;

        // Дополнительные данные для отчета
        $reportData = [
            'grade_distribution' => $grades->groupBy('grade_value')->map->count(),
            'subjects_performance' => [],
            'monthly_progress' => [],
            'attendance_by_status' => $attendance->countBy('status'),
            'generated_at' => now()->toISOString(),
            'total_lessons' => $totalLessons,
            'absent_lessons' => $attendance->where('status', 'absent')->count(),
            'late_lessons' => $attendance->where('status', 'late')->count(),
            'excused_lessons' => $attendance->where('status', 'excused')->count()
        ];

        // Статистика по предметам
        foreach ($grades->groupBy('subject_id') as $subjectId => $subjectGrades) {
            $subject = $subjectGrades->first()->subject;
            $reportData['subjects_performance'][] = [
                'subject' => $subject->name,
                'total_grades' => $subjectGrades->count(),
                'average_grade' => round($subjectGrades->avg('grade_value'), 2),
                'grades' => $subjectGrades->groupBy('grade_value')->map->count()
            ];
        }

        // Месячный прогресс
        $monthlyData = $grades->groupBy(function($grade) {
            return Carbon::parse($grade->date)->format('Y-m');
        })->map(function($monthGrades) {
            return round($monthGrades->avg('grade_value'), 2);
        });

        foreach ($monthlyData as $month => $avg) {
            $reportData['monthly_progress'][] = [
                'month' => $month,
                'average_grade' => $avg
            ];
        }

        // Создаем отчет
        PerformanceReport::create([
            'student_id' => $student->id,
            'school_class_id' => $class->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'total_grades' => $totalGrades,
            'average_grade' => $averageGrade,
            'attendance_percentage' => $attendancePercentage,
            'report_data' => $reportData
        ]);
    }
}
