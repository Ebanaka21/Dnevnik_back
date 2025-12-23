<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class PerformanceReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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
        $grades = Grade::where('student_id', $this->studentId)
            ->whereBetween('date', [$this->periodStart, $this->periodEnd])
            ->with(['subject:id,name', 'gradeType:id,name', 'teacher:id,name'])
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
        $data[] = ['', '', '', '', '', '', ''];

        // Заголовки таблицы
        $data[] = ['Дата', 'Предмет', 'Оценка', 'Тип', 'Учитель', 'Комментарий', 'Вес'];

        // Данные оценок
        foreach ($grades as $grade) {
            $data[] = [
                Carbon::parse($grade->date)->format('d.m.Y'),
                $grade->subject->name,
                $grade->grade,
                $grade->gradeType->name,
                $grade->teacher->name,
                $grade->comment ?? '',
                $grade->weight ?? 1
            ];
        }

        // Пустая строка
        $data[] = ['', '', '', '', '', '', ''];

        // Статистика
        $data[] = ['СТАТИСТИКА', '', '', '', '', '', ''];
        $data[] = ['Общее количество оценок:', $grades->count(), '', '', '', '', ''];
        $data[] = ['Средняя оценка:', round($grades->avg('grade_value'), 2), '', '', '', '', ''];

        // Статистика по предметам
        $subjectStats = $grades->groupBy('subject_id')->map(function($subjectGrades) {
            return [
                'subject' => $subjectGrades->first()->subject->name,
                'count' => $subjectGrades->count(),
                'average' => round($subjectGrades->avg('grade_value'), 2)
            ];
        });

        foreach ($subjectStats as $stat) {
            $data[] = ['Средняя по предмету "' . $stat['subject'] . '":', $stat['average'], '(' . $stat['count'] . ' оценок)', '', '', '', ''];
        }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'Отчет по успеваемости ученика'
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
        return 'Отчет по успеваемости';
    }
}
