<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TeacherNotificationsController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Получить уведомления учителя с расширенной фильтрацией
     */
    public function index(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            $query = Notification::where('user_id', $user->id);

            // Фильтрация по типу (поддержка массива)
            if ($request->has('type') && !empty($request->type)) {
                $types = is_array($request->type) ? $request->type : explode(',', $request->type);
                $query->whereIn('type', $types);
            }

            // Фильтрация по приоритету (высчитывается автоматически на основе типа)
            if ($request->has('priority') && !empty($request->priority)) {
                $priorities = is_array($request->priority) ? $request->priority : explode(',', $request->priority);
                // Приоритеты: high, medium, low
                $query->where(function($q) use ($priorities) {
                    foreach ($priorities as $priority) {
                        $q->orWhere('data->priority', $priority);
                    }
                });
            }

            // Фильтрация по статусу прочтения
            if ($request->has('is_read') && $request->is_read !== '') {
                $isRead = filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN);
                $query->where('is_read', $isRead);
            }

            // Фильтрация по дате
            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('created_at', '<=', $request->date_to);
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

            $allowedSortFields = ['created_at', 'title', 'type', 'is_read'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Пагинация
            $perPage = $request->get('per_page', 20);
            $notifications = $query->paginate($perPage);

            // Обогащаем данные уведомлений информацией о приоритете
            $notifications->getCollection()->transform(function ($notification) {
                $notification->priority = $this->calculatePriority($notification);
                return $notification;
            });

            // Статистика для учителей
            $stats = $this->getTeacherNotificationStats($user->id);

            return response()->json([
                'data' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total()
                ],
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in TeacherNotificationsController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении уведомлений'], 500);
        }
    }

    /**
     * Получить уведомления в реальном времени через WebSocket
     */
    public function getRealtimeNotifications(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $lastCheck = $request->get('last_check', now()->subMinutes(5)->toISOString());

            // Получаем новые уведомления с последней проверки
            $newNotifications = Notification::where('user_id', $user->id)
                ->where('created_at', '>', $lastCheck)
                ->orderBy('created_at', 'desc')
                ->get();

            // Получаем количество непрочитанных
            $unreadCount = Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'new_notifications' => $newNotifications,
                'unread_count' => $unreadCount,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error("Error in TeacherNotificationsController::getRealtimeNotifications", [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении уведомлений в реальном времени'], 500);
        }
    }

    /**
     * Создать уведомление для учителя (для внутреннего использования)
     */
    public function create(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'required|string|in:GRADES_NOT_SET,ATTENDANCE_NOT_SET,STUDENT_ABSENT,HOMEWORK_OVERDUE,GRADE_CONFLICT,SYSTEM_INFO,IMPORTANT',
                'user_id' => 'required|exists:users,id',
                'related_id' => 'nullable|integer',
                'related_type' => 'nullable|string|max:100',
                'data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $notification = Notification::create([
                'user_id' => $request->user_id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'related_id' => $request->related_id,
                'related_type' => $request->related_type,
                'data' => $request->data ?? [],
                'is_read' => false
            ]);

            // Добавляем информацию о приоритете
            $notification->priority = $this->calculatePriority($notification);

            Log::info('Teacher notification created', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'type' => $notification->type
            ]);

            return response()->json($notification, 201);

        } catch (\Exception $e) {
            Log::error('Error creating teacher notification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при создании уведомления'], 500);
        }
    }

    /**
     * Массовые действия с уведомлениями
     */
    public function bulkActions(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'notification_ids' => 'required|array',
                'notification_ids.*' => 'exists:notifications,id',
                'action' => 'required|string|in:mark_as_read,mark_as_unread,delete'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $notificationIds = $request->notification_ids;
            $action = $request->action;
            $affectedCount = 0;

            switch ($action) {
                case 'mark_as_read':
                    $affectedCount = Notification::where('user_id', $user->id)
                        ->whereIn('id', $notificationIds)
                        ->where('is_read', false)
                        ->update(['is_read' => true]);
                    break;

                case 'mark_as_unread':
                    $affectedCount = Notification::where('user_id', $user->id)
                        ->whereIn('id', $notificationIds)
                        ->where('is_read', true)
                        ->update(['is_read' => false]);
                    break;

                case 'delete':
                    $affectedCount = Notification::where('user_id', $user->id)
                        ->whereIn('id', $notificationIds)
                        ->delete();
                    break;
            }

            Log::info('Bulk notification action performed', [
                'user_id' => $user->id,
                'action' => $action,
                'notification_ids' => $notificationIds,
                'affected_count' => $affectedCount
            ]);

            return response()->json([
                'message' => "Выполнено действие '{$action}' для {$affectedCount} уведомлений",
                'affected_count' => $affectedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error performing bulk notification action', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при выполнении массового действия'], 500);
        }
    }

    /**
     * Запустить автоматическую проверку уведомлений
     */
    public function runAutoChecks(Request $request)
    {
        try {
            $user = $request->attributes->get('user');

            // Проверяем, что пользователь - учитель
            if ($user->role !== 'teacher') {
                return response()->json(['error' => 'Доступ только для учителей'], 403);
            }

            $this->notificationService->runAutomaticChecks();

            return response()->json([
                'message' => 'Автоматическая проверка уведомлений выполнена'
            ]);

        } catch (\Exception $e) {
            Log::error('Error running auto checks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при выполнении автоматической проверки'], 500);
        }
    }

    /**
     * Получить статистику уведомлений для учителя
     */
    public function getStats(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $stats = $this->getTeacherNotificationStats($user->id);

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error("Error in TeacherNotificationsController::getStats", [
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении статистики'], 500);
        }
    }

    /**
     * Рассчитать приоритет уведомления на основе типа
     */
    private function calculatePriority($notification)
    {
        $highPriorityTypes = ['STUDENT_ABSENT', 'GRADE_CONFLICT', 'ATTENDANCE_NOT_SET'];
        $mediumPriorityTypes = ['HOMEWORK_OVERDUE', 'IMPORTANT'];

        if (in_array($notification->type, $highPriorityTypes)) {
            return 'high';
        } elseif (in_array($notification->type, $mediumPriorityTypes)) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Получить статистику уведомлений для учителя
     */
    private function getTeacherNotificationStats($userId)
    {
        $totalNotifications = Notification::where('user_id', $userId)->count();
        $unreadNotifications = Notification::where('user_id', $userId)->where('is_read', false)->count();

        // Статистика по типам уведомлений
        $byType = Notification::where('user_id', $userId)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // Статистика по приоритетам
        $allNotifications = Notification::where('user_id', $userId)->get();
        $byPriority = [
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];

        foreach ($allNotifications as $notification) {
            $priority = $this->calculatePriority($notification);
            $byPriority[$priority]++;
        }

        // Уведомления за последние 24 часа
        $todayNotifications = Notification::where('user_id', $userId)
            ->whereDate('created_at', today())
            ->count();

        return [
            'total' => $totalNotifications,
            'unread' => $unreadNotifications,
            'today' => $todayNotifications,
            'by_type' => $byType,
            'by_priority' => $byPriority
        ];
    }
}
