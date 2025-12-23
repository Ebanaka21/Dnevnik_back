<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AcademicYear;
use App\Models\AcademicWeek;
use App\Models\CurriculumPlan;
use App\Models\ThematicBlock;
use Carbon\Carbon;

class AcademicYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Создаем учебный год 2025-2026
        $academicYear = AcademicYear::create([
            'year_name' => '2025-2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-05-31',
            'total_weeks' => 34,
            'is_active' => true,
            'description' => 'Основной учебный год 2025-2026'
        ]);

        // Создаем недели для учебного года
        $this->createAcademicWeeks($academicYear);

        // Создаем тематические блоки для учебных планов
        $this->createThematicBlocksForPlans();
    }

    private function createAcademicWeeks(AcademicYear $academicYear)
    {
        $startDate = Carbon::parse($academicYear->start_date);
        $weeks = [];

        for ($weekNumber = 1; $weekNumber <= $academicYear->total_weeks; $weekNumber++) {
            $weekStart = $startDate->copy()->addWeeks($weekNumber - 1)->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

            // Определяем тип недели
            $weekType = 'regular';
            $isHoliday = false;

            // Зимние каникулы (декабрь-январь)
            if ($weekNumber >= 16 && $weekNumber <= 18) {
                $weekType = 'holiday';
                $isHoliday = true;
            }

            // Весенние каникулы (март)
            if ($weekNumber >= 26 && $weekNumber <= 27) {
                $weekType = 'holiday';
                $isHoliday = true;
            }

            $weeks[] = [
                'academic_year_id' => $academicYear->id,
                'week_number' => $weekNumber,
                'start_date' => $weekStart->toDateString(),
                'end_date' => $weekEnd->toDateString(),
                'is_holiday' => $isHoliday,
                'week_type' => $weekType,
                'notes' => $isHoliday ? 'Каникулы' : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        AcademicWeek::insert($weeks);
    }

    private function createThematicBlocksForPlans()
    {
        $curriculumPlans = CurriculumPlan::where('academic_year', '2025-2026')->get();

        foreach ($curriculumPlans as $plan) {
            $this->createThematicBlocksForPlan($plan);
        }
    }

    private function createThematicBlocksForPlan(CurriculumPlan $plan)
    {
        $subjectName = $plan->subject->name ?? 'Unknown';

        // Тематические блоки для разных предметов
        $thematicBlocks = $this->getThematicBlocksForSubject($subjectName);

        foreach ($thematicBlocks as $index => $block) {
            ThematicBlock::create([
                'curriculum_plan_id' => $plan->id,
                'title' => $block['title'],
                'description' => $block['description'],
                'weeks_count' => $block['weeks_count'],
                'order' => $index + 1,
                'learning_objectives' => $block['objectives'],
                'required_materials' => $block['materials'],
            ]);
        }
    }

    private function getThematicBlocksForSubject(string $subjectName): array
    {
        $blocks = [
            'Математика' => [
                [
                    'title' => 'Повторение основных понятий',
                    'description' => 'Закрепление базовых математических понятий и операций',
                    'weeks_count' => 2,
                    'objectives' => ['Вспомнить основные математические операции', 'Закрепить навыки решения простых задач'],
                    'materials' => ['Учебник математики', 'Тетрадь для упражнений']
                ],
                [
                    'title' => 'Квадратные уравнения',
                    'description' => 'Изучение квадратных уравнений и методов их решения',
                    'weeks_count' => 4,
                    'objectives' => ['Научиться решать квадратные уравнения', 'Освоить теорему Виета'],
                    'materials' => ['Учебник математики', 'Графическая бумага']
                ],
                [
                    'title' => 'Функции и графики',
                    'description' => 'Изучение свойств функций и построение графиков',
                    'weeks_count' => 5,
                    'objectives' => ['Изучить свойства функций', 'Научиться строить графики'],
                    'materials' => ['Учебник математики', 'Графическая бумага', 'Линейка']
                ],
                [
                    'title' => 'Геометрия: подобие фигур',
                    'description' => 'Изучение подобия треугольников и других фигур',
                    'weeks_count' => 4,
                    'objectives' => ['Освоить понятие подобия', 'Научиться применять теорему Пифагора'],
                    'materials' => ['Учебник геометрии', 'Циркуль', 'Транспортир']
                ],
                [
                    'title' => 'Итоговое повторение',
                    'description' => 'Комплексное повторение изученного материала',
                    'weeks_count' => 2,
                    'objectives' => ['Закрепить все изученные темы', 'Подготовиться к контрольным работам'],
                    'materials' => ['Сборник задач', 'Контрольные работы']
                ]
            ],
            'Русский язык' => [
                [
                    'title' => 'Фонетика и орфоэпия',
                    'description' => 'Изучение звуков и букв русского языка',
                    'weeks_count' => 3,
                    'objectives' => ['Различать звуки и буквы', 'Правильно ставить ударение'],
                    'materials' => ['Учебник русского языка', 'Орфоэпический словарь']
                ],
                [
                    'title' => 'Морфология: имя существительное',
                    'description' => 'Изучение частей речи, начиная с имени существительного',
                    'weeks_count' => 4,
                    'objectives' => ['Классифицировать существительные', 'Образовывать формы слов'],
                    'materials' => ['Учебник русского языка', 'Карточки с заданиями']
                ],
                [
                    'title' => 'Синтаксис: простое предложение',
                    'description' => 'Изучение структуры простого предложения',
                    'weeks_count' => 5,
                    'objectives' => ['Анализировать предложения', 'Ставить знаки препинания'],
                    'materials' => ['Учебник русского языка', 'Схемы предложений']
                ],
                [
                    'title' => 'Литература: русская классика',
                    'description' => 'Изучение произведений русской литературы',
                    'weeks_count' => 6,
                    'objectives' => ['Анализировать литературные произведения', 'Развивать навыки чтения'],
                    'materials' => ['Хрестоматия', 'Книги русских писателей']
                ]
            ],
            'История' => [
                [
                    'title' => 'Древний мир',
                    'description' => 'История древних цивилизаций',
                    'weeks_count' => 4,
                    'objectives' => ['Изучить древние цивилизации', 'Понимать хронологию событий'],
                    'materials' => ['Учебник истории', 'Историческая карта']
                ],
                [
                    'title' => 'Средние века',
                    'description' => 'История средневековой Европы и мира',
                    'weeks_count' => 5,
                    'objectives' => ['Изучить феодализм', 'Понять развитие государств'],
                    'materials' => ['Учебник истории', 'Карты средневековья']
                ],
                [
                    'title' => 'Новое время',
                    'description' => 'История Нового времени',
                    'weeks_count' => 4,
                    'objectives' => ['Изучить эпоху Возрождения', 'Понять промышленную революцию'],
                    'materials' => ['Учебник истории', 'Иллюстрации эпохи']
                ]
            ],
            'Английский язык' => [
                [
                    'title' => 'Present Simple и Present Continuous',
                    'description' => 'Изучение времен Present Simple и Present Continuous',
                    'weeks_count' => 3,
                    'objectives' => ['Правильно использовать времена', 'Образовывать вопросы и отрицания'],
                    'materials' => ['Учебник английского', 'Аудиоматериалы']
                ],
                [
                    'title' => 'Past Simple и Past Continuous',
                    'description' => 'Изучение прошедших времен',
                    'weeks_count' => 4,
                    'objectives' => ['Рассказывать о прошлом', 'Различать времена'],
                    'materials' => ['Учебник английского', 'Видеоматериалы']
                ],
                [
                    'title' => 'Future tenses',
                    'description' => 'Изучение будущих времен',
                    'weeks_count' => 3,
                    'objectives' => ['Выражать будущие действия', 'Планировать события'],
                    'materials' => ['Учебник английского', 'Диалоги']
                ]
            ]
        ];

        return $blocks[$subjectName] ?? [
            [
                'title' => 'Основы предмета',
                'description' => 'Введение в основные понятия',
                'weeks_count' => 4,
                'objectives' => ['Освоить базовые понятия', 'Изучить основные принципы'],
                'materials' => ['Учебник', 'Рабочая тетрадь']
            ],
            [
                'title' => 'Практические занятия',
                'description' => 'Практическое применение знаний',
                'weeks_count' => 6,
                'objectives' => ['Применять знания на практике', 'Развивать навыки'],
                'materials' => ['Практические задания', 'Оборудование']
            ],
            [
                'title' => 'Контроль и повторение',
                'description' => 'Закрепление изученного материала',
                'weeks_count' => 2,
                'objectives' => ['Закрепить знания', 'Подготовиться к оценке'],
                'materials' => ['Тесты', 'Контрольные работы']
            ]
        ];
    }
}
