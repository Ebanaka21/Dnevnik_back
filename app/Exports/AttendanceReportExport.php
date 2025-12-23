<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class AttendanceReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $studentId;
    protected $periodStart;
    protected $periodEnd;
    protected $student;

    public function __construct($studentId, $periodStart, $periodEnd)
    {
        $this->studentId = $studentId;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
        $this->student = User::find($studentId);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $attendance = Attendance::where('student_id', $this->studentId)
            ->whereBetween('date', [$this->periodStart, $this->periodEnd])
            ->with(['lesson.subject:id,name', 'teacher:id,name'])
            ->orderBy('date', 'desc')
            ->get();

        $data = [];

        // Заголовки для отчета
        $data[] = [
            'student' => $this->student->name,
            'period' => $this->periodStart . ' - ' . $this->periodEnd,
            'generated_at' => now()->format('d.m.Y H:i')
        ];

        // Пустая строка
        $data[] = ['', '', '', '', '', ''];

        // Заголовки таблицы
        $data[] = ['Дата', 'Предмет', 'Статус', 'Учитель', 'Причина', 'Комментарий'];

        // Данные посещаемости
        foreach ($attendance as $record) {
            $statusText = match($record->status) {
                'present' => 'Присутствовал',
                'absent' => 'Отсутствовал',
                'late' => 'Опоздал',
                'excused' => 'Отсутствовал (уважительная причина)',
                default => $record->status
            };

            $data[] = [
                Carbon::parse($record->date)->format('d.m.Y'),
                $record->lesson->subject->name,
                $statusText,
                $record->teacher->name ?? '',
                $record->reason ?? '',
                $record->comment ?? ''
            ];
        }

        // Пустая строка
        $data[] = ['', '', '', '', '', ''];

        // Статистика
        $data[] = ['СТАТИСТИКА ПОСЕЩАЕМОСТИ', '', '', '', '', ''];
        $data[] = ['Всего занятий:', $attendance->count(), '', '', '', ''];
        $data[] = ['Присутствовал:', $attendance->where('status', 'present')->count(), '', '', '', ''];
        $data[] = ['Отсутствовал:', $attendance->where('status', 'absent')->count(), '', '', '', ''];
        $data[] = ['Опоздал:', $attendance->where('status', 'late')->count(), '', '', '', ''];
        $data[] = ['Уважительная причина:', $attendance->where('status', 'excused')->count(), '', '', '', ''];

        // Процент посещаемости
        $total = $attendance->count();
        $present = $attendance->where('status', 'present')->count();
        $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

        $data[] = ['Процент посещаемости:', $percentage . '%', '', '', '', ''];

        // Статистика по предметам
        $subjectStats = $attendance->groupBy('lesson.subject_id')->map(function($subjectAttendance) {
            $subject = $subjectAttendance->first()->lesson->subject;
            $total = $subjectAttendance->count();
            $present = $subjectAttendance->where('status', 'present')->count();

            return [
                'subject' => $subject->name,
                'total' => $total,
                'present' => $present,
                'percentage' => $total > 0 ? round(($present / $total) * 100, 2) : 0
            ];
        });

        $data[] = ['СТАТИСТИКА ПО ПРЕДМЕТАМ', '', '', '', '', ''];
        foreach ($subjectStats as $stat) {
            $data[] = [
                'По предмету "' . $stat['subject'] . '":',
                $stat['percentage'] . '%',
                '(' . $stat['present'] . '/' . $stat['total'] . ')',
                '',
                '',
                ''
            ];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Отчет по посещаемости ученика'
        ];
    }

    public function map($row): array
    {
        return $row;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['bold' => true]],
            3 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function title(): string
    {
        return 'Отчет по посещаемости';
    }
}
