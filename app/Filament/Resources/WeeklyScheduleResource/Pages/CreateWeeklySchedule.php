<?php

namespace App\Filament\Resources\WeeklyScheduleResource\Pages;

use App\Filament\Resources\WeeklyScheduleResource;
use App\Models\Schedule;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateWeeklySchedule extends CreateRecord
{
    protected static string $resource = WeeklyScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Удаляем данные формы, так как мы будем создавать записи в цикле
        return [];
    }

    protected function handleRecordCreation(array $data): Schedule
    {
        $classId = $data['school_class_id'];

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

        // Возвращаем фиктивную запись для совместимости с Filament
        return Schedule::where('school_class_id', $classId)->first() ?? new Schedule();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
