<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

class StudentClassSeeder extends Seeder
{
    /**
     * Заполнение классов учениками
     *
     * Автоматически распределяет учеников по классам с учетом:
     * - Максимального количества учеников в классе
     * - Реалистичного распределения по годам обучения
     * - Установки академического года и даты зачисления
     */
    public function run(): void
    {
        $this->command->info('Начинаем распределение учеников по классам...');

        // Получаем всех учеников без класса
        $students = User::where('role', 'student')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('student_classes')
                      ->whereRaw('student_classes.student_id = users.id')
                      ->where('student_classes.academic_year', '2024-2025')
                      ->where('student_classes.is_active', true);
            })
            ->get();

        if ($students->isEmpty()) {
            $this->command->info('Все ученики уже распределены по классам');
            return;
        }

        // Получаем активные классы текущего учебного года
        $classes = SchoolClass::where('is_active', true)
            ->where('academic_year', '2024-2025')
            ->orderBy('year')
            ->orderBy('letter')
            ->get();

        if ($classes->isEmpty()) {
            $this->command->error('Активные классы не найдены! Запустите SchoolSeeder сначала.');
            return;
        }

        $this->command->info("Найдено учеников: {$students->count()}");
        $this->command->info("Найдено классов: {$classes->count()}");

        // Распределяем учеников по классам
        $assignedCount = $this->assignStudentsToClasses($students, $classes);

        $this->command->info("Распределено учеников по классам: {$assignedCount}");
        $this->command->info('Распределение учеников завершено!');
    }

    private function assignStudentsToClasses($students, $classes)
    {
        $assignedCount = 0;
        $studentIndex = 0;

        // Сначала распределяем минимум 3 учеников на каждый класс
        foreach ($classes as $class) {
            $minStudents = 3; // Минимум учеников на класс
            $studentsToAdd = min($minStudents, $students->count() - $studentIndex);

            $this->command->info("Класс {$class->full_name}: добавляем минимум {$studentsToAdd} учеников");

            for ($i = 0; $i < $studentsToAdd && $studentIndex < $students->count(); $i++) {
                $student = $students[$studentIndex];

                // Создаем связь ученик-класс
                \Illuminate\Support\Facades\DB::table('student_classes')->updateOrInsert(
                    [
                        'student_id' => $student->id,
                        'school_class_id' => $class->id,
                        'academic_year' => '2024-2025'
                    ],
                    [
                        'school_class_id' => $class->id,
                        'is_active' => true,
                        'enrolled_at' => $this->generateEnrollmentDate($class->year),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );

                $assignedCount++;
                $studentIndex++;
            }
        }

        // Теперь распределяем оставшихся учеников по классам
        $remainingStudents = $students->count() - $studentIndex;
        if ($remainingStudents > 0) {
            $this->command->info("Распределяем {$remainingStudents} оставшихся учеников...");

            $classIndex = 0;
            while ($studentIndex < $students->count()) {
                $class = $classes[$classIndex % $classes->count()];

                $student = $students[$studentIndex];

                // Создаем связь ученик-класс
                \Illuminate\Support\Facades\DB::table('student_classes')->updateOrInsert(
                    [
                        'student_id' => $student->id,
                        'school_class_id' => $class->id,
                        'academic_year' => '2024-2025'
                    ],
                    [
                        'school_class_id' => $class->id,
                        'is_active' => true,
                        'enrolled_at' => $this->generateEnrollmentDate($class->year),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );

                $assignedCount++;
                $studentIndex++;
                $classIndex++;
            }
        }

        return $assignedCount;
    }

    private function calculateStudentsForClass($class, $remainingStudents)
    {
        // Базовое количество учеников: 7-12 на класс
        $baseCount = rand(7, 12);

        // Учитываем максимальное количество учеников в классе
        if ($class->max_students) {
            $baseCount = min($baseCount, $class->max_students);
        }

        // Проверяем сколько уже учеников в классе
        $currentStudents = $class->students()->count();
        $availableSlots = max(0, $baseCount - $currentStudents);

        // Не превышаем доступное количество оставшихся учеников
        return min($availableSlots, $remainingStudents);
    }

    private function generateEnrollmentDate($year)
    {
        // Дата зачисления в зависимости от года обучения
        $enrollmentDates = [
            1 => '2024-09-01', // 1 сентября
            2 => '2023-09-01', // Год назад
            3 => '2022-09-01', // Два года назад
            4 => '2021-09-01', // Три года назад
            5 => '2020-09-01', // Четыре года назад
            6 => '2019-09-01', // Пять лет назад
            7 => '2018-09-01', // Шесть лет назад
            8 => '2017-09-01', // Семь лет назад
            9 => '2016-09-01', // Восемь лет назад
            10 => '2015-09-01', // Девять лет назад
            11 => '2014-09-01', // Десять лет назад
        ];

        return $enrollmentDates[$year] ?? '2024-09-01';
    }
}
