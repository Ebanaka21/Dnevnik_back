<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class EditSchedule extends EditRecord
{
    protected static string $resource = ScheduleResource::class;

    public $scheduleFormData = [];

    public function form(Schema $schema): Schema
    {
        $classId = $this->record->id;
        $subjects = Subject::all()->pluck('name', 'id')->toArray();

        // Только ФИО без email
        $teachers = User::where('role', 'teacher')
            ->get()
            ->mapWithKeys(function ($teacher) {
                return [$teacher->id => $teacher->full_name];
            })->toArray();

        $daysOfWeek = [
            1 => 'Понедельник',
            2 => 'Вторник',
            3 => 'Среда',
            4 => 'Четверг',
            5 => 'Пятница',
            6 => 'Суббота',
        ];

        $sections = [];

        // Секция с информацией о классе
        $sections[] = Section::make('Информация о классе')
            ->schema([
                Forms\Components\Placeholder::make('class_info')
                    ->label('Класс')
                    ->content($this->record->full_name),

                Forms\Components\Placeholder::make('academic_year_info')
                    ->label('Учебный год')
                    ->content($this->record->academic_year ?? 'Не указан'),

                Forms\Components\Placeholder::make('class_teacher_info')
                    ->label('Классный руководитель')
                    ->content($this->record->classTeacher?->full_name ?? 'Не назначен'),
            ])
            ->columns(3);

        foreach ($daysOfWeek as $dayNumber => $dayName) {
            $lessons = [];

            for ($lessonNumber = 1; $lessonNumber <= 7; $lessonNumber++) {
                $lessons[] = Grid::make(3)
                    ->schema([
                        Forms\Components\Select::make("day_{$dayNumber}_lesson_{$lessonNumber}_subject")
                            ->label("Урок {$lessonNumber}")
                            ->options($subjects)
                            ->searchable()
                            ->placeholder('Предмет'),

                        Forms\Components\Select::make("day_{$dayNumber}_lesson_{$lessonNumber}_teacher")
                            ->label('Учитель')
                            ->options($teachers)
                            ->searchable()
                            ->placeholder('Учитель'),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make("day_{$dayNumber}_lesson_{$lessonNumber}_start")
                                    ->label('Начало')
                                    ->seconds(false)
                                    ->default($this->getDefaultTime($lessonNumber, 'start')),

                                Forms\Components\TimePicker::make("day_{$dayNumber}_lesson_{$lessonNumber}_end")
                                    ->label('Конец')
                                    ->seconds(false)
                                    ->default($this->getDefaultTime($lessonNumber, 'end')),
                            ]),
                    ]);
            }

            $sections[] = Section::make($dayName)
                ->description("Расписание на {$dayName}")
                ->schema($lessons)
                ->collapsible()
                ->collapsed(false);
        }

        return $schema->components($sections);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $schedules = Schedule::where('school_class_id', $this->record->id)
            ->where('is_active', true)
            ->get();

        foreach ($schedules as $schedule) {
            $prefix = "day_{$schedule->day_of_week}_lesson_{$schedule->lesson_number}";
            $data[$prefix . '_subject'] = $schedule->subject_id;
            $data[$prefix . '_teacher'] = $schedule->teacher_id;
            $data[$prefix . '_start'] = $schedule->start_time ? $schedule->start_time->format('H:i') : null;
            $data[$prefix . '_end'] = $schedule->end_time ? $schedule->end_time->format('H:i') : null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Сохраняем данные расписания для afterSave
        $this->scheduleFormData = $data;

        // Возвращаем минимальные данные для модели класса
        return [
            'id' => $this->record->id,
        ];
    }

    protected function afterSave(): void
    {
        // Используем сохранённые данные формы
        $data = $this->scheduleFormData ?? $this->form->getState();

        DB::transaction(function () use ($data) {
            Schedule::where('school_class_id', $this->record->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            for ($day = 1; $day <= 6; $day++) {
                for ($lesson = 1; $lesson <= 7; $lesson++) {
                    $prefix = "day_{$day}_lesson_{$lesson}";

                    $subjectId = $data[$prefix . '_subject'] ?? null;
                    $teacherId = $data[$prefix . '_teacher'] ?? null;

                    if ($subjectId && $teacherId) {
                        Schedule::create([
                            'school_class_id' => $this->record->id,
                            'subject_id' => $subjectId,
                            'teacher_id' => $teacherId,
                            'day_of_week' => $day,
                            'lesson_number' => $lesson,
                            'start_time' => $data[$prefix . '_start'] ?? $this->getDefaultTime($lesson, 'start'),
                            'end_time' => $data[$prefix . '_end'] ?? $this->getDefaultTime($lesson, 'end'),
                            'academic_year' => $this->record->academic_year,
                            'is_active' => true,
                        ]);
                    }
                }
            }
        });

        Notification::make()
            ->title('Расписание сохранено')
            ->success()
            ->send();
    }

    private function getDefaultTime(int $lessonNumber, string $type): string
    {
        $times = [
            1 => ['start' => '08:00', 'end' => '08:45'],
            2 => ['start' => '08:55', 'end' => '09:40'],
            3 => ['start' => '09:50', 'end' => '10:35'],
            4 => ['start' => '10:55', 'end' => '11:40'],
            5 => ['start' => '11:50', 'end' => '12:35'],
            6 => ['start' => '12:45', 'end' => '13:30'],
            7 => ['start' => '13:40', 'end' => '14:25'],
        ];

        return $times[$lessonNumber][$type] ?? '08:00';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
