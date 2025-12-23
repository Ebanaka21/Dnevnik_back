<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CreateSchedule extends CreateRecord
{
    protected static string $resource = 'App\Filament\Resources\ScheduleResource';

    public $scheduleFormData = [];

    public function form(Schema $schema): Schema
    {
        $classes = SchoolClass::where('is_active', true)
            ->get()
            ->mapWithKeys(function ($class) {
                return [$class->id => $class->full_name];
            })->toArray();

        $subjects = Subject::all()
            ->mapWithKeys(function ($subject) {
                return [$subject->id => $subject->name];
            })->toArray();

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

        // Выбор класса
        $sections[] = Section::make('Выберите класс')
            ->description('Для какого класса создаём расписание')
            ->schema([
                Forms\Components\Select::make('class_id')
                    ->label('Класс')
                    ->options($classes)
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $class = SchoolClass::find($state);
                            $set('academic_year', $class?->academic_year);
                        }
                    }),

                Forms\Components\TextInput::make('academic_year')
                    ->label('Учебный год')
                    ->default(date('Y') . '-' . (date('Y') + 1))
                    ->required(),

                Forms\Components\Select::make('first_lesson_time')
                    ->label('Время начала 1-го урока')
                    ->options([
                        '08:00' => '08:00',
                        '08:30' => '08:30',
                        '09:00' => '09:00',
                        '09:30' => '09:30',
                    ])
                    ->default('09:00')
                    ->required(),

                Forms\Components\Select::make('lesson_duration')
                    ->label('Длительность урока (минут)')
                    ->options([
                        45 => '45',
                        40 => '40',
                        50 => '50',
                    ])
                    ->default(45)
                    ->required(),
            ])
            ->columns(2);

        // Расписание по дням
        foreach ($daysOfWeek as $dayNumber => $dayName) {
            $lessons = [];

            for ($lessonNumber = 1; $lessonNumber <= 7; $lessonNumber++) {
                $lessons[] = Grid::make(2)
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
                    ]);
            }

            $sections[] = Section::make($dayName)
                ->description("Расписание на {$dayName}")
                ->schema($lessons)
                ->collapsible()
                ->collapsed(true);
        }

        return $schema->components($sections);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Сохраняем данные для afterCreate
        $this->scheduleFormData = $data;

        // Возвращаем пустой массив, т.к. мы не создаём SchoolClass
        return [];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Возвращаем существующий класс
        $classId = $this->scheduleFormData['class_id'] ?? $data['class_id'];
        return SchoolClass::findOrFail($classId);
    }

    protected function afterCreate(): void
    {
        $data = $this->scheduleFormData;
        $classId = $data['class_id'];
        $academicYear = $data['academic_year'];
        $firstLessonTime = $data['first_lesson_time'] ?? '09:00';
        $lessonDuration = (int)($data['lesson_duration'] ?? 45);

        DB::transaction(function () use ($data, $classId, $academicYear, $firstLessonTime, $lessonDuration) {
            // Удаляем старое расписание этого класса для избежания дубликатов
            Schedule::where('school_class_id', $classId)
                ->where('academic_year', $academicYear)
                ->delete();

            // Создаем новое расписание
            for ($day = 1; $day <= 6; $day++) {
                for ($lesson = 1; $lesson <= 7; $lesson++) {
                    $prefix = "day_{$day}_lesson_{$lesson}";

                    $subjectId = $data[$prefix . '_subject'] ?? null;
                    $teacherId = $data[$prefix . '_teacher'] ?? null;

                    if ($subjectId && $teacherId) {
                        [$startTime, $endTime] = $this->calculateLessonTime($firstLessonTime, $lesson, $lessonDuration);

                        Schedule::create([
                            'school_class_id' => $classId,
                            'subject_id' => $subjectId,
                            'teacher_id' => $teacherId,
                            'day_of_week' => $day,
                            'lesson_number' => $lesson,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'academic_year' => $academicYear,
                            'is_active' => true,
                        ]);
                    }
                }
            }
        });

        Notification::make()
            ->title('Расписание создано')
            ->success()
            ->send();
    }

    /**
     * Рассчитать время начала и конца урока
     */
    private function calculateLessonTime(string $firstLessonTime, int $lessonNumber, int $lessonDuration): array
    {
        $breakDuration = 15; // 15 минут перерыва между уроками

        $currentTime = \DateTime::createFromFormat('H:i', $firstLessonTime);

        // Перемещаемся на нужный урок
        for ($i = 1; $i < $lessonNumber; $i++) {
            $currentTime->modify("+{$lessonDuration} minutes");
            $currentTime->modify("+{$breakDuration} minutes");
        }

        $startTime = clone $currentTime;
        $endTime = clone $startTime;
        $endTime->modify("+{$lessonDuration} minutes");

        return [
            $startTime->format('H:i'),
            $endTime->format('H:i'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Расписание успешно создано';
    }
}

