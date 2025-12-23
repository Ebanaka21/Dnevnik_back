<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Schedule;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Сидер для создания расписания уроков
 *
 * Создает расписание на основе связей учитель-класс-предмет,
 * добавляет кабинеты и другие детали.
 */
class ScheduleSeeder extends Seeder
{
    /**
     * Запуск сидера
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание расписания уроков...');

        // Очищаем таблицу расписания перед созданием нового
        Schedule::truncate();

        // Получаем все связи учитель-класс-предмет, группируем по классу
        $teacherClassesGrouped = DB::table('teacher_classes')
            ->where('academic_year', '2024-2025')
            ->where('is_active', true)
            ->get()
            ->groupBy('school_class_id');

        $this->command->info("Найдено классов: {$teacherClassesGrouped->count()}");

        $scheduleCreated = 0;

        foreach ($teacherClassesGrouped as $classId => $classTeacherClasses) {
            $scheduleCreated += $this->createScheduleForClass($classId, $classTeacherClasses);
        }

        $this->command->info("Создано записей расписания: {$scheduleCreated}");
    }

    /**
     * Создание расписания для конкретного класса
     */
    private function createScheduleForClass($classId, $teacherClasses)
    {
        $class = SchoolClass::find($classId);
        if (!$class) {
            return 0;
        }

        $created = 0;

        // Для каждого дня недели (1-5, понедельник-пятница)
        for ($dayOfWeek = 1; $dayOfWeek <= 5; $dayOfWeek++) {
            $subjectsForDay = [];

            // Собираем предметы для этого дня
            foreach ($teacherClasses as $teacherClass) {
                $subject = Subject::find($teacherClass->subject_id);
                $teacher = User::find($teacherClass->teacher_id);

                if (!$subject || !$teacher) {
                    continue;
                }

                // Получаем план учебного плана для предмета
                $plan = DB::table('curriculum_plans')
                    ->where('school_class_id', $classId)
                    ->where('subject_id', $teacherClass->subject_id)
                    ->where('academic_year', '2024-2025')
                    ->first();

                if (!$plan) {
                    continue;
                }

                $hours = $plan->hours_per_week;
                $daysForSubject = range(1, min($hours, 5)); // дни с 1 по hours, максимум 5

                // Проверяем, преподается ли предмет в этот день
                if (in_array($dayOfWeek, $daysForSubject)) {
                    $subjectsForDay[] = [
                        'subject' => $subject,
                        'teacher' => $teacher,
                    ];
                }
            }

            // Назначаем номера уроков последовательно
            $lessonNumber = 1;
            foreach ($subjectsForDay as $item) {
                // Получаем подходящие номера уроков для этого предмета
                $lessonNumbers = $this->getLessonNumbersForSubject($item['subject']->name, $class->year_level, $dayOfWeek);

                foreach ($lessonNumbers as $assignedLessonNumber) {
                    // Проверяем, существует ли уже расписание
                    $existing = Schedule::where('school_class_id', $class->id)
                        ->where('day_of_week', $dayOfWeek)
                        ->where('lesson_number', $assignedLessonNumber)
                        ->where('academic_year', '2024-2025')
                        ->first();

                    if (!$existing) {
                        $times = $this->getLessonTimes($assignedLessonNumber);

                        Schedule::create([
                            'school_class_id' => $class->id,
                            'subject_id' => $item['subject']->id,
                            'teacher_id' => $item['teacher']->id,
                            'day_of_week' => $dayOfWeek,
                            'lesson_number' => $assignedLessonNumber,
                            'start_time' => $times['start'],
                            'end_time' => $times['end'],
                            'academic_year' => '2024-2025',
                            'is_active' => true,
                        ]);

                        $created++;
                        break; // Создаем только один урок для этого предмета в этот день
                    }
                }
            }
        }

        return $created;
    }

    /**
     * Получение дней недели для предмета
     */
    private function getDaysForSubject($subjectName, $classYear)
    {
        // Основные предметы - каждый день, второстепенные - реже
        $subjectFrequency = [
            'Математика' => [1, 2, 3, 4, 5], // Каждый день
            'Русский язык' => [1, 2, 3, 4, 5],
            'Литература' => [1, 3, 5],
            'История' => [2, 4],
            'Обществознание' => [3, 5],
            'Физика' => [2, 4],
            'Химия' => [3],
            'Биология' => [4],
            'География' => [5],
            'Английский язык' => [1, 3, 5],
            'Информатика' => [2, 4],
            'Физкультура' => [1, 3, 5],
            'ОБЖ' => [4],
            'Музыка' => [2],
            'Изобразительное искусство' => [4],
        ];

        return $subjectFrequency[$subjectName] ?? [1, 3, 5]; // По умолчанию 3 раза в неделю
    }

    /**
     * Получение номеров уроков для предмета
     */
    private function getLessonNumbersForSubject($subjectName, $classYear, $dayOfWeek)
    {
        // Определяем номера уроков в зависимости от предмета и дня
        $subjectSlots = [
            'Математика' => [1, 4], // Утро и после обеда
            'Русский язык' => [2, 5],
            'Литература' => [3],
            'История' => [6],
            'Обществознание' => [7],
            'Физика' => [4],
            'Химия' => [5],
            'Биология' => [6],
            'География' => [7],
            'Английский язык' => [3],
            'Информатика' => [6],
            'Физкультура' => [7, 8], // После основных уроков
            'ОБЖ' => [8],
            'Музыка' => [7],
            'Изобразительное искусство' => [8],
        ];

        $slots = $subjectSlots[$subjectName] ?? [3]; // По умолчанию 3-й урок

        // Для каждого дня берем только один слот, чтобы не перегружать
        return [array_shift($slots)];
    }

    /**
     * Получение времени урока
     */
    private function getLessonTimes($lessonNumber)
    {
        $times = [
            1 => ['start' => '08:30', 'end' => '09:15'],
            2 => ['start' => '09:25', 'end' => '10:10'],
            3 => ['start' => '10:30', 'end' => '11:15'],
            4 => ['start' => '11:25', 'end' => '12:10'],
            5 => ['start' => '13:00', 'end' => '13:45'],
            6 => ['start' => '13:55', 'end' => '14:40'],
            7 => ['start' => '14:50', 'end' => '15:35'],
            8 => ['start' => '15:45', 'end' => '16:30']
        ];

        return $times[$lessonNumber] ?? $times[1];
    }

    /**
     * Получение кабинета для предмета
     */
    private function getClassroomForSubject($subjectName, $classrooms)
    {
        return $classrooms[$subjectName] ?? $classrooms['default'] ?? '101';
    }

    /**
     * Получение списка кабинетов
     */
    private function getClassrooms()
    {
        return [
            'Математика' => '201',
            'Русский язык' => '202',
            'Литература' => '203',
            'История' => '204',
            'Обществознание' => '205',
            'Физика' => '301',
            'Химия' => '302',
            'Биология' => '303',
            'География' => '304',
            'Английский язык' => '101',
            'Информатика' => '401',
            'Физкультура' => 'Спортзал',
            'ОБЖ' => '205',
            'Музыка' => 'Муз. зал',
            'Изобразительное искусство' => 'Худ. каб.',
            'default' => '101'
        ];
    }
}
