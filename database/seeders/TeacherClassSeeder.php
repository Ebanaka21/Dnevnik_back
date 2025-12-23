<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class TeacherClassSeeder extends Seeder
{
    /**
     * Создание связей между учителями, классами и предметами с расширенными данными
     *
     * Назначает учителей на классы по предметам, устанавливает
     * классных руководителей для классов и создает расписание.
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание связей учитель-класс-предмет...');

        // Получаем всех учителей
        $teachers = User::where('role', 'teacher')->get();

        // Получаем все классы текущего учебного года
        $classes = SchoolClass::where('academic_year', '2024-2025')
                             ->where('is_active', true)
                             ->get();

        // Получаем все предметы
        $subjects = Subject::where('is_active', true)->get();

        $this->createTeacherSubjectClassLinks($teachers, $classes, $subjects);
        $this->assignClassTeachers($teachers, $classes);
        $this->createScheduleData($teachers, $classes, $subjects);

        $this->command->info('Связи учитель-класс-предмет созданы!');
    }

    private function createTeacherSubjectClassLinks($teachers, $classes, $subjects)
    {
        // Сопоставление email учителя с предметами и классами
        $teacherAssignments = [
            'ivanova.mp@school.ru' => [
                'subjects' => ['Математика', 'Физика'],
                'classes' => ['5А', '5Б', '6А', '6Б', '7А', '8А', '10А', '10Б']
            ],
            'petrov.as@school.ru' => [
                'subjects' => ['Русский язык', 'Литература'],
                'classes' => ['5А', '5Б', '6А', '6Б', '7А', '8А', '9А', '9Б', '10А', '11А']
            ],
            'sidorova.ev@school.ru' => [
                'subjects' => ['История', 'Обществознание'],
                'classes' => ['5А', '6А', '7А', '8А', '9А', '9Б', '10Б', '11Б']
            ],
            'kozlov.dn@school.ru' => [
                'subjects' => ['Информатика', 'Математика'],
                'classes' => ['7А', '8А', '9А', '10А', '11А', '11Б']
            ],
            'morozova.ai@school.ru' => [
                'subjects' => ['Английский язык'],
                'classes' => ['5А', '5Б', '6А', '6Б', '7А', '8А', '9А', '10А', '10Б', '11А']
            ],
            'vasilev.sp@school.ru' => [
                'subjects' => ['Физкультура', 'ОБЖ'],
                'classes' => ['5А', '5Б', '6А', '6Б', '7А', '8А', '9А', '10А', '10Б', '11А', '11Б']
            ],
            'novikova.tm@school.ru' => [
                'subjects' => ['Химия', 'Биология'],
                'classes' => ['7А', '8А', '9А', '10А', '10Б', '11А', '11Б']
            ],
            'sokolov.va@school.ru' => [
                'subjects' => ['География', 'Музыка'],
                'classes' => ['5А', '6А', '7А', '8А', '9А', '10Б', '11Б']
            ],
            'volkova.is@school.ru' => [
                'subjects' => ['Изобразительное искусство'],
                'classes' => ['5А', '6А', '7А', '8А']
            ]
        ];

        foreach ($teacherAssignments as $email => $assignment) {
            $teacher = $teachers->where('email', $email)->first();
            if (!$teacher) continue;

            foreach ($assignment['subjects'] as $subjectName) {
                $subject = $subjects->where('name', $subjectName)->first();
                if (!$subject) continue;

                foreach ($assignment['classes'] as $className) {
                    $class = $classes->where('name', $className)->first();
                    if (!$class) continue;

                    // Проверяем, существует ли уже связь
                    $existingLink = DB::table('teacher_classes')
                        ->where('teacher_id', $teacher->id)
                        ->where('school_class_id', $class->id)
                        ->where('subject_id', $subject->id)
                        ->where('academic_year', '2024-2025')
                        ->first();

                    if (!$existingLink) {
                        DB::table('teacher_classes')->insert([
                            'teacher_id' => $teacher->id,
                            'school_class_id' => $class->id,
                            'subject_id' => $subject->id,
                            'academic_year' => '2024-2025',
                            'is_active' => true,
                            'assigned_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        $this->command->info('Связи учитель-предмет-класс созданы');
    }

    private function assignClassTeachers($teachers, $classes)
    {
        // Назначаем классных руководителей
        $classTeacherAssignments = [
            '5А' => 'ivanova.mp@school.ru',
            '5Б' => 'petrov.as@school.ru',
            '6А' => 'sidorova.ev@school.ru',
            '6Б' => 'kozlov.dn@school.ru',
            '7А' => 'morozova.ai@school.ru',
            '7Б' => 'vasilev.sp@school.ru',
            '8А' => 'novikova.tm@school.ru',
            '8Б' => 'sokolov.va@school.ru',
            '9А' => 'volkova.is@school.ru',
            '9Б' => 'ivanova.mp@school.ru',
            '10А' => 'petrov.as@school.ru',
            '10Б' => 'sidorova.ev@school.ru',
            '11А' => 'kozlov.dn@school.ru',
            '11Б' => 'morozova.ai@school.ru'
        ];

        foreach ($classTeacherAssignments as $className => $email) {
            $class = $classes->where('name', $className)->first();
            $teacher = $teachers->where('email', $email)->first();

            if ($class && $teacher) {
                $class->update(['class_teacher_id' => $teacher->id]);
            }
        }

        $this->command->info('Классные руководители назначены: ' . count($classTeacherAssignments) . ' классов');
    }

    /**
     * Создание расписания уроков для классов
     */
    private function createScheduleData($teachers, $classes, $subjects)
    {
        $this->command->info('Создаем расписание уроков...');

        // Определяем дни недели и уроки
        $daysOfWeek = [1, 2, 3, 4, 5]; // Пн-Пт
        $periods = [
            1 => ['start' => '08:30', 'end' => '09:15'],
            2 => ['start' => '09:25', 'end' => '10:10'],
            3 => ['start' => '10:30', 'end' => '11:15'],
            4 => ['start' => '11:25', 'end' => '12:10'],
            5 => ['start' => '13:00', 'end' => '13:45'],
            6 => ['start' => '13:55', 'end' => '14:40'],
            7 => ['start' => '14:50', 'end' => '15:35'],
            8 => ['start' => '15:45', 'end' => '16:30']
        ];

        $scheduleRecordsCreated = 0;

        foreach ($classes as $class) {
            foreach ($daysOfWeek as $dayOfWeek) {
                // Получаем предметы для этого класса
                $classSubjects = $this->getClassSubjects($class, $subjects);

                $subjectIndex = 0;

                foreach ($periods as $periodNumber => $times) {
                    if ($subjectIndex >= count($classSubjects)) {
                        break; // Больше нет предметов на этот день
                    }

                    $subject = $classSubjects[$subjectIndex];
                    $teacher = $this->getSubjectTeacher($subject, $class, $teachers);

                    if ($teacher) {
                        // Создаем запись расписания
                         $existingSchedule = \App\Models\Schedule::where('school_class_id', $class->id)
                             ->where('day_of_week', $dayOfWeek)
                             ->where('lesson_number', $periodNumber)
                             ->first();

                         if (!$existingSchedule) {
                            \App\Models\Schedule::create([
                                'school_class_id' => $class->id,
                                'subject_id' => $subject->id,
                                'teacher_id' => $teacher->id,
                                'day_of_week' => $dayOfWeek,
                                'lesson_number' => $periodNumber,
                                'start_time' => $times['start'],
                                'end_time' => $times['end'],
                                'academic_year' => '2024-2025',
                                'is_active' => true,
                            ]);
                            $scheduleRecordsCreated++;
                        }
                    }

                    $subjectIndex++;
                }
            }
        }

        $this->command->info("Создано записей расписания: {$scheduleRecordsCreated}");
    }

    /**
     * Получение предметов для класса
     */
    private function getClassSubjects($class, $subjects)
    {
        // Определяем предметы для класса в зависимости от года обучения
        $classYear = $class->year;

        $classSubjects = [
            5 => ['Русский язык', 'Математика', 'Литература', 'История', 'Английский язык', 'Физкультура', 'Изобразительное искусство'],
            6 => ['Русский язык', 'Математика', 'Литература', 'История', 'Английский язык', 'Физкультура', 'Изобразительное искусство', 'География'],
            7 => ['Русский язык', 'Математика', 'Литература', 'История', 'Английский язык', 'Физкультура', 'География', 'Информатика', 'Химия', 'Биология'],
            8 => ['Русский язык', 'Математика', 'Литература', 'История', 'Английский язык', 'Физкультура', 'География', 'Информатика', 'Химия', 'Биология', 'Физика'],
            9 => ['Русский язык', 'Математика', 'Литература', 'История', 'Английский язык', 'Физкультура', 'География', 'Информатика', 'Химия', 'Биология', 'Физика', 'Обществознание'],
            10 => ['Русский язык', 'Математика', 'Литература', 'История', 'Английский язык', 'Физкультура', 'Информатика', 'Химия', 'Биология', 'Физика', 'Обществознание'],
            11 => ['Русский язык', 'Математика', 'Литература', 'История', 'Английский язык', 'Физкультура', 'Информатика', 'Химия', 'Биология', 'Физика', 'Обществознание']
        ];

        $subjectNames = $classSubjects[$classYear] ?? $classSubjects[9];

        return $subjects->whereIn('name', $subjectNames)->values();
    }

    /**
     * Получение учителя для предмета и класса
     */
    private function getSubjectTeacher($subject, $class, $teachers)
    {
        // Проверяем связь через teacher_classes
        $teacherClass = DB::table('teacher_classes')
            ->where('subject_id', $subject->id)
            ->where('school_class_id', $class->id)
            ->where('academic_year', '2024-2025')
            ->where('is_active', true)
            ->first();

        if ($teacherClass) {
            return $teachers->find($teacherClass->teacher_id);
        }

        // Если нет прямой связи, ищем учителя предмета
        foreach ($teachers as $teacher) {
            if ($teacher->subjects->contains($subject->id)) {
                return $teacher;
            }
        }

        return null;
    }
}
