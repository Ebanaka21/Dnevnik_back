<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Grade;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class NotificationController extends Controller
{
    /**
     * Получение уведомлений пользователя
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = Auth::user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => Notification::where('user_id', $user->id)->where('is_read', false)->count()
        ]);
    }

    /**
     * Отметка уведомления как прочитанного
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Уведомление не найдено'
            ], 404);
        }

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Уведомление отмечено как прочитанное'
        ]);
    }

    /**
     * Создание уведомления о новой оценке
     */
    public function createGradeNotification(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'grade_id' => 'required|exists:grades,id'
        ]);

        $student = User::find($request->student_id);
        $grade = Grade::with(['subject', 'gradeType'])->find($request->grade_id);

        if (!$student || !$grade) {
            return response()->json([
                'success' => false,
                'message' => 'Данные не найдены'
            ], 404);
        }

        // Создаем уведомление для ученика
        $notification = Notification::create([
            'user_id' => $student->id,
            'title' => 'Новая оценка',
            'message' => "Получена оценка {$grade->grade} по предмету {$grade->subject->name}",
            'type' => 'grade',
            'data' => [
                'grade_id' => $grade->id,
                'subject_id' => $grade->subject_id,
                'grade' => $grade->grade,
                'grade_type' => $grade->gradeType->name
            ]
        ]);

        // Создаем уведомление для родителей
        $parents = $student->parents;
        foreach ($parents as $parent) {
            Notification::create([
                'user_id' => $parent->parent->id,
                'title' => 'Новая оценка у вашего ребенка',
                'message' => "Ваш ребенок получил оценку {$grade->grade} по предмету {$grade->subject->name}",
                'type' => 'grade',
                'data' => [
                    'student_id' => $student->id,
                    'grade_id' => $grade->id,
                    'subject_id' => $grade->subject_id,
                    'grade' => $grade->grade,
                    'grade_type' => $grade->gradeType->name
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Уведомления о новой оценке созданы',
            'data' => $notification
        ]);
    }

    /**
     * Создание уведомления о пропуске
     */
    public function createAttendanceNotification(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:users,id',
            'attendance_id' => 'required|exists:attendances,id'
        ]);

        $student = User::find($request->student_id);
        $attendance = Attendance::with(['lesson', 'lesson.subject'])->find($request->attendance_id);

        if (!$student || !$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Данные не найдены'
            ], 404);
        }

        $statusText = match($attendance->status) {
            'present' => 'присутствовал',
            'absent' => 'отсутствовал',
            'late' => 'опоздал',
            'excused' => 'отсутствовал (уважительная причина)',
            default => 'имеет статус'
        };

        // Создаем уведомление для ученика
        $notification = Notification::create([
            'user_id' => $student->id,
            'title' => 'Отметка о посещаемости',
            'message' => "Вы {$statusText} на уроке {$attendance->lesson->subject->name}",
            'type' => 'attendance',
            'data' => [
                'attendance_id' => $attendance->id,
                'lesson_id' => $attendance->lesson_id,
                'subject_id' => $attendance->lesson->subject_id,
                'status' => $attendance->status
            ]
        ]);

        // Создаем уведомление для родителей (только при отсутствии)
        if ($attendance->status === 'absent') {
            foreach ($student->parents as $parent) {
                Notification::create([
                    'user_id' => $parent->parent->id,
                    'title' => 'Пропуск занятий',
                    'message' => "Ваш ребенок отсутствовал на уроке {$attendance->lesson->subject->name}",
                    'type' => 'attendance',
                    'data' => [
                        'student_id' => $student->id,
                        'attendance_id' => $attendance->id,
                        'lesson_id' => $attendance->lesson_id,
                        'subject_id' => $attendance->lesson->subject_id,
                        'status' => $attendance->status
                    ]
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Уведомления о посещаемости созданы',
            'data' => $notification
        ]);
    }

    /**
     * Создание уведомления о новом задании
     */
    public function createHomeworkNotification(Request $request): JsonResponse
    {
        $request->validate([
            'homework_id' => 'required|exists:homeworks,id',
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:users,id'
        ]);

        $homework = Homework::with(['subject', 'lesson'])->find($request->homework_id);

        if (!$homework) {
            return response()->json([
                'success' => false,
                'message' => 'Задание не найдено'
            ], 404);
        }

        $notifications = [];

        foreach ($request->student_ids as $studentId) {
            $student = User::find($studentId);

            // Уведомление для ученика
            $notification = Notification::create([
                'user_id' => $studentId,
                'title' => 'Новое домашнее задание',
                'message' => "Поставлено новое задание по предмету {$homework->subject->name}",
                'type' => 'homework',
                'data' => [
                    'homework_id' => $homework->id,
                    'subject_id' => $homework->subject_id,
                    'title' => $homework->title,
                    'due_date' => $homework->due_date
                ]
            ]);

            $notifications[] = $notification;
        }

        return response()->json([
            'success' => true,
            'message' => 'Уведомления о новом задании созданы',
            'data' => $notifications
        ]);
    }

    /**
     * Отметка всех уведомлений как прочитанных
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Все уведомления отмечены как прочитанные'
        ]);
    }

    /**
     * Удаление уведомления
     */
    public function destroy($id): JsonResponse
    {
        $user = Auth::user();

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Уведомление не найдено'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Уведомление удалено'
        ]);
    }
}
