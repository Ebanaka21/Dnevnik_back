<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Schedule;
use App\Models\PerformanceReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PerformanceReportExport;
use App\Exports\AttendanceReportExport;
use Carbon\Carbon;

class ReportController extends Controller
{
    // Отчет по оценкам ученика
    public function studentGradesReport($studentId, Request $request)
    {
        try {
            $student = User::findOrFail($studentId);

            if ($student->role->name !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Параметры отчета
            $dateFrom = $request->get('date_from', Carbon::now()->startOfYear()->toDateString());
            $dateTo = $request->get('date_to', Carbon::now()->endOfYear()->toDateString());
            $subjectId = $request->get('subject_id');
            $includeHomework = $request->get('include_homework', true);

            // Получаем оценки
            $gradesQuery = Grade::with(['subject:id,name', 'teacher:id,name,email', 'gradeType:id,name'])
                ->where('student_id', $studentId)
                ->whereBetween('date', [$dateFrom, $dateTo]);

            if ($subjectId) {
                $gradesQuery->where('subject_id', $subjectId);
            }

            $grades = $gradesQuery->orderBy('date', 'desc')->get();

            // Статистика по оценкам
            $gradeStats = [
                'total_grades' => $grades->count(),
                'average_grade' => round($grades->avg('grade_value'), 2),
                'grade_distribution' => $grades->groupBy('grade_value')->map->count(),
                'by_subject' => [],
                'by_grade_type' => [],
                'monthly_progress' => []
            ];

            // Статистика по предметам
            foreach ($grades->groupBy('subject_id') as $subjectId => $subjectGrades) {
                $subject = $subjectGrades->first()->subject;
                $gradeStats['by_subject'][] = [
                    'subject' => $subject->name,
                    'total_grades' => $subjectGrades->count(),
                    'average_grade' => round($subjectGrades->avg('grade_value'), 2),
                    'grades' => $subjectGrades->groupBy('grade_value')->map->count()
                ];
            }

            // Статистика по типам оценок
            foreach ($grades->groupBy('grade_type_id') as $typeId => $typeGrades) {
                $gradeType = $typeGrades->first()->gradeType;
                $gradeStats['by_grade_type'][] = [
                    'type' => $gradeType->name,
                    'total_grades' => $typeGrades->count(),
                    'average_grade' => round($typeGrades->avg('grade_value'), 2)
                ];
            }

            // Месячный прогресс
            $monthlyData = $grades->groupBy(function($grade) {
                return Carbon::parse($grade->date)->format('Y-m');
            })->map(function($monthGrades) {
                return round($monthGrades->avg('grade_value'), 2);
            });

            foreach ($monthlyData as $month => $avg) {
                $gradeStats['monthly_progress'][] = [
                    'month' => $month,
                    'average_grade' => $avg
                ];
            }

            // Домашние задания (если включено)
            $homeworkData = null;
            if ($includeHomework) {
                $studentClasses = $student->studentClasses()->pluck('school_class_id');

                $homeworkQuery = Homework::whereIn('school_class_id', $studentClasses)
                    ->whereBetween('created_at', [$dateFrom, $dateTo]);

                if ($subjectId) {
                    $homeworkQuery->where('subject_id', $subjectId);
                }

                $homeworks = $homeworkQuery->get();

                $submissions = HomeworkSubmission::where('student_id', $studentId)
                    ->whereIn('homework_id', $homeworks->pluck('id'))
                    ->get()
                    ->keyBy('homework_id');

                $homeworkStats = [
                    'total_homeworks' => $homeworks->count(),
                    'submitted' => $submissions->count(),
                    'pending' => $homeworks->count() - $submissions->count(),
                    'completion_rate' => $homeworks->count() > 0
                        ? round(($submissions->count() / $homeworks->count()) * 100, 2)
                        : 0,
                    'average_score' => $submissions->whereNotNull('earned_points')->avg('earned_points')
                ];

                $homeworkData = [
                    'statistics' => $homeworkStats,
                    'homeworks' => $homeworks->map(function($homework) use ($submissions) {
                        return [
                            'id' => $homework->id,
                            'title' => $homework->title,
                            'subject' => $homework->subject->name,
                            'due_date' => $homework->due_date,
                            'max_points' => $homework->max_points,
                            'is_submitted' => $submissions->has($homework->id),
                            'submission' => $submissions->get($homework),
                            'is_overdue' => !$submissions->has($homework->id) && $homework->due_date < now()->toDateString()
                        ];
                    })
                ];
            }

            Log::info('Student grades report generated', [
                'student_id' => $studentId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_grades' => $grades->count()
            ]);

            return response()->json([
                'student' => $student->only(['id', 'name', 'email']),
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'grades' => $grades,
                'statistics' => $gradeStats,
                'homework' => $homeworkData
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ReportController::studentGradesReport", [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при генерации отчета по оценкам'], 500);
        }
    }

    // Отчет по посещаемости ученика
    public function studentAttendanceReport($studentId, Request $request)
    {
        try {
            $student = User::findOrFail($studentId);

            if ($student->role->name !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Параметры отчета
            $dateFrom = $request->get('date_from', Carbon::now()->startOfYear()->toDateString());
            $dateTo = $request->get('date_to', Carbon::now()->endOfYear()->toDateString());
            $subjectId = $request->get('subject_id');

            // Получаем посещаемость
            $attendanceQuery = Attendance::with(['lesson.subject:id,name', 'teacher:id,name,email'])
                ->where('student_id', $studentId)
                ->whereBetween('date', [$dateFrom, $dateTo]);

            if ($subjectId) {
                $attendanceQuery->where('subject_id', $subjectId);
            }

            $attendance = $attendanceQuery->orderBy('date', 'desc')->get();

            // Статистика посещаемости
            $stats = [
                'total_lessons' => $attendance->count(),
                'present' => $attendance->where('status', 'present')->count(),
                'absent' => $attendance->where('status', 'absent')->count(),
                'late' => $attendance->where('status', 'late')->count(),
                'excused' => $attendance->where('status', 'excused')->count(),
                'attendance_percentage' => $attendance->count() > 0
                    ? round(($attendance->where('status', 'present')->count() / $attendance->count()) * 100, 2)
                    : 0,
                'by_subject' => [],
                'by_month' => [],
                'absence_reasons' => []
            ];

            // Статистика по предметам
            foreach ($attendance->groupBy('lesson.subject_id') as $subjectId => $subjectAttendance) {
                $subject = $subjectAttendance->first()->lesson->subject;
                $total = $subjectAttendance->count();
                $present = $subjectAttendance->where('status', 'present')->count();

                $stats['by_subject'][] = [
                    'subject' => $subject->name,
                    'total_lessons' => $total,
                    'present' => $present,
                    'absent' => $subjectAttendance->where('status', 'absent')->count(),
                    'late' => $subjectAttendance->where('status', 'late')->count(),
                    'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                ];
            }

            // Месячная статистика
            $monthlyData = $attendance->groupBy(function($record) {
                return Carbon::parse($record->date)->format('Y-m');
            })->map(function($monthRecords) {
                $total = $monthRecords->count();
                $present = $monthRecords->where('status', 'present')->count();
                return [
                    'total_lessons' => $total,
                    'present' => $present,
                    'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                ];
            });

            foreach ($monthlyData as $month => $data) {
                $stats['by_month'][] = array_merge(['month' => $month], $data);
            }

            // Причины отсутствий
            $absenceReasons = $attendance->where('status', 'absent')->pluck('reason')->filter()->countBy();
            foreach ($absenceReasons as $reason => $count) {
                $stats['absence_reasons'][] = [
                    'reason' => $reason,
                    'count' => $count
                ];
            }

            Log::info('Student attendance report generated', [
                'student_id' => $studentId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_lessons' => $attendance->count()
            ]);

            return response()->json([
                'student' => $student->only(['id', 'name', 'email']),
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'attendance' => $attendance,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ReportController::studentAttendanceReport", [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при генерации отчета по посещаемости'], 500);
        }
    }

    // Отчет по оценкам ребенка для родителя
    public function getChildGradesReport($childId, Request $request)
    {
        try {
            $child = User::findOrFail($childId);

            if ($child->role !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверка прав доступа: ребенок должен принадлежать родителю
            $parent = auth()->user();
            if (!$parent->children()->where('id', $childId)->exists()) {
                return response()->json(['error' => 'У вас нет доступа к этому ребенку'], 403);
            }

            // Параметры отчета
            $dateFrom = $request->get('date_from', Carbon::now()->startOfYear()->toDateString());
            $dateTo = $request->get('date_to', Carbon::now()->endOfYear()->toDateString());
            $subjectId = $request->get('subject_id');
            $includeHomework = $request->get('include_homework', true);

            // Получаем оценки
            $gradesQuery = Grade::with(['subject:id,name', 'teacher:id,name,email', 'gradeType:id,name'])
                ->where('student_id', $childId)
                ->whereBetween('date', [$dateFrom, $dateTo]);

            if ($subjectId) {
                $gradesQuery->where('subject_id', $subjectId);
            }

            $grades = $gradesQuery->orderBy('date', 'desc')->get();

            // Статистика по оценкам
            $gradeStats = [
                'total_grades' => $grades->count(),
                'average_grade' => round($grades->avg('grade_value'), 2),
                'grade_distribution' => $grades->groupBy('grade_value')->map->count(),
                'by_subject' => [],
                'by_grade_type' => [],
                'monthly_progress' => []
            ];

            // Статистика по предметам
            foreach ($grades->groupBy('subject_id') as $subjectId => $subjectGrades) {
                $subject = $subjectGrades->first()->subject;
                $gradeStats['by_subject'][] = [
                    'subject' => $subject->name,
                    'total_grades' => $subjectGrades->count(),
                    'average_grade' => round($subjectGrades->avg('grade_value'), 2),
                    'grades' => $subjectGrades->groupBy('grade_value')->map->count()
                ];
            }

            // Статистика по типам оценок
            foreach ($grades->groupBy('grade_type_id') as $typeId => $typeGrades) {
                $gradeType = $typeGrades->first()->gradeType;
                $gradeStats['by_grade_type'][] = [
                    'type' => $gradeType->name,
                    'total_grades' => $typeGrades->count(),
                    'average_grade' => round($typeGrades->avg('grade_value'), 2)
                ];
            }

            // Месячный прогресс
            $monthlyData = $grades->groupBy(function($grade) {
                return Carbon::parse($grade->date)->format('Y-m');
            })->map(function($monthGrades) {
                return round($monthGrades->avg('grade_value'), 2);
            });

            foreach ($monthlyData as $month => $avg) {
                $gradeStats['monthly_progress'][] = [
                    'month' => $month,
                    'average_grade' => $avg
                ];
            }

            // Домашние задания (если включено)
            $homeworkData = null;
            if ($includeHomework) {
                $studentClasses = $child->studentClasses()->pluck('school_class_id');

                $homeworkQuery = Homework::whereIn('school_class_id', $studentClasses)
                    ->whereBetween('created_at', [$dateFrom, $dateTo]);

                if ($subjectId) {
                    $homeworkQuery->where('subject_id', $subjectId);
                }

                $homeworks = $homeworkQuery->get();

                $submissions = HomeworkSubmission::where('student_id', $childId)
                    ->whereIn('homework_id', $homeworks->pluck('id'))
                    ->get()
                    ->keyBy('homework_id');

                $homeworkStats = [
                    'total_homeworks' => $homeworks->count(),
                    'submitted' => $submissions->count(),
                    'pending' => $homeworks->count() - $submissions->count(),
                    'completion_rate' => $homeworks->count() > 0
                        ? round(($submissions->count() / $homeworks->count()) * 100, 2)
                        : 0,
                    'average_score' => $submissions->whereNotNull('earned_points')->avg('earned_points')
                ];

                $homeworkData = [
                    'statistics' => $homeworkStats,
                    'homeworks' => $homeworks->map(function($homework) use ($submissions) {
                        return [
                            'id' => $homework->id,
                            'title' => $homework->title,
                            'subject' => $homework->subject->name,
                            'due_date' => $homework->due_date,
                            'max_points' => $homework->max_points,
                            'is_submitted' => $submissions->has($homework->id),
                            'submission' => $submissions->get($homework),
                            'is_overdue' => !$submissions->has($homework->id) && $homework->due_date < now()->toDateString()
                        ];
                    })
                ];
            }

            Log::info('Child grades report generated', [
                'parent_id' => $parent->id,
                'child_id' => $childId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_grades' => $grades->count()
            ]);

            return response()->json([
                'child' => $child->only(['id', 'name', 'email']),
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'grades' => $grades,
                'statistics' => $gradeStats,
                'homework' => $homeworkData
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ReportController::getChildGradesReport", [
                'child_id' => $childId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при генерации отчета по оценкам ребенка'], 500);
        }
    }

    // Отчет по посещаемости ребенка для родителя
    public function getChildAttendanceReport($childId, Request $request)
    {
        try {
            $child = User::findOrFail($childId);

            if ($child->role !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверка прав доступа: ребенок должен принадлежать родителю
            $parent = auth()->user();
            if (!$parent->children()->where('id', $childId)->exists()) {
                return response()->json(['error' => 'У вас нет доступа к этому ребенку'], 403);
            }

            // Параметры отчета
            $dateFrom = $request->get('date_from', Carbon::now()->startOfYear()->toDateString());
            $dateTo = $request->get('date_to', Carbon::now()->endOfYear()->toDateString());
            $subjectId = $request->get('subject_id');

            // Получаем посещаемость
            $attendanceQuery = Attendance::with(['lesson.subject:id,name', 'teacher:id,name,email'])
                ->where('student_id', $childId)
                ->whereBetween('date', [$dateFrom, $dateTo]);

            if ($subjectId) {
                $attendanceQuery->where('subject_id', $subjectId);
            }

            $attendance = $attendanceQuery->orderBy('date', 'desc')->get();

            // Статистика посещаемости
            $stats = [
                'total_lessons' => $attendance->count(),
                'present' => $attendance->where('status', 'present')->count(),
                'absent' => $attendance->where('status', 'absent')->count(),
                'late' => $attendance->where('status', 'late')->count(),
                'excused' => $attendance->where('status', 'excused')->count(),
                'attendance_percentage' => $attendance->count() > 0
                    ? round(($attendance->where('status', 'present')->count() / $attendance->count()) * 100, 2)
                    : 0,
                'by_subject' => [],
                'by_month' => [],
                'absence_reasons' => []
            ];

            // Статистика по предметам
            foreach ($attendance->groupBy('lesson.subject_id') as $subjectId => $subjectAttendance) {
                $subject = $subjectAttendance->first()->lesson->subject;
                $total = $subjectAttendance->count();
                $present = $subjectAttendance->where('status', 'present')->count();

                $stats['by_subject'][] = [
                    'subject' => $subject->name,
                    'total_lessons' => $total,
                    'present' => $present,
                    'absent' => $subjectAttendance->where('status', 'absent')->count(),
                    'late' => $subjectAttendance->where('status', 'late')->count(),
                    'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                ];
            }

            // Месячная статистика
            $monthlyData = $attendance->groupBy(function($record) {
                return Carbon::parse($record->date)->format('Y-m');
            })->map(function($monthRecords) {
                $total = $monthRecords->count();
                $present = $monthRecords->where('status', 'present')->count();
                return [
                    'total_lessons' => $total,
                    'present' => $present,
                    'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                ];
            });

            foreach ($monthlyData as $month => $data) {
                $stats['by_month'][] = array_merge(['month' => $month], $data);
            }

            // Причины отсутствий
            $absenceReasons = $attendance->where('status', 'absent')->pluck('reason')->filter()->countBy();
            foreach ($absenceReasons as $reason => $count) {
                $stats['absence_reasons'][] = [
                    'reason' => $reason,
                    'count' => $count
                ];
            }

            Log::info('Child attendance report generated', [
                'parent_id' => $parent->id,
                'child_id' => $childId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_lessons' => $attendance->count()
            ]);

            return response()->json([
                'child' => $child->only(['id', 'name', 'email']),
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'attendance' => $attendance,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ReportController::getChildAttendanceReport", [
                'child_id' => $childId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при генерации отчета по посещаемости ребенка'], 500);
        }
    }

    // НОВЫЕ МЕТОДЫ ДЛЯ PERFORMANCE REPORT

    /**
     * Генерация отчета по успеваемости с сохранением в PerformanceReport
     */
    public function generatePerformanceReport(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|exists:users,id',
                'school_class_id' => 'required|exists:school_classes,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after:period_start'
            ]);

            $studentId = $request->student_id;
            $classId = $request->class_id;
            $periodStart = $request->period_start;
            $periodEnd = $request->period_end;

            // Проверяем, что ученик принадлежит к классу
            $student = User::findOrFail($studentId);
            $class = SchoolClass::findOrFail($classId);

            // Получаем оценки за период
            $grades = Grade::where('student_id', $studentId)
                ->whereBetween('date', [$periodStart, $periodEnd])
                ->get();

            // Получаем посещаемость за период
            $attendance = Attendance::where('student_id', $studentId)
                ->whereBetween('date', [$periodStart, $periodEnd])
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
                'late_lessons' => $attendance->where('status', 'late')->count()
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

            // Создаем или обновляем отчет
            $performanceReport = PerformanceReport::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'school_class_id' => $classId,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd
                ],
                [
                    'total_grades' => $totalGrades,
                    'average_grade' => $averageGrade,
                    'attendance_percentage' => $attendancePercentage,
                    'report_data' => $reportData
                ]
            );

            Log::info('Performance report generated', [
                'student_id' => $studentId,
                'school_class_id' => $classId,
                'period' => $periodStart . ' - ' . $periodEnd,
                'report_id' => $performanceReport->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Отчет по успеваемости успешно сгенерирован',
                'data' => $performanceReport->load(['student:id,name,email', 'schoolClass:id,name'])
            ]);

        } catch (\Exception $e) {
            Log::error("Error in generatePerformanceReport", [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при генерации отчета по успеваемости',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Генерация отчета по посещаемости
     */
    public function generateAttendanceReport(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|exists:users,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after:period_start'
            ]);

            $studentId = $request->student_id;
            $periodStart = $request->period_start;
            $periodEnd = $request->period_end;

            $student = User::findOrFail($studentId);

            // Получаем посещаемость за период
            $attendance = Attendance::where('student_id', $studentId)
                ->whereBetween('date', [$periodStart, $periodEnd])
                ->with(['lesson.subject:id,name', 'teacher:id,name'])
                ->orderBy('date', 'desc')
                ->get();

            // Рассчитываем статистику
            $totalLessons = $attendance->count();
            $presentLessons = $attendance->where('status', 'present')->count();
            $absentLessons = $attendance->where('status', 'absent')->count();
            $lateLessons = $attendance->where('status', 'late')->count();
            $excusedLessons = $attendance->where('status', 'excused')->count();

            $attendancePercentage = $totalLessons > 0
                ? round(($presentLessons / $totalLessons) * 100, 2)
                : 0;

            // Статистика по предметам
            $subjectStats = [];
            foreach ($attendance->groupBy('lesson.subject_id') as $subjectId => $subjectAttendance) {
                $subject = $subjectAttendance->first()->lesson->subject;
                $subjectTotal = $subjectAttendance->count();
                $subjectPresent = $subjectAttendance->where('status', 'present')->count();

                $subjectStats[] = [
                    'subject' => $subject->name,
                    'total_lessons' => $subjectTotal,
                    'present' => $subjectPresent,
                    'absent' => $subjectAttendance->where('status', 'absent')->count(),
                    'late' => $subjectAttendance->where('status', 'late')->count(),
                    'attendance_percentage' => $subjectTotal > 0
                        ? round(($subjectPresent / $subjectTotal) * 100, 2)
                        : 0
                ];
            }

            // Месячная статистика
            $monthlyData = $attendance->groupBy(function($record) {
                return Carbon::parse($record->date)->format('Y-m');
            })->map(function($monthRecords) {
                $total = $monthRecords->count();
                $present = $monthRecords->where('status', 'present')->count();
                return [
                    'total_lessons' => $total,
                    'present' => $present,
                    'attendance_percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
                ];
            });

            $monthlyStats = [];
            foreach ($monthlyData as $month => $data) {
                $monthlyStats[] = array_merge(['month' => $month], $data);
            }

            $reportData = [
                'student' => $student->only(['id', 'name', 'email']),
                'period' => [
                    'start' => $periodStart,
                    'end' => $periodEnd
                ],
                'statistics' => [
                    'total_lessons' => $totalLessons,
                    'present' => $presentLessons,
                    'absent' => $absentLessons,
                    'late' => $lateLessons,
                    'excused' => $excusedLessons,
                    'attendance_percentage' => $attendancePercentage
                ],
                'subjects_stats' => $subjectStats,
                'monthly_stats' => $monthlyStats,
                'attendance_records' => $attendance,
                'generated_at' => now()->toISOString()
            ];

            Log::info('Attendance report generated', [
                'student_id' => $studentId,
                'period' => $periodStart . ' - ' . $periodEnd,
                'total_lessons' => $totalLessons
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Отчет по посещаемости успешно сгенерирован',
                'data' => $reportData
            ]);

        } catch (\Exception $e) {
            Log::error("Error in generateAttendanceReport", [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при генерации отчета по посещаемости',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Экспорт отчета в PDF
     */
    public function exportToPDF(Request $request)
    {
        try {
            $request->validate([
                'report_type' => 'required|in:performance,attendance',
                'student_id' => 'required|exists:users,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after:period_start'
            ]);

            $reportType = $request->report_type;
            $studentId = $request->student_id;
            $periodStart = $request->period_start;
            $periodEnd = $request->period_end;

            $student = User::findOrFail($studentId);

            // Генерируем данные для отчета
            if ($reportType === 'performance') {
                $reportData = $this->getPerformanceReportData($studentId, $periodStart, $periodEnd);
                $view = 'reports.performance_pdf';
            } else {
                $reportData = $this->getAttendanceReportData($studentId, $periodStart, $periodEnd);
                $view = 'reports.attendance_pdf';
            }

            // Создаем PDF
            $pdf = Pdf::loadView($view, [
                'student' => $student,
                'reportData' => $reportData,
                'periodStart' => $periodStart,
                'periodEnd' => $periodEnd,
                'generatedAt' => now()
            ]);

            $filename = "{$reportType}_report_{$studentId}_" . date('Y-m-d_H-i-s') . ".pdf";

            // Сохраняем во временную папку
            $tempPath = "temp/{$filename}";
            Storage::put($tempPath, $pdf->output());

            Log::info('PDF report exported', [
                'report_type' => $reportType,
                'student_id' => $studentId,
                'period' => $periodStart . ' - ' . $periodEnd,
                'filename' => $filename
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PDF отчет успешно сгенерирован',
                'filename' => $filename,
                'download_url' => route('reports.download', ['filename' => $filename])
            ]);

        } catch (\Exception $e) {
            Log::error("Error in exportToPDF", [
                'report_type' => $request->report_type,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при экспорте PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Экспорт отчета в Excel
     */
    public function exportToExcel(Request $request)
    {
        try {
            $request->validate([
                'report_type' => 'required|in:performance,attendance',
                'student_id' => 'required|exists:users,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after:period_start'
            ]);

            $reportType = $request->report_type;
            $studentId = $request->student_id;
            $periodStart = $request->period_start;
            $periodEnd = $request->period_end;

            $student = User::findOrFail($studentId);

            $filename = "{$reportType}_report_{$studentId}_" . date('Y-m-d_H-i-s') . ".xlsx";

            // Создаем Excel файл
            if ($reportType === 'performance') {
                Excel::store(new PerformanceReportExport($studentId, $periodStart, $periodEnd), "temp/{$filename}");
            } else {
                Excel::store(new AttendanceReportExport($studentId, $periodStart, $periodEnd), "temp/{$filename}");
            }

            Log::info('Excel report exported', [
                'report_type' => $reportType,
                'student_id' => $studentId,
                'period' => $periodStart . ' - ' . $periodEnd,
                'filename' => $filename
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Excel отчет успешно сгенерирован',
                'filename' => $filename,
                'download_url' => route('reports.download', ['filename' => $filename])
            ]);

        } catch (\Exception $e) {
            Log::error("Error in exportToExcel", [
                'report_type' => $request->report_type,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при экспорте Excel',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Вспомогательные методы для получения данных отчетов
     */
    private function getPerformanceReportData($studentId, $periodStart, $periodEnd)
    {
        $grades = Grade::where('student_id', $studentId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->with(['subject:id,name', 'gradeType:id,name'])
            ->get();

        return [
            'total_grades' => $grades->count(),
            'average_grade' => round($grades->avg('grade_value'), 2),
            'grades' => $grades,
            'subjects_performance' => $grades->groupBy('subject_id')->map(function($subjectGrades) {
                $subject = $subjectGrades->first()->subject;
                return [
                    'subject' => $subject->name,
                    'average_grade' => round($subjectGrades->avg('grade_value'), 2),
                    'total_grades' => $subjectGrades->count()
                ];
            })->values()
        ];
    }

    private function getAttendanceReportData($studentId, $periodStart, $periodEnd)
    {
        $attendance = Attendance::where('student_id', $studentId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->with(['lesson.subject:id,name'])
            ->get();

        $totalLessons = $attendance->count();
        $presentLessons = $attendance->where('status', 'present')->count();

        return [
            'total_lessons' => $totalLessons,
            'present_lessons' => $presentLessons,
            'attendance_percentage' => $totalLessons > 0 ? round(($presentLessons / $totalLessons) * 100, 2) : 0,
            'attendance_records' => $attendance
        ];
    }

    // Существующие методы...

    // Сводный отчет по классу
    public function classSummaryReport($classId, Request $request)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            // Параметры отчета
            $dateFrom = $request->get('date_from', Carbon::now()->startOfYear()->toDateString());
            $dateTo = $request->get('date_to', Carbon::now()->endOfYear()->toDateString());
            $subjectId = $request->get('subject_id');

            // Получаем учеников класса
            $students = $class->students()->with('role')->get();

            // Общая статистика класса
            $classStats = [
                'total_students' => $students->count(),
                'class_info' => [
                    'name' => $class->name,
                    'academic_year' => $class->academic_year,
                    'class_teacher' => $class->classTeacher ? $class->classTeacher->name : null
                ],
                'subjects' => [],
                'students_performance' => [],
                'overall_statistics' => []
            ];

            // Получаем уникальные предметы класса
            $subjects = $class->schedules()
                ->with('subject:id,name')
                ->distinct()
                ->get('subject_id')
                ->pluck('subject');

            foreach ($subjects as $subject) {
                if ($subjectId && $subject->id != $subjectId) continue;

                // Оценки по предмету
                $gradesQuery = Grade::whereHas('student.studentClasses', function($q) use ($classId) {
                        $q->where('school_class_id', $classId);
                    })
                    ->where('subject_id', $subject->id)
                    ->whereBetween('date', [$dateFrom, $dateTo]);

                $grades = $gradesQuery->with(['student:id,name'])->get();

                // Посещаемость по предмету
                $attendanceQuery = Attendance::whereHas('student.studentClasses', function($q) use ($classId) {
                        $q->where('school_class_id', $classId);
                    })
                    ->where('subject_id', $subject->id)
                    ->whereBetween('date', [$dateFrom, $dateTo]);

                $attendance = $attendanceQuery->get();

                $subjectStats = [
                    'subject' => $subject->name,
                    'total_grades' => $grades->count(),
                    'average_grade' => round($grades->avg('grade_value'), 2),
                    'grade_distribution' => $grades->groupBy('grade_value')->map->count(),
                    'total_lessons' => $attendance->count(),
                    'attendance_percentage' => $attendance->count() > 0
                        ? round(($attendance->where('status', 'present')->count() / $attendance->count()) * 100, 2)
                        : 0,
                    'top_students' => [],
                    'students_needing_attention' => []
                ];

                // Топ ученики по предмету
                $studentGrades = $grades->groupBy('student_id')->map(function($studentGrades) {
                    return round($studentGrades->avg('grade_value'), 2);
                })->sortDesc()->take(3);

                foreach ($studentGrades as $studentId => $avgGrade) {
                    $student = $students->find($studentId);
                    if ($student) {
                        $subjectStats['top_students'][] = [
                            'student' => $student->name,
                            'average_grade' => $avgGrade
                        ];
                    }
                }

                // Ученики, требующие внимания
                $strugglingStudents = $studentGrades->filter(function($avgGrade) {
                    return $avgGrade < 3.5;
                });

                foreach ($strugglingStudents as $studentId => $avgGrade) {
                    $student = $students->find($studentId);
                    if ($student) {
                        $subjectStats['students_needing_attention'][] = [
                            'student' => $student->name,
                            'average_grade' => $avgGrade
                        ];
                    }
                }

                $classStats['subjects'][] = $subjectStats;
            }

            // Статистика по каждому ученику
            foreach ($students as $student) {
                $studentGrades = Grade::where('student_id', $student->id)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->when($subjectId, function($q) use ($subjectId) {
                        $q->where('subject_id', $subjectId);
                    })
                    ->get();

                $studentAttendance = Attendance::where('student_id', $student->id)
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->when($subjectId, function($q) use ($subjectId) {
                        $q->where('subject_id', $subjectId);
                    })
                    ->get();

                $studentStats = [
                    'student' => $student->only(['id', 'name', 'email']),
                    'average_grade' => round($studentGrades->avg('grade_value'), 2),
                    'total_grades' => $studentGrades->count(),
                    'attendance_percentage' => $studentAttendance->count() > 0
                        ? round(($studentAttendance->where('status', 'present')->count() / $studentAttendance->count()) * 100, 2)
                        : 0,
                    'total_absences' => $studentAttendance->where('status', 'absent')->count(),
                    'performance_level' => $this->getPerformanceLevel($studentGrades->avg('grade_value'))
                ];

                $classStats['students_performance'][] = $studentStats;
            }

            // Общая статистика класса
            $allGrades = Grade::whereHas('student.studentClasses', function($q) use ($classId) {
                    $q->where('school_class_id', $classId);
                })
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->when($subjectId, function($q) use ($subjectId) {
                    $q->where('subject_id', $subjectId);
                })
                ->get();

            $allAttendance = Attendance::whereHas('student.studentClasses', function($q) use ($classId) {
                    $q->where('school_class_id', $classId);
                })
                ->whereBetween('date', [$dateFrom, $dateTo])
                ->when($subjectId, function($q) use ($subjectId) {
                    $q->where('subject_id', $subjectId);
                })
                ->get();

            $classStats['overall_statistics'] = [
                'class_average_grade' => round($allGrades->avg('grade_value'), 2),
                'class_attendance_percentage' => $allAttendance->count() > 0
                    ? round(($allAttendance->where('status', 'present')->count() / $allAttendance->count()) * 100, 2)
                    : 0,
                'total_grades_recorded' => $allGrades->count(),
                'total_lessons' => $allAttendance->count()
            ];

            Log::info('Class summary report generated', [
                'school_class_id' => $classId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'total_students' => $students->count()
            ]);

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'report' => $classStats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in ReportController::classSummaryReport", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при генерации сводного отчета по классу'], 500);
        }
    }

    // Определить уровень успеваемости ученика
    private function getPerformanceLevel($averageGrade)
    {
        if ($averageGrade >= 4.5) return 'Отлично';
        if ($averageGrade >= 4.0) return 'Хорошо';
        if ($averageGrade >= 3.0) return 'Удовлетворительно';
        if ($averageGrade >= 2.0) return 'Неудовлетворительно';
        return 'Критический уровень';
    }

}
