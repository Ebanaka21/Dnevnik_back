<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Notification;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_get_own_notifications()
    {
        $user = User::factory()->create();
        Notification::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'title',
                        'message',
                        'type',
                        'is_read',
                        'created_at'
                    ]
                ]
            ]);
    }

    /** @test */
    public function user_can_get_unread_notifications()
    {
        $user = User::factory()->create();
        Notification::factory()->create([
            'user_id' => $user->id,
            'is_read' => false
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/api/notifications/unread');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'title',
                        'message',
                        'type',
                        'is_read',
                        'created_at'
                    ]
                ]
            ]);
    }

    /** @test */
    public function user_can_mark_notification_as_read()
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'is_read' => false
        ]);

        $response = $this->actingAs($user, 'api')->putJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson(['is_read' => true]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true
        ]);
    }

    /** @test */
    public function user_can_mark_all_notifications_as_read()
    {
        $user = User::factory()->create();
        Notification::factory()->create([
            'user_id' => $user->id,
            'is_read' => false
        ]);

        $response = $this->actingAs($user, 'api')->putJson('/api/notifications/mark-all-read');

        $response->assertStatus(200)
            ->assertJson(['message' => 'All notifications marked as read']);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'is_read' => false
        ]);
    }

    /** @test */
    public function teacher_can_send_bulk_notifications()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $students = User::factory()->count(3)->create(['role' => 'student']);

        $response = $this->actingAs($teacher, 'api')->postJson('/api/notifications/bulk', [
            'user_ids' => $students->pluck('id')->toArray(),
            'title' => 'Новое задание',
            'message' => 'У вас новое домашнее задание по математике',
            'type' => 'homework'
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Bulk notifications sent successfully']);

        $this->assertDatabaseCount('notifications', 3);
    }

    /** @test */
    public function teacher_can_send_notifications_to_class()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = SchoolClass::factory()->create();

        $response = $this->actingAs($teacher, 'api')->postJson('/api/notifications/class', [
            'school_class_id' => $class->id,
            'title' => 'Отмена урока',
            'message' => 'Завтрашний урок по математике отменен',
            'type' => 'schedule',
            'recipients' => ['students', 'parents']
        ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Class notifications sent successfully']);
    }

    /** @test */
    public function user_can_get_unread_notifications_count()
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'is_read' => false
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson(['count' => 3]);
    }

    /** @test */
    public function user_can_get_recent_notifications()
    {
        $user = User::factory()->create();
        Notification::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->getJson('/api/notifications/recent?limit=3');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'title',
                        'message',
                        'type',
                        'is_read',
                        'created_at'
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function user_can_delete_notification()
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }
}
