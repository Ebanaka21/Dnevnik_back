<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\StudentClass;
use App\Models\Subject;
use App\Models\ParentStudent;
use Illuminate\Support\Facades\DB;

/**
 * Создание специальных тестовых аккаунтов для системы
 *
 * Создает фиксированные тестовые аккаунты с известными логинами и паролями:
 * - Учитель: teacher@example.com / password123
 * - Ученик: student@example.com / password123
 *
 * Эти аккаунты используются для тестирования функционала системы
 * и не должны изменяться в процессе разработки.
 */
class TestAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎯 Начинаем создание тестовых аккаунтов...');
        $this->command->info('');

        // Создаем тестового учителя
        $teacher = $this->createTestTeacher();

        // Создаем тестового ученика
        $student = $this->createTestStudent();

        // Создаем родителя для тестового ученика
        $parent = $this->createTestParent($student);

        // Связываем данные
        $class = $this->linkTestData($teacher, $student, $parent);

        // Создаем тестовые уведомления
        $this->createTestNotifications($teacher, $student, $parent);

        // Создаем тестовые данные для дашбордов
        $this->createTestDashboardData($teacher, $student, $class);

        $this->command->info('');
        $this->command->info('✅ Тестовые аккаунты созданы успешно!');
        $this->command->info('');
        $this->command->info('📧 ТЕСТОВЫЕ АККАУНТЫ:');
        $this->command->info('👨‍🏫 Учитель: teacher@example.com / password123');
        $this->command->info('👨‍🎓 Ученик: student@example.com / password123');
        $this->command->info('👪 Родитель: parent@example.com / password123');
        $this->command->info('');
        $this->command->info('🔗 Созданы связи: учитель-класс-предмет, ученик-класс, родитель-ученик');
        $this->command->info('🔔 Созданы тестовые уведомления');
        $this->command->info('');
        $this->command->info('⚠️  ВНИМАНИЕ: Эти аккаунты предназначены только для тестирования!');
    }

    private function createTestTeacher()
    {
        $teacherData = [
            'name' => 'Александр',
            'surname' => 'Тестов',
            'second_name' => 'Петрович',
            'email' => 'teacher@example.com',
            'phone' => '+7(900)123-45-67',
            'birthday' => '1980-05-15',
            'gender' => 'male',
        ];

        $userData = array_intersect_key($teacherData, array_flip([
            'name', 'surname', 'second_name', 'email', 'phone', 'birthday', 'gender'
        ]));

        $teacher = User::updateOrCreate(
            ['email' => $teacherData['email']],
            array_merge($userData, [
                'password' => Hash::make('password123'),
                'role' => 'teacher',
            ])
        );

        // Связываем с предметами
        $subjects = Subject::whereIn('name', ['Математика', 'Физика'])->get();
        if ($subjects->isNotEmpty()) {
            $teacher->subjects()->sync($subjects->pluck('id'));
        }

        // Класс будет назначен в linkTestData

        $this->command->info("👨‍🏫 Создан тестовый учитель: {$teacher->getFullNameAttribute()}");

        return $teacher;
    }

    private function createTestStudent()
    {
        // Находим класс 10А для тестового ученика
        $class = SchoolClass::where('name', '10А')
                            ->where('academic_year', '2024-2025')
                            ->first();

        if (!$class) {
            $this->command->error('❌ Класс 10А не найден! Запустите сначала SchoolSeeder.');
            return null;
        }

        if (!$class->is_active) {
            $this->command->error('❌ Класс 10А неактивен! Проверьте настройки класса.');
            return null;
        }

        $studentData = [
            'name' => 'Иван',
            'surname' => 'Тестов',
            'second_name' => 'Александрович',
            'email' => 'student@example.com',
            'phone' => '+7(900)987-65-43',
            'birthday' => '2009-03-22',
            'gender' => 'male',
        ];

        $userData = array_intersect_key($studentData, array_flip([
            'name', 'surname', 'second_name', 'email', 'phone', 'birthday', 'gender'
        ]));

        $student = User::updateOrCreate(
            ['email' => $studentData['email']],
            array_merge($userData, [
                'password' => Hash::make('password123'),
                'role' => 'student',
            ])
        );

        // Связываем ученика с классом
        DB::table('student_classes')->updateOrInsert(
            [
                'student_id' => $student->id,
                'school_class_id' => $class->id,
                'academic_year' => '2024-2025'
            ],
            [
                'school_class_id' => $class->id, // Добавляем обязательное поле class_id
                'is_active' => true,
                'enrolled_at' => '2024-09-01',
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        $this->command->info("👨‍🎓 Создан тестовый ученик: {$student->getFullNameAttribute()} (класс {$class->name})");

        return $student;
    }

    private function createTestParent($student)
    {

        $parentData = [
            'name' => 'Елена',
            'surname' => 'Тестова',
            'second_name' => 'Сергеевна',
            'email' => 'parent@example.com',
            'phone' => '+7(900)555-77-99',
            'birthday' => '1985-11-10',
            'gender' => 'female',
        ];

        $userData = array_intersect_key($parentData, array_flip([
            'name', 'surname', 'second_name', 'email', 'phone', 'birthday', 'gender'
        ]));

        $parent = User::updateOrCreate(
            ['email' => $parentData['email']],
            array_merge($userData, [
                'password' => Hash::make('password123'),
                'role' => 'parent',
            ])
        );

        // Связываем родителя с учеником
        ParentStudent::updateOrCreate(
            [
                'parent_id' => $parent->id,
                'student_id' => $student->id
            ],
            [
                'relationship' => 'mother',
                'is_primary' => true
            ]
        );

        $this->command->info("👪 Создан тестовый родитель: {$parent->getFullNameAttribute()} (мать {$student->getFullNameAttribute()})");

        return $parent;
    }

    private function linkTestData($teacher, $student, $parent)
    {
        if (!$teacher || !$student || !$parent) {
            return;
        }

        // Назначаем учителя классным руководителем класса 10А
        $class = SchoolClass::where('name', '10А')
                           ->where('academic_year', '2024-2025')
                           ->first();

        if (!$class) {
            $this->command->error('❌ Класс 10А не найден! Пропускаем назначение классного руководителя.');
            return;
        }

        if (!$class->is_active) {
            $this->command->error('❌ Класс 10А неактивен! Пропускаем назначение классного руководителя.');
            return;
        }

        if ($class && !$class->class_teacher_id) {
            $class->update(['class_teacher_id' => $teacher->id]);
            $this->command->info("👥 Назначен классным руководителем класса: {$class->name}");
        }

        // Создаем связи учитель-предмет-класс
        $subjects = Subject::whereIn('name', ['Математика', 'Физика'])->get();

        foreach ($subjects as $subject) {
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

        $this->command->info("🔗 Созданы связи учитель-предмет-класс");
    }

    private function createTestNotifications($teacher, $student, $parent)
    {
        if (!$teacher || !$student || !$parent) {
            return;
        }

        // Создаем тестовые уведомления для учителя
        $teacherNotifications = [
            [
                'user_id' => $teacher->id,
                'title' => 'Добро пожаловать в систему!',
                'message' => 'Ваш тестовый аккаунт успешно создан. Вы можете начать работу с системой.',
                'type' => 'system',
                'is_read' => false,
            ],
            [
                'user_id' => $teacher->id,
                'title' => 'Новое домашнее задание',
                'message' => 'Добавлено домашнее задание по математике для класса 10А',
                'type' => 'homework',
                'is_read' => false,
            ]
        ];

        // Создаем тестовые уведомления для ученика
        $studentNotifications = [
            [
                'user_id' => $student->id,
                'title' => 'Добро пожаловать!',
                'message' => 'Ваш тестовый аккаунт ученика создан. Изучите раздел "Мои оценки" и "Расписание".',
                'type' => 'system',
                'is_read' => false,
            ],
            [
                'user_id' => $student->id,
                'title' => 'Новая оценка',
                'message' => 'Получена оценка 5 по математике за контрольную работу',
                'type' => 'grade',
                'is_read' => false,
            ]
        ];

        // Создаем тестовые уведомления для родителя
        $parentNotifications = [
            [
                'user_id' => $parent->id,
                'title' => 'Добро пожаловать!',
                'message' => 'Ваш тестовый аккаунт родителя создан. Вы можете следить за успеваемостью вашего ребенка.',
                'type' => 'system',
                'is_read' => false,
            ],
            [
                'user_id' => $parent->id,
                'title' => 'Успеваемость ребенка',
                'message' => 'Иван показывает хорошие результаты в учебе',
                'type' => 'grade',
                'is_read' => false,
            ]
        ];

        foreach ($teacherNotifications as $notification) {
            \App\Models\Notification::create($notification);
        }

        foreach ($studentNotifications as $notification) {
            \App\Models\Notification::create($notification);
        }

        foreach ($parentNotifications as $notification) {
            \App\Models\Notification::create($notification);
        }

        $this->command->info("🔔 Созданы тестовые уведомления для всех аккаунтов");
    }

    private function createTestDashboardData($teacher, $student, $class)
    {
        if (!$teacher || !$student || !$class) {
            return;
        }

        // Получаем всех учеников класса
        $students = \App\Models\StudentClass::where('school_class_id', $class->id)
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->take(7); // Все ученики класса

        if ($students->isEmpty()) {
            $students = collect([$student]); // Если нет учеников, используем тестового
        }

        // Создаем тестовые уроки
        $subjects = Subject::whereIn('name', ['Математика', 'Физика'])->get();
        if ($subjects->isEmpty()) {
            // Если предметы не найдены, берем первые доступные
            $subjects = Subject::take(2)->get();
        }
        $gradeTypes = \App\Models\GradeType::all();

        $this->command->info('📚 Предметы найдено: ' . $subjects->count());
        $this->command->info('👨‍🎓 Учеников найдено: ' . $students->count());
        $this->command->info('🏷️  Типы оценок найдено: ' . $gradeTypes->count());

        if ($subjects->isEmpty() || $gradeTypes->isEmpty()) {
            $this->command->error('❌ Предметы или типы оценок не найдены! Пропускаем создание тестовых данных.');
            return;
        }

        if ($students->isEmpty()) {
            $this->command->error('❌ Ученики не найдены! Пропускаем создание тестовых данных.');
            return;
        }

        // Создаем уроки для каждого предмета
        $lessons = [];
        foreach ($subjects as $subject) {
            $lesson = \App\Models\Lesson::create([
                'teacher_id' => $teacher->id,
                'subject_id' => $subject->id,
                'school_class_id' => $class->id,
                'title' => 'Тестовый урок по ' . $subject->name,
                'description' => 'Тестовое описание урока',
                'date' => now()->subDays(rand(0, 30)),
                'lesson_number' => 1,
                'academic_year' => '2024-2025',
            ]);
            $lessons[$subject->id] = $lesson;
        }

        $comments = [
            'Отличная работа',
            'Хорошая работа',
            'Превосходно',
            'Нужно больше стараться',
            'Удовлетворительно',
            'Требуется доработка',
            'Выше среднего',
            'Ниже среднего',
        ];

        $values = [3, 4, 5]; // Возможные оценки

        // Создаем оценки за последние 6 месяцев
        $startDate = now()->subMonths(6);
        $endDate = now();

        foreach ($students as $studentItem) {
            foreach ($subjects as $subject) {
                // Создаем 3-5 оценок на ученика на предмет
                $numGrades = rand(3, 5);

                for ($i = 0; $i < $numGrades; $i++) {
                    $date = $startDate->copy()->addDays(rand(0, $endDate->diffInDays($startDate)));

                    \App\Models\Grade::updateOrCreate([
                        'student_id' => $studentItem->id,
                        'lesson_id' => $lessons[$subject->id]->id,
                        'date' => $date->format('Y-m-d'),
                    ], [
                        'grade_type_id' => $gradeTypes->random()->id,
                        'value' => $values[array_rand($values)],
                        'comment' => $comments[array_rand($comments)],
                        'academic_year' => '2024-2025',
                    ]);
                }
            }
        }

        // Создаем тестовые посещаемости
        $dates = ['2024-10-01', '2024-10-02', '2024-10-03', '2024-10-04', '2024-10-05'];
        foreach ($dates as $date) {
            \App\Models\Attendance::updateOrCreate([
                'student_id' => $student->id,
                'subject_id' => $subjects->first()->id,
                'date' => $date,
            ], [
                'teacher_id' => $teacher->id,
                'status' => 'present',
            ]);
        }

        // Создаем домашнее задание
        $homework = \App\Models\Homework::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $subjects->first()->id,
            'school_class_id' => $class->id,
            'title' => 'Решить задачи по математике',
            'description' => 'Решить задачи 1-10 из учебника стр. 45',
            'due_date' => '2024-10-10',
            'academic_year' => '2024-2025',
            'is_active' => true,
        ]);

        // Создаем отправку домашнего задания
        \App\Models\HomeworkSubmission::create([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'submission_text' => 'Решения приложены в файле',
            'submitted_at' => '2024-10-08',
            'status' => 'submitted',
            'grade' => 5,
            'teacher_comment' => 'Отлично решено!',
        ]);


        // Создаем расписание
        \App\Models\Schedule::create([
            'teacher_id' => $teacher->id,
            'school_class_id' => $class->id,
            'subject_id' => $subjects->first()->id,
            'day_of_week' => 1, // Понедельник
            'lesson_number' => 1,
            'academic_year' => '2024-2025',
            'is_active' => true,
        ]);

        $this->command->info("📊 Созданы тестовые данные для дашбордов: оценки, посещаемость, ДЗ, уроки, расписание");
    }
}
