<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    // Получить уведомления пользователя
    public function index(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            $query = Notification::where('user_id', $user->id);

            // Фильтрация по типу
            if ($request->has('type') && !empty($request->type)) {
                $query->where('type', $request->type);
            }

            // Фильтрация по статусу прочтения
            if ($request->has('is_read') && !empty($request->is_read)) {
                if ($request->is_read === 'true') {
                    $query->where('is_read', true);
                } else {
                    $query->where('is_read', false);
                }
            }

            // Фильтрация по связанной сущности
            if ($request->has('related_type') && !empty($request->related_type)) {
                $query->where('related_type', $request->related_type);
            }

            // Поиск по заголовку или сообщению
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('message', 'like', "%{$search}%");
                });
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $notifications = $query->get();

            return response()->json($notifications);

        } catch (\Exception $e) {
            Log::error("Error in NotificationController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении уведомлений'], 500);
        }
    }

    // Отметить уведомление как прочитанное
    public function markAsRead($id, Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $notification = Notification::where('user_id', $user->id)->findOrFail($id);

            if ($notification->is_read) {
                return response()->json(['message' => 'Уведомление уже прочитано']);
            }

            $notification->update(['is_read' => true]);

            Log::info('Notification marked as read', [
                'notification_id' => $id,
                'user_id' => $user->id
            ]);

            return response()->json(['message' => 'Уведомление отмечено как прочитанное']);

        } catch (\Exception $e) {
            Log::error("Error in NotificationController::markAsRead", [
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Уведомление не найдено'], 404);
        }
    }

    // Отметить все уведомления как прочитанные
    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            $updatedCount = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            Log::info('All notifications marked as read', [
                'user_id' => $user->id,
                'updated_count' => $updatedCount
            ]);

            return response()->json([
                'message' => 'Все уведомления отмечены как прочитанные',
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            Log::error("Error in NotificationController::markAllAsRead", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при отмечании уведомлений'], 500);
        }
    }

    // Создать новое уведомление (для внутреннего использования)
    public function store(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|string|max:50',
                'related_id' => 'nullable|integer',
                'related_type' => 'nullable|string|max:100',
                'is_read' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $notification = Notification::create($validator->validated());

            Log::info('Notification created', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type
            ]);

            return response()->json($notification, 201);

        } catch (\Exception $e) {
            Log::error('Error creating notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании уведомления'], 500);
        }
    }

    // Удалить уведомление
    public function destroy($id, Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $notification = Notification::where('user_id', $user->id)->findOrFail($id);

            $notification->delete();

            Log::info('Notification deleted', [
                'notification_id' => $id,
                'user_id' => $user->id
            ]);

            return response()->json(['message' => 'Уведомление удалено']);

        } catch (\Exception $e) {
            Log::error("Error in NotificationController::destroy", [
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Уведомление не найдено'], 404);
        }
    }

    // Получить количество непрочитанных уведомлений
    public function getUnreadCount(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            $count = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json(['unread_count' => $count]);

        } catch (\Exception $e) {
            Log::error("Error in NotificationController::getUnreadCount", [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении количества уведомлений'], 500);
        }
    }

    /**
     * Получить количество непрочитанных уведомлений (алиас для getUnreadCount)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unreadCount(Request $request)
    {
        return $this->getUnreadCount($request);
    }

    // Получить последние уведомления
    public function getRecent(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $limit = $request->get('limit', 10);

            $notifications = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $unreadCount = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);

        } catch (\Exception $e) {
            Log::error("Error in NotificationController::getRecent", [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении последних уведомлений'], 500);
        }
    }

    // Массовая отправка уведомлений
    public function sendBulk(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'user_ids' => 'required|array',
                'user_ids.*' => 'exists:users,id',
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|string|max:50',
                'related_id' => 'nullable|integer',
                'related_type' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $created = [];
            $errors = [];

            foreach ($request->user_ids as $userId) {
                try {
                    $notification = Notification::create([
                        'user_id' => $userId,
                        'title' => $request->title,
                        'message' => $request->message,
                        'type' => $request->type,
                        'related_id' => $request->related_id,
                        'related_type' => $request->related_type,
                        'is_read' => false
                    ]);
                    $created[] = $notification;

                } catch (\Exception $e) {
                    $errors[] = "User ID {$userId}: " . $e->getMessage();
                }
            }

            Log::info('Bulk notifications sent', [
                'total_recipients' => count($request->user_ids),
                'created_count' => count($created),
                'errors_count' => count($errors)
            ]);

            return response()->json([
                'created' => $created,
                'errors' => $errors,
                'created_count' => count($created),
                'errors_count' => count($errors)
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending bulk notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при массовой отправке уведомлений'], 500);
        }
    }

    // Уведомления для конкретного класса
    public function sendToClass(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'school_class_id' => 'required|exists:school_classes,id',
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|string|max:50',
                'recipients' => 'required|array',
                'recipients.*' => 'in:students,parents,teachers',
                'related_id' => 'nullable|integer',
                'related_type' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $class = \App\Models\SchoolClass::findOrFail($request->class_id);
            $userIds = [];

            // Получаем ID пользователей в зависимости от получателей
            if (in_array('students', $request->recipients)) {
                $studentIds = $class->students()->pluck('user_id')->toArray();
                $userIds = array_merge($userIds, $studentIds);
            }

            if (in_array('parents', $request->recipients)) {
                $studentIds = $class->students()->pluck('user_id')->toArray();
                $parentIds = \App\Models\ParentStudent::whereIn('student_id', $studentIds)
                    ->pluck('parent_id')
                    ->toArray();
                $userIds = array_merge($userIds, $parentIds);
            }

            if (in_array('teachers', $request->recipients)) {
                $teacherIds = $class->schedules()->distinct()->pluck('teacher_id')->toArray();
                $userIds = array_merge($userIds, $teacherIds);
            }

            $userIds = array_unique($userIds);

            if (empty($userIds)) {
                return response()->json(['error' => 'Не найдено получателей для уведомления'], 400);
            }

            $created = [];
            $errors = [];

            foreach ($userIds as $userId) {
                try {
                    $notification = Notification::create([
                        'user_id' => $userId,
                        'title' => $request->title,
                        'message' => $request->message,
                        'type' => $request->type,
                        'related_id' => $request->related_id,
                        'related_type' => $request->related_type,
                        'is_read' => false
                    ]);
                    $created[] = $notification;

                } catch (\Exception $e) {
                    $errors[] = "User ID {$userId}: " . $e->getMessage();
                }
            }

            Log::info('Class notifications sent', [
                'school_class_id' => $request->class_id,
                'recipients' => $request->recipients,
                'total_recipients' => count($userIds),
                'created_count' => count($created),
                'errors_count' => count($errors)
            ]);

            return response()->json([
                'class' => $class->only(['id', 'name']),
                'created' => $created,
                'errors' => $errors,
                'created_count' => count($created),
                'errors_count' => count($errors)
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending class notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при отправке уведомлений классу'], 500);
        }
    }

    // Очистить старые уведомления
    public function cleanup(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'days' => 'nullable|integer|min:1|max:365',
                'read_only' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $days = $request->get('days', 30);
            $readOnly = $request->get('read_only', true);

            $query = Notification::where('created_at', '<', now()->subDays($days));

            if ($readOnly) {
                $query->where('is_read', true);
            }

            $deletedCount = $query->delete();

            Log::info('Notifications cleaned up', [
                'days' => $days,
                'read_only' => $readOnly,
                'deleted_count' => $deletedCount
            ]);

            return response()->json([
                'message' => 'Старые уведомления удалены',
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error cleaning up notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при очистке уведомлений'], 500);
        }
    }

    // Получить детали конкретного уведомления
    public function show($id, Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $notification = Notification::where('user_id', $user->id)->findOrFail($id);

            // Автоматически отмечаем как прочитанное при просмотре
            if (!$notification->is_read) {
                $notification->update(['is_read' => true]);
            }

            return response()->json($notification);

        } catch (\Exception $e) {
            Log::error("Error in NotificationController::show", [
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Уведомление не найдено'], 404);
        }
    }

    // Обновить уведомление (отметка как прочитанное)
    public function update(Request $request, $id)
    {
        try {
            $user = $request->attributes->get('user');
            $notification = Notification::where('user_id', $user->id)->findOrFail($id);

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'is_read' => 'sometimes|required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $notification->update($validator->validated());

            Log::info('Notification updated', [
                'notification_id' => $id,
                'user_id' => $user->id,
                'updated_fields' => $request->only(['is_read'])
            ]);

            return response()->json($notification);

        } catch (\Exception $e) {
            Log::error('Error updating notification', [
                'notification_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении уведомления'], 500);
        }
    }

    // Получить непрочитанные уведомления
    public function unread(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            $notifications = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->orderBy('created_at', 'desc')
                ->get();

            // Статистика
            $stats = [
                'total_unread' => $notifications->count(),
                'by_type' => $notifications->groupBy('type')->map->count(),
                'today_unread' => $notifications->where('created_at', '>=', now()->startOfDay())->count(),
                'this_week_unread' => $notifications->where('created_at', '>=', now()->startOfWeek())->count()
            ];

            return response()->json([
                'notifications' => $notifications,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in NotificationController::unread", [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении непрочитанных уведомлений'], 500);
        }
    }
}
