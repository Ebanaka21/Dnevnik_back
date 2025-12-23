<?php

namespace App\Filament\Resources\WeeklyScheduleResource\Pages;

use App\Filament\Resources\WeeklyScheduleResource;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditWeeklySchedule extends EditRecord
{
    protected static string $resource = WeeklyScheduleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Загружаем существующие данные расписания для класса
        $schedules = Schedule::where('school_class_id', $this->record->id)
            ->orderBy('day_of_week')
            ->orderBy('lesson_number')
            ->get();

        $scheduleData = [
            'name' => $this->record->name,
            'academic_year' => $this->record->academic_year,
            'monday' => [],
            'tuesday' => [],
            'wednesday' => [],
            'thursday' => [],
            'friday' => [],
            'saturday' => [],
        ];

        // Заполняем 7 уроков для каждого дня
        for ($lesson = 1; $lesson <= 7; $lesson++) {
            $scheduleData['monday'][] = ['subject_id' => null, 'teacher_id' => null];
            $scheduleData['tuesday'][] = ['subject_id' => null, 'teacher_id' => null];
            $scheduleData['wednesday'][] = ['subject_id' => null, 'teacher_id' => null];
            $scheduleData['thursday'][] = ['subject_id' => null, 'teacher_id' => null];
            $scheduleData['friday'][] = ['subject_id' => null, 'teacher_id' => null];
            $scheduleData['saturday'][] = ['subject_id' => null, 'teacher_id' => null];
        }

        // Заполняем существующие уроки
        foreach ($schedules as $schedule) {
            $dayName = match ($schedule->day_of_week) {
                1 => 'monday',
                2 => 'tuesday',
                3 => 'wednesday',
                4 => 'thursday',
                5 => 'friday',
                6 => 'saturday',
                default => null
            };

            if ($dayName && isset($scheduleData[$dayName])) {
                $lessonIndex = $schedule->lesson_number - 1;
                if (isset($scheduleData[$dayName][$lessonIndex])) {
                    $scheduleData[$dayName][$lessonIndex] = [
                        'subject_id' => $schedule->subject_id,
                        'teacher_id' => $schedule->teacher_id,
                    ];
                }
            }
        }

        return $scheduleData;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Удаляем данные формы, так как мы будем создавать записи в цикле
        return [];
    }

    protected function afterSave(): void
    {
        $data = $this->form->getState();

        $classId = $this->record->id;

        // Определяем стандартное время уроков
        $lessonTimes = [
            1 => ['08:00', '08:45'],
            2 => ['08:55', '09:40'],
            3 => ['09:50', '10:35'],
            4 => ['10:55', '11:40'],
            5 => ['12:00', '12:45'],
            6 => ['13:00', '13:45'],
            7 => ['14:00', '14:45'],
        ];

        DB::transaction(function () use ($classId, $data, $lessonTimes) {
            // Удаляем существующие записи для этого класса
            Schedule::where('school_class_id', $classId)->delete();

            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

            foreach ($days as $dayIndex => $day) {
                $dayOfWeek = $dayIndex + 1; // 1-6 (понедельник-суббота)

                if (isset($data[$day])) {
                    foreach ($data[$day] as $lessonIndex => $lesson) {
                        $lessonNumber = $lessonIndex + 1;

                        // Пропускаем если предмет не выбран (пустой урок)
                        if (empty($lesson['subject_id'])) {
                            continue;
                        }

                        Schedule::create([
                            'subject_id' => $lesson['subject_id'],
                            'school_class_id' => $classId,
                            'teacher_id' => $lesson['teacher_id'] ?? null,
                            'day_of_week' => $dayOfWeek,
                            'lesson_number' => $lessonNumber,
                            'start_time' => $lessonTimes[$lessonNumber][0] ?? '08:00',
                            'end_time' => $lessonTimes[$lessonNumber][1] ?? '08:45',
                            'is_active' => true,
                        ]);
                    }
                }
            }
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Назад к списку')
                ->url($this->getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }
}
