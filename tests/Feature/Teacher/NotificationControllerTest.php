<?php

namespace Tests\Feature\Teacher;

use App\Models\User;
use App\Models\Notification;
use App\Models\Subject;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $teacher;
    protected $student;
    protected $parent;
    protected $notification;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем роли
        $teacherRole = \App\Models\Role::create(['name' => 'teacher']);
        $studentRole = \App\Models\Role::create(['name' => 'student']);
        $parentRole = \App\Models\Role::create(['name' => 'parent']);

        // Создаем учителя
        $this->teacher = User::factory()->create([
            'role_id' => $teacherRole->id,
            'name' => 'Тестовый Учитель',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password')
        ]);

        // Создаем ученика
        $this->student = User::factory()->create([
            'role_id' => $studentRole->id,
            'name' => 'Тестовый Ученик',
            'email' => 'student@test.com',
            'password' => bcrypt('password')
        ]);

        // Создаем родителя
        $this->parent = User::factory()->create([
            'role_id' => $parentRole->id,
            'name' => 'Тестовый Родитель',
            'email' => 'parent@test.com',
            'password' => bcrypt('password')
        ]);

        // Связываем родителя с учеником
        \App\Models\ParentStudent::create([
            'parent_id' => $this->parent->id,
            'student_id' => $this->student->id
        ]);

        // Создаем уведомление
        $this->notification = Notification::create([
            'user_id' => $this->teacher->id,
            'title' => 'Тестовое уведомление',
            'message' => 'Это тестовое уведомление',
            'type' => 'test',
            'is_read' => false
        ]);

        // Авторизуемся как учитель
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'teacher@test.com',
            'password' => 'password'
        ]);

        $this->token = $loginResponse->json('token');
    }

    /** @test */
    public function teacher_can_get_all_notifications()
    {
        // Создаем дополнительные уведомления
        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'user_id' => $this->teacher->id,
                'title' => 'Уведомление ' . ($i + 1),
                'message' => 'Сообщение ' . ($i + 1),
                'type' => 'test',
                'is_read' => $i % 2 == 0
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'message',
                        'type',
                        'is_read',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    /** @test */
    public function teacher_can_get_unread_notifications()
    {
        // Создаем прочитанные и непрочитанные уведомления
        Notification::create([
            'user_id' => $this->teacher->id,
            'title' => 'Непрочитанное',
            'message' => 'Непрочитанное уведомление',
            'type' => 'test',
            'is_read' => false
        ]);

        Notification::create([
            'user_id' => $this->teacher->id,
            'title' => 'Прочитанное',
            'message' => 'Прочитанное уведомление',
            'type' => 'test',
            'is_read' => true
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'notifications' => [
                    '*' => [
                        'id',
                        'title',
                        'message',
                        'type',
                        'is_read'
                    ]
                ],
                'statistics' => [
                    'total_unread',
                    'by_type',
                    'today_unread',
                    'this_week_unread'
                ]
            ]);

        // Проверяем, что все уведомления непрочитанные
        $unreadNotifications = $response->json('notifications');
        foreach ($unreadNotifications as $notification) {
            $this->assertEquals(false, $notification['is_read']);
        }
    }

    /** @test */
    public function teacher_can_mark_notification_as_read()
    {
        $unreadNotification = Notification::create([
            'user_id' => $this->teacher->id,
            'title' => 'Непрочитанное уведомление',
            'message' => 'Нужно отметить как прочитанное',
            'type' => 'test',
            'is_read' => false
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson('/api/notifications/' . $unreadNotification->id . '/read');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Уведомление отмечено как прочитанное']);

        // Проверяем, что уведомление теперь прочитано
        $this->assertDatabaseHas('notifications', [
            'id' => $unreadNotification->id,
            'is_read' => true
        ]);
    }

    /** @test */
    public function teacher_can_mark_all_notifications_as_read()
    {
        // Создаем несколько непрочитанных уведомлений
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'user_id' => $this->teacher->id,
                'title' => 'Непрочитанное ' . ($i + 1),
                'message' => 'Сообщение ' . ($i + 1),
                'type' => 'test',
                'is_read' => false
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson('/api/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'updated_count'
            ]);

        // Проверяем, что все уведомления теперь прочитаны
        $unreadCount = Notification::where('user_id', $this->teacher->id)
            ->where('is_read', false)
            ->count();
        $this->assertEquals(0, $unreadCount);
    }

    /** @test */
    public function teacher_can_get_unread_count()
    {
        // Создаем несколько непрочитанных уведомлений
        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'user_id' => $this->teacher->id,
                'title' => 'Непрочитанное ' . ($i + 1),
                'message' => 'Сообщение ' . ($i + 1),
                'type' => 'test',
                'is_read' => false
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'unread_count'
            ]);

        $this->assertEquals(4, $response->json('unread_count')); // 3 новых + 1 существующее
    }

    /** @test */
    public function teacher_can_get_recent_notifications()
    {
        // Создаем уведомления разных дат
        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'user_id' => $this->teacher->id,
                'title' => 'Уведомление ' . ($i + 1),
                'message' => 'Сообщение ' . ($i + 1),
                'type' => 'test',
                'is_read' => false,
                'created_at' => now()->subDays($i)
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications/recent?limit=3');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'notifications' => [
                    '*' => [
                        'id',
                        'title',
                        'message',
                        'created_at'
                    ]
                ],
                'unread_count'
            ]);

        $notifications = $response->json('notifications');
        $this->assertEquals(3, count($notifications));
    }

    /** @test */
    public function teacher_can_delete_notification()
    {
        $notificationToDelete = Notification::create([
            'user_id' => $this->teacher->id,
            'title' => 'Удаляемое уведомление',
            'message' => 'Это уведомление будет удалено',
            'type' => 'test',
            'is_read' => false
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->deleteJson('/api/notifications/' . $notificationToDelete->id);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Уведомление удалено']);

        $this->assertDatabaseMissing('notifications', ['id' => $notificationToDelete->id]);
    }

    /** @test */
    public function teacher_can_create_notification()
    {
        $notificationData = [
            'user_id' => $this->student->id,
            'title' => 'Новое уведомление',
            'message' => 'Создано учителем',
            'type' => 'announcement',
            'is_read' => false
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/notifications', $notificationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'user_id',
                'title',
                'message',
                'type',
                'is_read',
                'created_at'
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->student->id,
            'title' => 'Новое уведомление'
        ]);
    }

    /** @test */
    public function teacher_can_send_bulk_notifications()
    {
        // Создаем несколько пользователей
        $users = User::factory()->count(3)->create();

        $bulkData = [
            'user_ids' => $users->pluck('id')->toArray(),
            'title' => 'Массовое уведомление',
            'message' => 'Отправлено всем выбранным пользователям',
            'type' => 'announcement'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/notifications/bulk', $bulkData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'created',
                'errors',
                'created_count',
                'errors_count'
            ]);

        $this->assertEquals(3, $response->json('created_count'));
    }

    /** @test */
    public function teacher_can_send_notification_to_class()
    {
        // Создаем класс с учениками
        $class = SchoolClass::create([
            'name' => '10А',
            'academic_year' => '2024-2025'
        ]);

        $this->student->studentClasses()->attach($class->id);

        $classNotificationData = [
            'school_class_id' => $class->id,
            'title' => 'Уведомление для класса',
            'message' => 'Важная информация для класса',
            'type' => 'class_announcement',
            'recipients' => ['students']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/notifications/class', $classNotificationData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'class' => ['id', 'name'],
                'created',
                'errors',
                'created_count',
                'errors_count'
            ]);

        // Проверяем, что уведомление создано для ученика
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->student->id,
            'title' => 'Уведомление для класса'
        ]);
    }

    /** @test */
    public function teacher_can_send_notification_to_parents()
    {
        $class = SchoolClass::create([
            'name' => '10А',
            'academic_year' => '2024-2025'
        ]);

        $this->student->studentClasses()->attach($class->id);

        $parentNotificationData = [
            'school_class_id' => $class->id,
            'title' => 'Уведомление для родителей',
            'message' => 'Информация для родителей учеников',
            'type' => 'parent_announcement',
            'recipients' => ['parents']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/notifications/class', $parentNotificationData);

        $response->assertStatus(200);

        // Проверяем, что уведомление создано для родителя
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->parent->id,
            'title' => 'Уведомление для родителей'
        ]);
    }

    /** @test */
    public function teacher_can_filter_notifications_by_type()
    {
        // Создаем уведомления разных типов
        $types = ['announcement', 'grade', 'homework', 'attendance'];
        foreach ($types as $type) {
            Notification::create([
                'user_id' => $this->teacher->id,
                'title' => 'Уведомление типа ' . $type,
                'message' => 'Сообщение типа ' . $type,
                'type' => $type,
                'is_read' => false
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications?type=grade');

        $response->assertStatus(200);

        $notifications = $response->json('data');
        foreach ($notifications as $notification) {
            $this->assertEquals('grade', $notification['type']);
        }
    }

    /** @test */
    public function teacher_can_filter_notifications_by_read_status()
    {
        // Создаем прочитанные и непрочитанные уведомления
        Notification::create([
            'user_id' => $this->teacher->id,
            'title' => 'Прочитанное',
            'message' => 'Уже прочитано',
            'type' => 'test',
            'is_read' => true
        ]);

        Notification::create([
            'user_id' => $this->teacher->id,
            'title' => 'Непрочитанное',
            'message' => 'Не прочитано',
            'type' => 'test',
            'is_read' => false
        ]);

        // Фильтр по прочитанным
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications?is_read=true');

        $response->assertStatus(200);

        $notifications = $response->json('data');
        foreach ($notifications as $notification) {
            $this->assertEquals(true, $notification['is_read']);
        }
    }

    /** @test */
    public function teacher_can_search_notifications()
    {
        // Создаем уведомления с разными заголовками
        Notification::create([
            'user_id' => $this->teacher->id,
            'title' => 'Важное объявление',
            'message' => 'Содержит ключевое слово',
            'type' => 'announcement',
            'is_read' => false
        ]);

        Notification::create([
            'user_id' => $this->teacher->id,
            'title' => 'Обычное уведомление',
            'message' => 'Без ключевых слов',
            'type' => 'test',
            'is_read' => false
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications?search=объявление');

        $response->assertStatus(200);

        $notifications = $response->json('data');
        $this->assertEquals(1, count($notifications));
        $this->assertEquals('Важное объявление', $notifications[0]['title']);
    }

    /** @test */
    public function teacher_can_sort_notifications()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications?sort_by=created_at&sort_order=asc');

        $response->assertStatus(200);
    }

    /** @test */
    public function pagination_works_for_notifications()
    {
        // Создаем много уведомлений
        for ($i = 0; $i < 25; $i++) {
            Notification::create([
                'user_id' => $this->teacher->id,
                'title' => 'Уведомление ' . ($i + 1),
                'message' => 'Сообщение ' . ($i + 1),
                'type' => 'test',
                'is_read' => false
            ]);
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function teacher_can_get_notification_details()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications/' . $this->notification->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
                'message',
                'type',
                'is_read',
                'created_at',
                'updated_at'
            ]);

        // Проверяем, что уведомление автоматически отмечается как прочитанное
        $this->assertDatabaseHas('notifications', [
            'id' => $this->notification->id,
            'is_read' => true
        ]);
    }

    /** @test */
    public function teacher_can_update_notification()
    {
        $updateData = [
            'is_read' => true
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->putJson('/api/notifications/' . $this->notification->id, $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $this->notification->id,
                'is_read' => true
            ]);
    }

    /** @test */
    public function unauthorized_user_cannot_access_notifications()
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }

    /** @test */
    public function teacher_cannot_access_other_users_notifications()
    {
        $otherUser = User::factory()->create();
        $otherNotification = Notification::create([
            'user_id' => $otherUser->id,
            'title' => 'Чужое уведомление',
            'message' => 'Чужое сообщение',
            'type' => 'test',
            'is_read' => false
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/notifications/' . $otherNotification->id);

        $response->assertStatus(404);
    }

    /** @test */
    public function validation_works_for_notification_creation()
    {
        // Неполные данные
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/notifications', []);

        $response->assertStatus(422);

        // Неверный тип
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/notifications', [
            'user_id' => $this->teacher->id,
            'title' => 'Уведомление',
            'message' => 'Сообщение',
            'type' => 'invalid_type'
        ]);

        $response->assertStatus(422);

        // Несуществующий пользователь
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/notifications', [
            'user_id' => 999,
            'title' => 'Уведомление',
            'message' => 'Сообщение',
            'type' => 'announcement'
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function teacher_can_cleanup_old_notifications()
    {
        // Создаем старые уведомления
        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'user_id' => $this->teacher->id,
                'title' => 'Старое уведомление ' . ($i + 1),
                'message' => 'Сообщение ' . ($i + 1),
                'type' => 'test',
                'is_read' => true,
                'created_at' => now()->subDays(40)
            ]);
        }

        $cleanupData = [
            'days' => 30,
            'read_only' => true
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/notifications/cleanup', $cleanupData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'deleted_count'
            ]);

        // Проверяем, что старые уведомления удалены
        $oldNotificationsCount = Notification::where('user_id', $this->teacher->id)
            ->where('created_at', '<', now()->subDays(30))
            ->count();
        $this->assertEquals(0, $oldNotificationsCount);
    }

    /** @test */
    public function bulk_notification_handles_errors_gracefully()
    {
        // Создаем пользователей, один из которых не существует
        $validUsers = User::factory()->count(2)->create();
        $userIds = array_merge($validUsers->pluck('id')->toArray(), [999]); // Добавляем несуществующий ID

        $bulkData = [
            'user_ids' => $userIds,
            'title' => 'Массовое уведомление',
            'message' => 'Тест массовой отправки',
            'type' => 'announcement'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/notifications/bulk', $bulkData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'created',
                'errors',
                'created_count',
                'errors_count'
            ]);

        // Должно быть 2 созданных уведомления и 1 ошибка
        $this->assertEquals(2, $response->json('created_count'));
        $this->assertEquals(1, $response->json('errors_count'));
    }
}
