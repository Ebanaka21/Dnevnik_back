<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Создание основных школьных предметов для электронного дневника
     *
     * Предметы с указанием часов в неделю согласно учебному плану:
     * - Математика: 5 часов (основной предмет)
     * - Русский язык: 4 часа (основной предмет)
     * - Литература: 3 часа (основной предмет)
     * - История: 2 часа (гуманитарные науки)
     * - Обществознание: 2 часа (гуманитарные науки)
     * - Физика: 3 часа (естественные науки)
     * - Химия: 2 часа (естественные науки)
     * - Биология: 2 часа (естественные науки)
     * - География: 2 часа (естественные науки)
     * - Английский язык: 3 часа (иностранный язык)
     * - Информатика: 2 часа (современные технологии)
     * - Физкультура: 3 часа (физическое развитие)
     * - ОБЖ: 1 час (безопасность жизнедеятельности)
     * - Музыка: 1 час (эстетическое развитие)
     * - ИЗО: 1 час (изобразительное искусство)
     */
    public function run(): void
    {
        $subjects = [
            // Основные предметы
            [
                'name' => 'Математика',
                'short_name' => 'МАТ',
                'description' => 'Алгебра и геометрия - основной предмет математического цикла',
                'hours_per_week' => 5,
                'subject_code' => 'MATH',
                'is_active' => true,
            ],
            [
                'name' => 'Русский язык',
                'short_name' => 'РУС',
                'description' => 'Изучение русского языка, грамматики и орфографии',
                'hours_per_week' => 4,
                'subject_code' => 'RUS',
                'is_active' => true,
            ],
            [
                'name' => 'Литература',
                'short_name' => 'ЛИТ',
                'description' => 'Изучение русской и зарубежной литературы',
                'hours_per_week' => 3,
                'subject_code' => 'LIT',
                'is_active' => true,
            ],

            // Гуманитарные науки
            [
                'name' => 'История',
                'short_name' => 'ИСТ',
                'description' => 'История России и всеобщая история',
                'hours_per_week' => 2,
                'subject_code' => 'HIST',
                'is_active' => true,
            ],
            [
                'name' => 'Обществознание',
                'short_name' => 'ОБЩ',
                'description' => 'Изучение общества, права и экономики',
                'hours_per_week' => 2,
                'subject_code' => 'SOC',
                'is_active' => true,
            ],

            // Естественные науки
            [
                'name' => 'Физика',
                'short_name' => 'ФИЗ',
                'description' => 'Основы физики и астрономии',
                'hours_per_week' => 3,
                'subject_code' => 'PHYS',
                'is_active' => true,
            ],
            [
                'name' => 'Химия',
                'short_name' => 'ХИМ',
                'description' => 'Основы химии и химических процессов',
                'hours_per_week' => 2,
                'subject_code' => 'CHEM',
                'is_active' => true,
            ],
            [
                'name' => 'Биология',
                'short_name' => 'БИО',
                'description' => 'Изучение живой природы и организмов',
                'hours_per_week' => 2,
                'subject_code' => 'BIO',
                'is_active' => true,
            ],
            [
                'name' => 'География',
                'short_name' => 'ГЕО',
                'description' => 'Физическая и экономическая география',
                'hours_per_week' => 2,
                'subject_code' => 'GEO',
                'is_active' => true,
            ],

            // Иностранные языки
            [
                'name' => 'Английский язык',
                'short_name' => 'АНГ',
                'description' => 'Изучение английского языка как основного иностранного языка',
                'hours_per_week' => 3,
                'subject_code' => 'ENG',
                'is_active' => true,
            ],

            // Современные технологии
            [
                'name' => 'Информатика',
                'short_name' => 'ИНФ',
                'description' => 'Информатика и информационно-коммуникационные технологии',
                'hours_per_week' => 2,
                'subject_code' => 'IT',
                'is_active' => true,
            ],

            // Физическое развитие
            [
                'name' => 'Физкультура',
                'short_name' => 'ФИЗК',
                'description' => 'Физическая культура и спорт',
                'hours_per_week' => 3,
                'subject_code' => 'PE',
                'is_active' => true,
            ],

            // Безопасность жизнедеятельности
            [
                'name' => 'ОБЖ',
                'short_name' => 'ОБЖ',
                'description' => 'Основы безопасности жизнедеятельности',
                'hours_per_week' => 1,
                'subject_code' => 'OBZH',
                'is_active' => true,
            ],

            // Эстетическое развитие
            [
                'name' => 'Музыка',
                'short_name' => 'МУЗ',
                'description' => 'Музыкальное образование и развитие',
                'hours_per_week' => 1,
                'subject_code' => 'MUSIC',
                'is_active' => true,
            ],
            [
                'name' => 'Изобразительное искусство',
                'short_name' => 'ИЗО',
                'description' => 'Изобразительное искусство и художественное творчество',
                'hours_per_week' => 1,
                'subject_code' => 'ART',
                'is_active' => true,
            ],
        ];

        foreach ($subjects as $subject) {
            \App\Models\Subject::updateOrCreate(
                ['name' => $subject['name']],
                $subject
            );
        }

        $this->command->info('Предметы успешно созданы:');
        $this->command->info('Основные предметы: Математика (5ч), Русский язык (4ч), Литература (3ч)');
        $this->command->info('Гуманитарные: История (2ч), Обществознание (2ч)');
        $this->command->info('Естественные: Физика (3ч), Химия (2ч), Биология (2ч), География (2ч)');
        $this->command->info('Иностранные: Английский язык (3ч)');
        $this->command->info('Технологии: Информатика (2ч)');
        $this->command->info('Развитие: Физкультура (3ч), ОБЖ (1ч), Музыка (1ч), ИЗО (1ч)');
    }
}
