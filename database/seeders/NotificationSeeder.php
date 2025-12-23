<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    /**
     * Создание системных уведомлений для пользователей
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание уведомлений...');

        $users = User::all();
        $notificationCount = 0;

        // Создаем уведомления для разных типов пользователей
        foreach ($users as $user) {
            $userNotifications = $this->generateUserNotifications($user);

            foreach ($userNotifications as $notificationData) {
                Notification::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'title' => $notificationData['title'],
                        'type' => $notificationData['type'],
                        'created_at' => $notificationData['created_at'],
                    ],
                    $notificationData
                );
                $notificationCount++;
            }
        }

        $this->command->info("Создано уведомлений: {$notificationCount}");
        $this->command->info('Создание уведомлений завершено!');
    }

    private function generateUserNotifications($user)
    {
        $notifications = [];
        $currentDate = Carbon::now();
        $startDate = $currentDate->copy()->subWeeks(4); // За последние 4 недели

        // Безопасное получение имени роли с fallback
        $roleName = $user->role?->name ?? null;

        // Пропускаем пользователей без роли
        if ($roleName === null) {
            $this->command->warn("Пользователь ID:{$user->id} ({$user->email}) не имеет роли - пропущен");
            return [];
        }

        switch ($roleName) {
            case 'teacher':
                $notifications = $this->generateTeacherNotifications($user, $startDate, $currentDate);
                break;
            case 'student':
                $notifications = $this->generateStudentNotifications($user, $startDate, $currentDate);
                break;
            case 'parent':
                $notifications = $this->generateParentNotifications($user, $startDate, $currentDate);
                break;
            case 'admin':
                $notifications = $this->generateAdminNotifications($user, $startDate, $currentDate);
                break;
            default:
                $this->command->warn("Неизвестная роль '{$roleName}' для пользователя ID:{$user->id} - пропущен");
                break;
        }

        return $notifications;
    }

    private function generateTeacherNotifications($user, $startDate, $currentDate)
    {
        $notifications = [];
        $notificationCount = mt_rand(8, 15);

        for ($i = 0; $i < $notificationCount; $i++) {
            $notificationDate = $startDate->copy()->addDays(mt_rand(0, 28));

            $notificationTypes = [
                'grade' => [
                    'title' => 'Новая оценка выставлена',
                    'message' => 'Ученик получил оценку по вашему предмету',
                    'type' => 'grade'
                ],
                'homework' => [
                    'title' => 'Новое домашнее задание',
                    'message' => 'Добавлено новое домашнее задание для класса',
                    'type' => 'homework'
                ],
                'attendance' => [
                    'title' => 'Изменения в посещаемости',
                    'message' => 'Зафиксированы изменения в посещаемости класса',
                    'type' => 'attendance'
                ],
                'announcement' => [
                    'title' => 'Объявление администрации',
                    'message' => 'Новое объявление от администрации школы',
                    'type' => 'announcement'
                ],
                'other' => [
                    'title' => 'Системное уведомление',
                    'message' => 'Важная информация от системы управления',
                    'type' => 'other'
                ]
            ];

            $type = array_rand($notificationTypes);
            $notificationData = $notificationTypes[$type];

            // Добавляем специфичные данные
            $notificationData['user_id'] = $user->id;
            $notificationData['created_at'] = $notificationDate;
            $notificationData['updated_at'] = $notificationDate;

            // Некоторые уведомления помечаем как прочитанные
            $notificationData['is_read'] = mt_rand(1, 100) <= 70; // 70% прочитаны
            $notificationData['read_at'] = $notificationData['is_read']
                ? $notificationDate->copy()->addHours(mt_rand(1, 24))
                : null;

            // Добавляем данные для типов уведомлений
            if ($type === 'grade') {
                $notificationData['data'] = json_encode([
                    'grade_id' => mt_rand(1, 100),
                    'subject' => $this->getRandomSubject(),
                    'student_name' => $this->getRandomStudentName(),
                ]);
            } elseif ($type === 'homework') {
                $notificationData['data'] = json_encode([
                    'homework_id' => mt_rand(1, 50),
                    'class_name' => $this->getRandomClassName(),
                    'subject' => $this->getRandomSubject(),
                ]);
            }

            $notifications[] = $notificationData;
        }

        // Добавляем уведомления для классных руководителей
        if ($this->isClassTeacher($user)) {
            $notifications = array_merge($notifications, $this->generateClassTeacherNotifications($user, $startDate, $currentDate));
        }

        return $notifications;
    }

    private function generateStudentNotifications($user, $startDate, $currentDate)
    {
        $notifications = [];
        $notificationCount = mt_rand(12, 20);

        for ($i = 0; $i < $notificationCount; $i++) {
            $notificationDate = $startDate->copy()->addDays(mt_rand(0, 28));

            $notificationTypes = [
                'grade' => [
                    'title' => 'Новая оценка',
                    'message' => 'Вы получили новую оценку',
                    'type' => 'grade'
                ],
                'homework' => [
                    'title' => 'Новое домашнее задание',
                    'message' => 'Учитель выдал новое домашнее задание',
                    'type' => 'homework'
                ],
                'announcement' => [
                    'title' => 'Объявление школы',
                    'message' => 'Важное объявление от администрации',
                    'type' => 'announcement'
                ],
                'other' => [
                    'title' => 'Напоминание',
                    'message' => 'Не забудьте о предстоящем занятии',
                    'type' => 'other'
                ]
            ];

            $type = array_rand($notificationTypes);
            $notificationData = $notificationTypes[$type];

            $notificationData['user_id'] = $user->id;
            $notificationData['created_at'] = $notificationDate;
            $notificationData['updated_at'] = $notificationDate;
            $notificationData['is_read'] = mt_rand(1, 100) <= 80; // 80% прочитаны
            $notificationData['read_at'] = $notificationData['is_read']
                ? $notificationDate->copy()->addHours(mt_rand(1, 12))
                : null;

            if ($type === 'grade') {
                $notificationData['data'] = json_encode([
                    'grade_id' => mt_rand(1, 100),
                    'subject' => $this->getRandomSubject(),
                    'value' => mt_rand(2, 5),
                    'description' => 'Контрольная работа',
                ]);
            }

            $notifications[] = $notificationData;
        }

        return $notifications;
    }

    private function generateParentNotifications($user, $startDate, $currentDate)
    {
        $notifications = [];
        $notificationCount = mt_rand(6, 12);

        // Получаем детей родителя
        $children = $user->children()->get();

        for ($i = 0; $i < $notificationCount; $i++) {
            $notificationDate = $startDate->copy()->addDays(mt_rand(0, 28));

            if ($children->isNotEmpty()) {
                $child = $children->random();

                $notificationTypes = [
                    'grade' => [
                        'title' => "Оценка ребенка: {$child->getFullNameAttribute()}",
                        'message' => "Ваш ребенок получил новую оценку",
                        'type' => 'grade'
                    ],
                    'attendance' => [
                        'title' => "Посещаемость: {$child->getFullNameAttribute()}",
                        'message' => "Есть пропуски занятий",
                        'type' => 'attendance'
                    ],
                    'homework' => [
                        'title' => "Домашнее задание: {$child->getFullNameAttribute()}",
                        'message' => "Есть невыполненные задания",
                        'type' => 'homework'
                    ],
                    'announcement' => [
                        'title' => 'Объявление школы',
                        'message' => 'Важная информация для родителей',
                        'type' => 'announcement'
                    ]
                ];

                $type = array_rand($notificationTypes);
                $notificationData = $notificationTypes[$type];

                $notificationData['user_id'] = $user->id;
                $notificationData['created_at'] = $notificationDate;
                $notificationData['updated_at'] = $notificationDate;
                $notificationData['is_read'] = mt_rand(1, 100) <= 60; // 60% прочитаны
                $notificationData['read_at'] = $notificationData['is_read']
                    ? $notificationDate->copy()->addHours(mt_rand(1, 48))
                    : null;

                if ($type === 'grade') {
                    $notificationData['data'] = json_encode([
                        'student_id' => $child->id,
                        'student_name' => $child->getFullNameAttribute(),
                        'grade_id' => mt_rand(1, 100),
                        'subject' => $this->getRandomSubject(),
                        'value' => mt_rand(2, 5),
                    ]);
                }

                $notifications[] = $notificationData;
            }
        }

        return $notifications;
    }

    private function generateAdminNotifications($user, $startDate, $currentDate)
    {
        $notifications = [];
        $notificationCount = mt_rand(5, 10);

        for ($i = 0; $i < $notificationCount; $i++) {
            $notificationDate = $startDate->copy()->addDays(mt_rand(0, 28));

            $notificationTypes = [
                'announcement' => [
                    'title' => 'Системное сообщение',
                    'message' => 'Информация от системы управления',
                    'type' => 'announcement'
                ],
                'other' => [
                    'title' => 'Требуется ваше внимание',
                    'message' => 'Необходимо выполнить административные действия',
                    'type' => 'other'
                ]
            ];

            $type = array_rand($notificationTypes);
            $notificationData = $notificationTypes[$type];

            $notificationData['user_id'] = $user->id;
            $notificationData['created_at'] = $notificationDate;
            $notificationData['updated_at'] = $notificationDate;
            $notificationData['is_read'] = mt_rand(1, 100) <= 90; // 90% прочитаны
            $notificationData['read_at'] = $notificationData['is_read']
                ? $notificationDate->copy()->addHours(mt_rand(1, 6))
                : null;

            $notifications[] = $notificationData;
        }

        return $notifications;
    }

    private function generateClassTeacherNotifications($user, $startDate, $currentDate)
    {
        $notifications = [];
        $notificationCount = mt_rand(3, 6);

        for ($i = 0; $i < $notificationCount; $i++) {
            $notificationDate = $startDate->copy()->addDays(mt_rand(0, 28));

            $notifications[] = [
                'user_id' => $user->id,
                'title' => 'Уведомление классного руководителя',
                'message' => 'Есть информация по вашему классу',
                'type' => 'announcement',
                'data' => json_encode([
                    'class_name' => $this->getClassTeacherClass($user),
                    'action_required' => true,
                ]),
                'created_at' => $notificationDate,
                'updated_at' => $notificationDate,
                'is_read' => mt_rand(1, 100) <= 70,
                'read_at' => null,
            ];
        }

        return $notifications;
    }

    private function isClassTeacher($user)
    {
        return \App\Models\SchoolClass::where('class_teacher_id', $user->id)->exists();
    }

    private function getClassTeacherClass($user)
    {
        $class = \App\Models\SchoolClass::where('class_teacher_id', $user->id)->first();
        return $class ? $class->name : 'Неизвестный класс';
    }

    private function getRandomSubject()
    {
        $subjects = ['Математика', 'Русский язык', 'Литература', 'История', 'Физика', 'Химия', 'Биология', 'Английский язык'];
        return $subjects[array_rand($subjects)];
    }

    private function getRandomClassName()
    {
        $classes = ['5А', '5Б', '6А', '6Б', '7А', '7Б', '8А', '8Б', '9А', '9Б', '10А', '10Б', '11А', '11Б'];
        return $classes[array_rand($classes)];
    }

    private function getRandomStudentName()
    {
        $names = ['Иванов И.И.', 'Петров П.П.', 'Сидоров С.С.', 'Козлов К.К.', 'Смирнов С.С.'];
        return $names[array_rand($names)];
    }
}
