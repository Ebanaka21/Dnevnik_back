<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\StudentClass;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Создание записей посещаемости за последние недели
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание записей посещаемости...');

        $subjects = Subject::where('is_active', true)->get();
        $students = User::where('role', 'student')->with(['studentClassRelationships' => function ($query) {
            $query->where('academic_year', '2024-2025')
                  ->where('is_active', true);
        }])->get();

        $attendanceRecordsCreated = 0;

        foreach ($students as $student) {
            foreach ($student->studentClassRelationships as $studentClass) {
                $class = $studentClass->schoolClass;
                if (!$class) continue;

                foreach ($subjects as $subject) {
                    $attendanceRecords = $this->generateAttendanceRecords($student, $subject, $class);

                    foreach ($attendanceRecords as $recordData) {
                        \App\Models\Attendance::updateOrCreate(
                            [
                                'student_id' => $student->id,
                                'subject_id' => $subject->id,
                                'date' => $recordData['date'],
                            ],
                            $recordData
                        );
                        $attendanceRecordsCreated++;
                    }
                }
            }
        }

        $this->command->info("Создано записей посещаемости: {$attendanceRecordsCreated}");
        $this->command->info('Создание записей посещаемости завершено!');
    }

    private function generateAttendanceRecords($student, $subject, $class)
    {
        $records = [];
        $currentDate = Carbon::now();
        $startDate = $currentDate->copy()->subWeeks(8);
        $teacherId = $this->getSubjectTeacher($subject, $class);

        for ($date = $startDate->copy(); $date->lte($currentDate); $date->addDay()) {
            if ($date->isWeekend() || $this->isHoliday($date)) continue;

            $attendanceStatus = $this->generateAttendanceStatus($student, $date);

            $records[] = [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'teacher_id' => $teacherId ?? 1, // fallback к первому учителю
                'date' => $date->format('Y-m-d'),
                'status' => $attendanceStatus['status'],
                'reason' => $attendanceStatus['reason'],
                'arrival_time' => $attendanceStatus['arrival_time'],
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }

        return $records;
    }

    private function generateAttendanceStatus($student, $date)
    {
        $attendanceRate = $this->getStudentAttendanceRate($student);

        $absenceProbability = match($attendanceRate) {
            'excellent' => 0.02,
            'good' => 0.05,
            'average' => 0.10,
            'poor' => 0.20,
            default => 0.10
        };

        $rand = mt_rand(1, 100) / 100;

        if ($rand <= $absenceProbability) {
            $absenceTypes = ['absent', 'sick'];
            $weight = [
                'absent' => $attendanceRate === 'poor' ? 0.7 : 0.4,
                'sick' => $attendanceRate === 'poor' ? 0.3 : 0.6
            ];

            $status = $this->weightedRandomChoice($absenceTypes, $weight);

            return [
                'status' => $status,
                'reason' => $this->generateAbsenceReason($status),
                'arrival_time' => null,
            ];
        } elseif (mt_rand(1, 100) <= 5) {
            return [
                'status' => 'late',
                'reason' => null,
                'arrival_time' => $this->generateLateArrivalTime(),
            ];
        } else {
            return [
                'status' => 'present',
                'reason' => null,
                'arrival_time' => null,
            ];
        }
    }

    private function getStudentAttendanceRate($student)
    {
        $lastChar = strtolower(substr($student->surname, -1));

        if (in_array($lastChar, ['а', 'о', 'е', 'и', 'н'])) {
            return 'excellent';
        } elseif (in_array($lastChar, ['р', 'т', 'л', 'д'])) {
            return 'good';
        } elseif (in_array($lastChar, ['с', 'в', 'к', 'м'])) {
            return 'average';
        } else {
            return 'poor';
        }
    }

    private function weightedRandomChoice($items, $weights)
    {
        $totalWeight = array_sum($weights);
        $rand = mt_rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($items as $item) {
            $currentWeight += $weights[$item];
            if ($rand <= $currentWeight) {
                return $item;
            }
        }

        return $items[array_rand($items)];
    }

    private function generateAbsenceReason($status)
    {
        $reasons = [
            'absent' => [
                'Семейные обстоятельства',
                'По семейным делам',
                'Не явился на занятие',
                'Уважительная причина',
                'Прогулял занятие'
            ],
            'sick' => [
                'Болезнь',
                'ОРВИ',
                'Грипп',
                'Простуда',
                'Высокая температура',
                'Зубная боль',
                'Головная боль'
            ]
        ];

        $statusReasons = $reasons[$status] ?? $reasons['absent'];
        return $statusReasons[array_rand($statusReasons)];
    }

    private function generateLateArrivalTime()
    {
        $hour = 8;
        $minute = mt_rand(0, 15);

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    private function getSubjectTeacher($subject, $class)
    {
        $teacherId = DB::table('teacher_classes')
            ->join('users', 'teacher_classes.teacher_id', '=', 'users.id')
            ->where('teacher_classes.subject_id', $subject->id)
            ->where('teacher_classes.school_class_id', $class->id)
            ->where('teacher_classes.academic_year', '2024-2025')
            ->where('teacher_classes.is_active', true)
            ->value('teacher_classes.teacher_id');

        return $teacherId;
    }

    private function isHoliday($date)
    {
        $holidays = [
            '2024-01-01', '2024-01-02', '2024-01-03', '2024-01-04', '2024-01-05', '2024-01-06', '2024-01-07', '2024-01-08',
            '2024-02-23',
            '2024-03-08',
            '2024-05-01',
            '2024-05-09',
            '2024-06-12',
            '2024-09-01',
            '2024-10-06',
        ];

        return in_array($date->format('Y-m-d'), $holidays);
    }
}
