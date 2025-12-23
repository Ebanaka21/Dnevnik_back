<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Grade;
use App\Models\Homework;
use App\Models\TeacherComment;
use App\Models\HomeworkSubmission;
use Carbon\Carbon;

class TeacherCommentSeeder extends Seeder
{
    /**
     * Создание тестовых комментариев учителей для электронного дневника
     *
     * Назначение: Создание тестовых комментариев учителей
     *
     * Функции:
     * - Создать комментарии к оценкам
     * - Создать комментарии к домашним заданиям
     * - Настроить видимость для учеников и родителей
     * - Связать с существующими учителями, оценками и заданиями
     *
     * Типы комментариев:
     * - Поощрительные (для хороших оценок)
     * - Замечания (для плохих оценок)
     * - Методические (с рекомендациями)
     * - Организационные (по домашним заданиям)
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание комментариев учителей...');

        $teachers = User::where('role', 'teacher')->get();

        // Создаем комментарии к оценкам
        $this->createGradeComments($teachers);

        // Создаем комментарии к домашним заданиям
        $this->createHomeworkComments($teachers);

        // Создаем комментарии к сдачам домашних заданий
        $this->createHomeworkSubmissionComments($teachers);

        $totalComments = TeacherComment::count();
        $this->command->info("Создание комментариев завершено!");
        $this->command->info("Всего создано комментариев: {$totalComments}");
    }

    private function createGradeComments($teachers)
    {
        $positiveComments = [
            'Отличная работа! Продолжайте в том же духе.',
            'Превосходный результат, очень хорошо разобрали тему.',
            'Замечательно! Вы показали глубокое понимание материала.',
            'Хорошо выполнено! Особенно понравилось ваше решение.',
            'Отличная динамика! Заметен ваш прогресс в изучении предмета.',
            'Спасибо за старание! Ваша работа выполнена качественно.',
            'Молодец! Продолжайте изучать предмет с таким же интересом.',
            'Хорошая работа над ошибками, видно что вы старались.',
        ];

        $negativeComments = [
            'Нужно более внимательно изучить материал по этой теме.',
            'Требуется дополнительная работа над пониманием концепций.',
            'Обратите внимание на основные правила и определения.',
            'Рекомендую повторить пройденный материал и выполнить дополнительные упражнения.',
            'Есть ошибки в понимании основных принципов, нужно разобрать еще раз.',
            'Просьба переделать работу, обращая внимание на замечания.',
            'Нужно больше практики в решении подобных задач.',
            'Рекомендую обратиться за дополнительной помощью.',
        ];

        $neutralComments = [
            'Работа выполнена, но есть области для улучшения.',
            'Хорошая попытка, но нужно доработать некоторые моменты.',
            'Результат соответствует минимальным требованиям.',
            'Нужно уделить больше внимания деталям.',
            'Хорошая основа, но требуется дополнительная проработка.',
        ];

        // Создаем комментарии для существующих оценок
        $grades = Grade::with(['student', 'teacher', 'subject'])->limit(20)->get();

        foreach ($grades as $grade) {
            // Выбираем тип комментария в зависимости от оценки
            if ($grade->grade_value >= 4) {
                $comments = $positiveComments;
            } elseif ($grade->grade_value == 3) {
                $comments = $neutralComments;
            } else {
                $comments = $negativeComments;
            }

            // Случайно выбираем комментарий
            $commentText = $comments[array_rand($comments)];

            TeacherComment::create([
                'user_id' => $grade->teacher_id,
                'commentable_type' => Grade::class,
                'commentable_id' => $grade->id,
                'content' => $commentText,
                'is_visible_to_student' => true,
                'is_visible_to_parent' => true,
            ]);
        }

        // Создаем дополнительные комментарии если нет оценок
        if ($grades->isEmpty()) {
            $this->createSampleGradeComments($teachers, $positiveComments, $negativeComments, $neutralComments);
        }

        $this->command->info('Комментарии к оценкам созданы');
    }

    private function createSampleGradeComments($teachers, $positiveComments, $negativeComments, $neutralComments)
    {
        // Создаем тестовые комментарии для примера
        $sampleComments = [
            ['type' => 'positive', 'comments' => $positiveComments],
            ['type' => 'negative', 'comments' => $negativeComments],
            ['type' => 'neutral', 'comments' => $neutralComments],
        ];

        foreach ($sampleComments as $sample) {
            foreach ($teachers->take(3) as $teacher) {
                $commentText = $sample['comments'][array_rand($sample['comments'])];

                TeacherComment::create([
                    'user_id' => $teacher->id,
                    'commentable_type' => Grade::class,
                    'commentable_id' => 1, // Тестовый ID
                    'content' => $commentText,
                    'is_visible_to_student' => true,
                    'is_visible_to_parent' => true,
                ]);
            }
        }
    }

    private function createHomeworkComments($teachers)
    {
        $homeworkComments = [
            'Домашнее задание выполнено качественно, хорошо оформлено.',
            'Нужно переделать задания согласно методическим указаниям.',
            'Работа выполнена в срок, молодец!',
            'Обратите внимание на оформление работы.',
            'Есть ошибки в решении задач, рекомендую разобрать еще раз.',
            'Хорошая попытка, но нужно быть внимательнее к деталям.',
            'Отличная работа над домашним заданием!',
            'Требуется дополнительная проработка материала.',
        ];

        // Создаем комментарии для существующих домашних заданий
        $homeworks = Homework::with(['teacher', 'schoolClass', 'subject'])->limit(10)->get();

        foreach ($homeworks as $homework) {
            $commentText = $homeworkComments[array_rand($homeworkComments)];

            TeacherComment::create([
                'user_id' => $homework->teacher_id,
                'commentable_type' => Homework::class,
                'commentable_id' => $homework->id,
                'content' => $commentText,
                'is_visible_to_student' => true,
                'is_visible_to_parent' => true,
            ]);
        }

        $this->command->info('Комментарии к домашним заданиям созданы');
    }

    private function createHomeworkSubmissionComments($teachers)
    {
        $submissionComments = [
            'Работа принята, задания выполнены правильно.',
            'Хорошо выполнено! Видно старание и понимание материала.',
            'Есть недочеты, но в целом работа выполнена удовлетворительно.',
            'Требуется доработка отдельных заданий.',
            'Отличная работа! Продолжайте в том же духе.',
            'Работа выполнена не полностью, необходимо доделать.',
            'Качественная работа, спасибо за старание!',
            'Нужно исправить ошибки и переделать работу.',
        ];

        // Создаем комментарии для существующих сдач домашних заданий
        $submissions = HomeworkSubmission::with(['student', 'homework'])->limit(15)->get();

        foreach ($submissions as $submission) {
            $commentText = $submissionComments[array_rand($submissionComments)];

            TeacherComment::create([
                'user_id' => $submission->homework->teacher_id,
                'commentable_type' => HomeworkSubmission::class,
                'commentable_id' => $submission->id,
                'content' => $commentText,
                'is_visible_to_student' => true,
                'is_visible_to_parent' => true,
            ]);
        }

        $this->command->info('Комментарии к сдачам домашних заданий созданы');
    }
}
