<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\HomeworkSubmission;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HomeworkSeeder extends Seeder
{
    /**
     * Создание домашних заданий и их выполнения
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание домашних заданий...');

        $subjects = Subject::where('is_active', true)->get();
        $classes = SchoolClass::where('academic_year', '2024-2025')
                             ->where('is_active', true)
                             ->get();

        $homeworkCount = 0;
        $submissionCount = 0;

        foreach ($subjects as $subject) {
            foreach ($classes as $class) {
                // Проверяем, ведет ли какой-то учитель этот предмет в этом классе
                $teacherId = $this->getSubjectTeacher($subject, $class);
                if (!$teacherId) continue;

                // Создаем домашние задания для этого предмета и класса
                $homeworks = $this->generateHomeworks($subject, $class, $teacherId);

                foreach ($homeworks as $homeworkData) {
                    $homework = \App\Models\Homework::create($homeworkData);
                    $homeworkCount++;

                    // Создаем сдачу домашних заданий учениками
                    $submissions = $this->generateHomeworkSubmissions($homework, $class);
                    foreach ($submissions as $submissionData) {
                        HomeworkSubmission::updateOrCreate(
                            [
                                'homework_id' => $homework->id,
                                'student_id' => $submissionData['student_id']
                            ],
                            $submissionData
                        );
                        $submissionCount++;
                    }
                }
            }
        }

        $this->command->info("Создано домашних заданий: {$homeworkCount}");
        $this->command->info("Создано сдач заданий: {$submissionCount}");
        $this->command->info('Создание домашних заданий завершено!');
    }

    private function generateHomeworks($subject, $class, $teacherId)
    {
        $homeworks = [];
        $currentDate = Carbon::now();
        $startDate = $currentDate->copy()->subMonths(4); // За последние 4 месяца

        // Генерируем 6-12 заданий на предмет для класса
        $homeworkCount = mt_rand(6, 12);

        for ($i = 0; $i < $homeworkCount; $i++) {
            $assignedDate = $startDate->copy()->addWeeks($i * 2 + mt_rand(0, 7));
            $dueDate = $assignedDate->copy()->addDays(mt_rand(3, 7));

            $homeworks[] = [
                'subject_id' => $subject->id,
                'school_class_id' => $class->id,
                'teacher_id' => $teacherId,
                'title' => $this->generateHomeworkTitle($subject, $i),
                'description' => $this->generateHomeworkDescription($subject, $i),
                'assigned_date' => $assignedDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'max_points' => mt_rand(10, 100),
                'is_active' => true,
                'created_at' => $assignedDate,
                'updated_at' => $assignedDate,
            ];
        }

        return $homeworks;
    }

    private function generateHomeworkTitle($subject, $index)
    {
        $titles = [
            'Математика' => [
                'Решение квадратных уравнений',
                'Задачи на прогрессии',
                'Геометрические задачи',
                'Системы уравнений',
                'Функции и их графики',
                'Неравенства',
                'Тригонометрические уравнения',
                'Производные и интегралы'
            ],
            'Русский язык' => [
                'Морфологический разбор слов',
                'Синтаксический разбор предложений',
                'Орфографические упражнения',
                'Пунктуационные задачи',
                'Лексический анализ текста',
                'Сочинение-рассуждение',
                'Анализ художественного текста',
                'Стилистический анализ'
            ],
            'Литература' => [
                'Анализ стихотворения',
                'Характеристика героя',
                'Анализ главы романа',
                'Сравнительная характеристика',
                'Анализ конфликта произведения',
                'Тема и идея произведения',
                'Анализ художественных средств',
                'Эссе по произведению'
            ],
            'История' => [
                'Характеристика исторического периода',
                'Анализ исторического события',
                'Сравнение исторических эпох',
                'Биография исторического деятеля',
                'Причины и последствия события',
                'Характеристика общественного строя',
                'Анализ исторического источника',
                'Исторические параллели'
            ],
            'Физика' => [
                'Задачи на механику',
                'Экспериментальная работа',
                'Расчет электрических цепей',
                'Изучение тепловых процессов',
                'Задачи на оптику',
                'Исследование колебаний',
                'Анализ физических явлений',
                'Лабораторная работа'
            ],
            'Химия' => [
                'Составление химических уравнений',
                'Задачи на концентрацию растворов',
                'Исследование свойств веществ',
                'Анализ химических реакций',
                'Лабораторная работа',
                'Синтез органических соединений',
                'Качественный анализ',
                'Количественные расчеты'
            ],
            'Биология' => [
                'Изучение строения клетки',
                'Анализ жизненных процессов',
                'Исследование растений',
                'Изучение животных',
                'Анализ экологических систем',
                'Генетические задачи',
                'Лабораторная работа',
                'Наблюдение за природой'
            ]
        ];

        $subjectTitles = $titles[$subject->name] ?? [
            'Домашнее задание №' . ($index + 1),
            'Самостоятельная работа',
            'Творческое задание',
            'Практическая работа'
        ];

        return $subjectTitles[array_rand($subjectTitles)];
    }

    private function generateHomeworkDescription($subject, $index)
    {
        $descriptions = [
            'Математика' => [
                'Решите уравнения и неравенства. Оформите решение подробно.',
                'Решите задачи на применение формул прогрессий.',
                'Выполните геометрические построения и расчеты.',
                'Решите систему уравнений различными методами.',
                'Постройте графики функций и найдите их свойства.'
            ],
            'Русский язык' => [
                'Выполните упражнения на закрепление изученных правил.',
                'Проанализируйте текст, найдите средства выразительности.',
                'Напишите сочинение на предложенную тему.',
                'Выполните морфологический разбор указанных слов.',
                'Составьте схемы предложений с различными типами связи.'
            ],
            'Литература' => [
                'Проанализируйте стихотворение по плану.',
                'Напишите характеристику главного героя произведения.',
                'Определите тему и идею произведения.',
                'Проанализируйте художественные средства языка.',
                'Сравните образы героев в произведении.'
            ],
            'История' => [
                'Составьте характеристику исторического периода.',
                'Проанализируйте причины и последствия события.',
                'Подготовьте сообщение о историческом деятеле.',
                'Сравните различные исторические эпохи.',
                'Проанализируйте исторический источник.'
            ],
            'Физика' => [
                'Решите задачи на применение законов механики.',
                'Проведите эксперимент и оформите отчет.',
                'Рассчитайте параметры электрических цепей.',
                'Изучите тепловые процессы и решите задачи.',
                'Постройте графики физических зависимостей.'
            ],
            'Химия' => [
                'Составьте уравнения химических реакций.',
                'Решите задачи на концентрацию растворов.',
                'Проведите качественный анализ веществ.',
                'Изучите свойства органических соединений.',
                'Выполните расчеты по химическим формулам.'
            ],
            'Биология' => [
                'Изучите строение клетки под микроскопом.',
                'Проанализируйте процессы жизнедеятельности.',
                'Сравните растения разных видов.',
                'Исследуйте экологические взаимосвязи.',
                'Решите генетические задачи.'
            ]
        ];

        $subjectDescriptions = $descriptions[$subject->name] ?? [
            'Выполните задания по учебнику на странице ' . (20 + $index * 10),
            'Подготовьте ответы на контрольные вопросы',
            'Выполните практические задания',
            'Изучите дополнительный материал по теме'
        ];

        return $subjectDescriptions[array_rand($subjectDescriptions)];
    }

    private function generateHomeworkSubmissions($homework, $class)
    {
        $submissions = [];
        $students = $class->students()->where('role', 'student')->get();

        foreach ($students as $student) {
            $submission = $this->generateStudentSubmission($student, $homework);
            if ($submission) {
                $submissions[] = $submission;
            }
        }

        return $submissions;
    }

    private function generateStudentSubmission($student, $homework)
    {
        // Определяем вероятность сдачи задания в зависимости от успеваемости
        $submissionRate = $this->getStudentSubmissionRate($student);

        // 70-90% учеников сдают домашнее задание
        if (mt_rand(1, 100) > ($submissionRate * 100)) {
            return null; // Ученик не сдал задание
        }

        $dueDate = Carbon::parse($homework->due_date);
        $isLate = mt_rand(1, 100) <= 20; // 20% сдают с опозданием
        $submittedAt = $dueDate->copy();

        if ($isLate) {
            $submittedAt->addDays(mt_rand(1, 3));
        } else {
            // Сдают за 1-2 дня до дедлайна
            $submittedAt->subDays(mt_rand(1, 2));
        }

        // Генерируем баллы (большинство получают хорошие оценки)
        $pointsEarned = $this->generateSubmissionPoints($student, $homework);

        // Генерируем комментарий учителя (иногда)
        $teacherComment = null;
        if (mt_rand(1, 100) <= 30) { // 30% получают комментарий
            $teacherComment = $this->generateTeacherComment($pointsEarned, $homework->max_points);
        }

        return [
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'content' => $this->generateSubmissionContent($homework->subject, $student),
            'file_path' => null, // В демо-данных файлов нет
            'submitted_at' => $submittedAt,
            'points_earned' => $pointsEarned,
            'teacher_comment' => $teacherComment,
            'reviewed_at' => $submittedAt->copy()->addDays(mt_rand(1, 2)),
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ];
    }

    private function getStudentSubmissionRate($student)
    {
        // Простая эвристика на основе фамилии
        $lastChar = strtolower(substr($student->surname, -1));

        if (in_array($lastChar, ['а', 'о', 'е', 'и'])) {
            return 0.95; // 95% сдают
        } elseif (in_array($lastChar, ['н', 'р', 'т'])) {
            return 0.85; // 85% сдают
        } elseif (in_array($lastChar, ['л', 'д', 'с'])) {
            return 0.75; // 75% сдают
        } else {
            return 0.65; // 65% сдают
        }
    }

    private function generateSubmissionPoints($student, $homework)
    {
        $maxPoints = $homework->max_points;

        // Большинство получают хорошие оценки
        $gradeDistribution = [
            0.1 => $maxPoints * 0.6,  // 10% получают 60% от максимума
            0.2 => $maxPoints * 0.7,  // 20% получают 70% от максимума
            0.4 => $maxPoints * 0.85, // 40% получают 85% от максимума
            0.2 => $maxPoints * 0.95, // 20% получают 95% от максимума
            0.1 => $maxPoints,         // 10% получают максимум
        ];

        $rand = mt_rand(1, 100) / 100;
        $cumulative = 0;

        foreach ($gradeDistribution as $probability => $points) {
            $cumulative += $probability;
            if ($rand <= $cumulative) {
                return (int)round($points);
            }
        }

        return (int)round($maxPoints * 0.85);
    }

    private function generateTeacherComment($points, $maxPoints)
    {
        $percentage = ($points / $maxPoints) * 100;

        $excellentComments = [
            'Отличная работа!',
            'Превосходно!',
            'Так держать!',
            'Показал глубокое понимание темы',
            'Творческий подход к заданию'
        ];

        $goodComments = [
            'Хорошая работа!',
            'Правильно понял материал',
            'Старательно выполнено',
            'Есть небольшие недочеты',
            'Молодец!'
        ];

        $averageComments = [
            'Работа выполнена, но есть ошибки',
            'Нужно повторить материал',
            'Обрати внимание на замечания',
            'Старайся больше',
            'Следующий раз будет лучше'
        ];

        $poorComments = [
            'Работа не завершена',
            'Много ошибок в решении',
            'Нужно переделать задание',
            'Недостаточно проработан материал',
            'Повтори тему еще раз'
        ];

        if ($percentage >= 90) {
            return $excellentComments[array_rand($excellentComments)];
        } elseif ($percentage >= 75) {
            return $goodComments[array_rand($goodComments)];
        } elseif ($percentage >= 60) {
            return $averageComments[array_rand($averageComments)];
        } else {
            return $poorComments[array_rand($poorComments)];
        }
    }

    private function generateSubmissionContent($subject, $student)
    {
        $contents = [
            'Выполнил все задания согласно условию. Подготовил ответы на все вопросы.',
            'Решил задачи и оформил решение подробно. Приложил необходимые расчеты.',
            'Выполнил задание полностью. Показал хорошее понимание материала.',
            'Ответил на все вопросы и решил практические задачи.',
            'Подготовил развернутые ответы с примерами.'
        ];

        return $contents[array_rand($contents)];
    }

    private function getSubjectTeacher($subject, $class)
    {
        $teacherId = DB::table('teacher_classes')
            ->join('users', 'teacher_classes.teacher_id', '=', 'users.id')
            ->where('teacher_classes.subject_id', $subject->id)
            ->where('teacher_classes.school_class_id', $class->id)
            ->where('teacher_classes.academic_year', '2024-2025')
            ->where('teacher_classes.is_active', true)
            ->value('teacher_classes.teacher_id');

        return $teacherId;
    }
}
