<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\SchoolClass;

class SchoolSeeder extends Seeder
{
    /**
     * Создание школьных классов для электронного дневника
     *
     * Создает тестовые классы с 1 по 11 год обучения:
     * - Начальная школа: 1А, 1Б, 2А, 2Б, 3А, 3Б, 4А
     * - Средняя школа: 5А, 5Б, 6А, 6Б, 7А, 7Б, 8А, 8Б, 9А, 9Б
     * - Старшая школа: 10А, 11А
     *
     * Академические годы: 2024-2025, 2025-2026
     */
    public function run()
    {
        $this->command->info('Начинаем создание школьных классов...');

        // Создаем классы по годам обучения
        $this->createClasses();

        $this->command->info('Создание классов завершено!');
    }

    private function createClasses()
    {
        $classesData = [
            // Начальная школа (1-4 классы)
            ['year' => 1, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 1, 'letter' => 'Б', 'academic_year' => '2024-2025'],
            ['year' => 2, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 2, 'letter' => 'Б', 'academic_year' => '2024-2025'],
            ['year' => 3, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 3, 'letter' => 'Б', 'academic_year' => '2024-2025'],
            ['year' => 4, 'letter' => 'А', 'academic_year' => '2024-2025'],

            // Средняя школа (5-9 классы)
            ['year' => 5, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 5, 'letter' => 'Б', 'academic_year' => '2024-2025'],
            ['year' => 6, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 6, 'letter' => 'Б', 'academic_year' => '2024-2025'],
            ['year' => 7, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 7, 'letter' => 'Б', 'academic_year' => '2024-2025'],
            ['year' => 8, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 8, 'letter' => 'Б', 'academic_year' => '2024-2025'],
            ['year' => 9, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 9, 'letter' => 'Б', 'academic_year' => '2024-2025'],

            // Старшая школа (10-11 классы)
            ['year' => 10, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 10, 'letter' => 'Б', 'academic_year' => '2024-2025'],
            ['year' => 11, 'letter' => 'А', 'academic_year' => '2024-2025'],
            ['year' => 11, 'letter' => 'Б', 'academic_year' => '2024-2025'],
        ];

        foreach ($classesData as $classData) {
            $className = "{$classData['year']}{$classData['letter']}";

            SchoolClass::updateOrCreate(
                [
                    'name' => $className,
                    'year' => $classData['year'],
                    'letter' => $classData['letter'],
                    'academic_year' => $classData['academic_year']
                ],
                [
                    'description' => $this->getClassDescription($classData['year']),
                    'max_students' => $this->getMaxStudents($classData['year']),
                    'is_active' => true
                ]
            );
        }

        $this->command->info('Классы успешно созданы:');
        $this->command->info('Начальная школа (1-4 классы): 7 классов');
        $this->command->info('Средняя школа (5-9 классы): 10 классов');
        $this->command->info('Старшая школа (10-11 классы): 4 класса');
        $this->command->info('Всего создано: ' . count($classesData) . ' классов');
    }

    /**
     * Получить описание класса по году обучения
     */
    private function getClassDescription($year)
    {
        $descriptions = [
            1 => '1 класс - адаптационный период, изучение основ',
            2 => '2 класс - углубленное изучение базовых предметов',
            3 => '3 класс - развитие навыков самостоятельной работы',
            4 => '4 класс - подготовка к переходу в среднюю школу',
            5 => '5 класс - адаптация к средней школе, изучение новых предметов',
            6 => '6 класс - углубленное изучение предметов',
            7 => '7 класс - развитие аналитического мышления',
            8 => '8 класс - подготовка к выбору профиля',
            9 => '9 класс - подготовка к государственной итоговой аттестации',
            10 => '10 класс - углубленное изучение профильных предметов',
            11 => '11 класс - подготовка к единому государственному экзамену'
        ];

        return $descriptions[$year] ?? "{$year} класс средней школы";
    }

    /**
     * Получить максимальное количество учеников по году обучения
     */
    private function getMaxStudents($year)
    {
        // В начальной школе меньше учеников в классе
        if ($year <= 4) {
            return 25;
        }

        // В средней и старшей школе больше учеников
        return 30;
    }
}
