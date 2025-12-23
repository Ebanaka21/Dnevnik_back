<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Subject;
use App\Models\GradeType;
use App\Models\SchoolClass;
use App\Models\StudentClass;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GradeSeeder extends Seeder
{
    /**
     * Создание реалистичных оценок за последние 6 месяцев
     *
     * Создает оценки для всех учеников по всем предметам с разными типами
     * оценок и реалистичными значениями.
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание оценок...');

        // Получаем все предметы
        $subjects = Subject::where('is_active', true)->get();

        // Получаем все типы оценок
        $gradeTypes = GradeType::all();

        // Получаем всех учеников с их классами
        $students = User::where('role', 'student')->with(['studentClassRelationships' => function ($query) {
            $query->where('academic_year', '2024-2025')
                  ->where('is_active', true);
        }])->get();

        $gradesCreated = 0;

        foreach ($students as $student) {
            foreach ($student->studentClassRelationships as $studentClass) {
                $class = $studentClass->schoolClass;
                if (!$class) continue;

                // Для каждого предмета создаем оценки
                foreach ($subjects as $subject) {
                    // Определяем, какие оценки должен иметь ученик по этому предмету
                    $subjectGrades = $this->generateStudentGrades($student, $subject, $class, $gradeTypes);

                    foreach ($subjectGrades as $gradeData) {
                        \App\Models\Grade::create($gradeData);
                        $gradesCreated++;
                    }
                }
            }
        }

        $this->command->info("Создано оценок: {$gradesCreated}");
        $this->command->info('Создание оценок завершено!');
    }

    private function generateStudentGrades($student, $subject, $class, $gradeTypes)
    {
        $grades = [];
        $currentDate = Carbon::now();
        $startDate = $currentDate->copy()->subMonths(6);

        // Генерируем оценки за период с сентября 2024 по ноябрь 2024 (3 месяца)
        $periodStart = Carbon::createFromDate(2024, 9, 1);
        $periodEnd = Carbon::createFromDate(2024, 11, 30);

        // Создаем различные типы оценок с реалистичным распределением
        $gradeDistribution = $this->getGradeDistribution($student, $subject, $class);

        foreach ($gradeDistribution as $gradeInfo) {
            $gradeType = $gradeTypes->where('name', $gradeInfo['type'])->first();
            if (!$gradeType) continue;

            // Генерируем дату в пределах периода
            $gradeDate = Carbon::createFromTimestamp(
                rand($periodStart->timestamp, $periodEnd->timestamp)
            );

            $grades[] = [
                'student_id' => $student->id,
                'subject_id' => $subject->id,
                'grade_type_id' => $gradeType->id,
                'teacher_id' => $this->getSubjectTeacher($subject, $class) ?? 1, // fallback к первому учителю
                'value' => $gradeInfo['value'],
                'description' => $gradeInfo['description'],
                'date' => $gradeDate->format('Y-m-d'),
                'comment' => $gradeInfo['comment'] ?? null,
                'is_final' => $gradeInfo['is_final'] ?? false,
                'created_at' => $gradeDate,
                'updated_at' => $gradeDate,
            ];
        }

        return $grades;
    }

    private function getGradeDistribution($student, $subject, $class)
    {
        // Анализируем успеваемость ученика для реалистичных оценок
        $studentPerformance = $this->getStudentPerformanceLevel($student);

        $grades = [];

        // Контрольные работы (1 в месяц)
        $controlWorkCount = rand(2, 4);
        for ($i = 0; $i < $controlWorkCount; $i++) {
            $grades[] = [
                'type' => 'Контрольная работа',
                'value' => $this->generateRealisticGrade($studentPerformance, 'high'),
                'description' => 'Контрольная работа по теме: ' . $this->getRandomTopic($subject),
                'comment' => $this->generateGradeComment($studentPerformance),
            ];
        }

        // Самостоятельные работы (1-2 в месяц)
        $selfWorkCount = rand(3, 6);
        for ($i = 0; $i < $selfWorkCount; $i++) {
            $grades[] = [
                'type' => 'Самостоятельная работа',
                'value' => $this->generateRealisticGrade($studentPerformance, 'medium'),
                'description' => 'Самостоятельная работа на уроке',
                'comment' => null,
            ];
        }

        // Домашние работы (регулярно)
        $homeworkCount = rand(6, 12);
        for ($i = 0; $i < $homeworkCount; $i++) {
            $grades[] = [
                'type' => 'Домашняя работа',
                'value' => $this->generateRealisticGrade($studentPerformance, 'low'),
                'description' => 'Домашнее задание: ' . $this->getRandomTopic($subject),
                'comment' => rand(0, 3) == 0 ? $this->generateHomeworkComment() : null,
            ];
        }

        // Ответы на уроке (часто)
        $lessonAnswersCount = rand(8, 15);
        for ($i = 0; $i < $lessonAnswersCount; $i++) {
            $grades[] = [
                'type' => 'Ответ на уроке',
                'value' => $this->generateRealisticGrade($studentPerformance, 'medium'),
                'description' => 'Устный ответ на уроке',
                'comment' => null,
            ];
        }

        // Лабораторные работы (для соответствующих предметов)
        if (in_array($subject->name, ['Физика', 'Химия', 'Биология'])) {
            $labWorkCount = rand(3, 6);
            for ($i = 0; $i < $labWorkCount; $i++) {
                $grades[] = [
                    'type' => 'Лабораторная работа',
                    'value' => $this->generateRealisticGrade($studentPerformance, 'medium'),
                    'description' => 'Лабораторная работа №' . ($i + 1),
                    'comment' => rand(0, 2) == 0 ? $this->generateLabComment() : null,
                ];
            }
        }

        // Итоговые оценки за четверть
        if ($class->year >= 5) {
            $grades[] = [
                'type' => 'Контрольная работа',
                'value' => $this->generateRealisticGrade($studentPerformance, 'high'),
                'description' => 'Итоговая оценка за четверть',
                'comment' => 'Итоговая оценка',
                'is_final' => true,
            ];
        }

        return $grades;
    }

    private function getStudentPerformanceLevel($student)
    {
        // Определяем уровень успеваемости ученика на основе его имени/фамилии
        // Это простая эвристика для демонстрационных данных
        $lastChar = strtolower(substr($student->surname, -1));

        if (in_array($lastChar, ['а', 'о', 'е', 'и'])) {
            return 'high'; // Отличники
        } elseif (in_array($lastChar, ['н', 'р', 'т'])) {
            return 'medium'; // Хорошисты
        } else {
            return 'low'; // Троечники
        }
    }

    private function generateRealisticGrade($performance, $gradeType = 'medium')
    {
        // Матрица оценок в зависимости от успеваемости и типа работы
        $gradeMatrix = [
            'high' => [
                'high' => [4, 4, 4, 5, 5, 5],      // Контрольные - чаще 4-5
                'medium' => [3, 4, 4, 4, 5, 5],    // Средние работы
                'low' => [4, 4, 5, 5, 5, 5]        // Простые работы - чаще 4-5
            ],
            'medium' => [
                'high' => [3, 3, 4, 4, 4, 5],      // Контрольные - 3-4
                'medium' => [3, 4, 4, 4, 5],       // Средние работы
                'low' => [3, 4, 4, 4, 5]           // Простые работы
            ],
            'low' => [
                'high' => [2, 3, 3, 3, 4, 4],      // Контрольные - чаще 2-3
                'medium' => [2, 3, 3, 4],          // Средние работы
                'low' => [3, 3, 4]                 // Простые работы
            ]
        ];

        $grades = $gradeMatrix[$performance][$gradeType] ?? [3, 4];
        return $grades[array_rand($grades)];
    }

    private function getSubjectTeacher($subject, $class)
    {
        // Находим учителя для данного предмета и класса
        $teacherClass = DB::table('teacher_classes')
            ->join('users', 'teacher_classes.teacher_id', '=', 'users.id')
            ->where('teacher_classes.subject_id', $subject->id)
            ->where('teacher_classes.school_class_id', $class->id)
            ->where('teacher_classes.academic_year', '2024-2025')
            ->where('teacher_classes.is_active', true)
            ->value('teacher_classes.teacher_id');

        return $teacherClass;
    }

    private function getRandomTopic($subject)
    {
        $topics = [
            'Математика' => [
                'Алгебраические уравнения',
                'Геометрические фигуры',
                'Функции и графики',
                'Системы уравнений',
                'Неравенства'
            ],
            'Русский язык' => [
                'Морфология',
                'Синтаксис',
                'Орфография',
                'Пунктуация',
                'Лексика'
            ],
            'Литература' => [
                'Анализ произведения',
                'Характеристика героя',
                'Тема и идея произведения',
                'Стихосложение',
                'Литературные жанры'
            ],
            'История' => [
                'Древняя история',
                'Средневековье',
                'Новая история',
                'Новейшая история',
                'Исторические события'
            ],
            'Физика' => [
                'Механика',
                'Термодинамика',
                'Электричество',
                'Магнетизм',
                'Оптика'
            ],
            'Химия' => [
                'Неорганическая химия',
                'Органическая химия',
                'Химические реакции',
                'Периодическая система',
                'Химические связи'
            ],
            'Биология' => [
                'Ботаника',
                'Зоология',
                'Анатомия человека',
                'Генетика',
                'Экология'
            ]
        ];

        $subjectTopics = $topics[$subject->name] ?? ['Основы предмета'];
        return $subjectTopics[array_rand($subjectTopics)];
    }

    private function generateGradeComment($performance)
    {
        $comments = [
            'high' => [
                'Отличная работа!',
                'Превосходный результат!',
                'Так держать!',
                'Показал глубокие знания!',
                'Активно участвовал в обсуждении!'
            ],
            'medium' => [
                'Хорошая работа!',
                'Правильно понял материал',
                'Молодец!',
                'Работай еще усерднее',
                'Есть прогресс!'
            ],
            'low' => [
                'Нужно повторить материал',
                'Обрати внимание на ошибки',
                'Старайся больше',
                'Необходимо дополнительное изучение',
                'Следующий раз будет лучше'
            ]
        ];

        $performanceComments = $comments[$performance] ?? $comments['medium'];
        return $performanceComments[array_rand($performanceComments)];
    }

    private function generateHomeworkComment()
    {
        $comments = [
            'Домашнее задание выполнено аккуратно',
            'Хорошо оформлено',
            'Необходимо переделать задание 3',
            'Отлично разобрался в теме',
            'Есть несколько недочетов',
            'Творческий подход к заданию'
        ];

        return $comments[array_rand($comments)];
    }

    private function generateLabComment()
    {
        $comments = [
            'Точно провел эксперимент',
            'Правильно оформил результаты',
            'Хорошо проанализировал данные',
            'Нужно улучшить технику проведения опыта',
            'Отличная работа в лаборатории!',
            'Показал понимание физических законов'
        ];

        return $comments[array_rand($comments)];
    }
}
