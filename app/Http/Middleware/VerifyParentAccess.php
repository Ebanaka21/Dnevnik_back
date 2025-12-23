<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ParentStudent;

/**
 * Middleware для проверки прав доступа родителей к данным учеников
 *
 * Проверяет:
 * - Роль пользователя (должен быть parent)
 * - Наличие активной связи с запрашиваемым учеником
 * - Существование student_id в параметрах маршрута
 *
 * @package App\Http\Middleware
 */
class VerifyParentAccess
{
    /**
     * Обработка входящего запроса
     *
     * Проверяет права доступа родителя к данным ученика.
     * Извлекает student_id из параметров маршрута и проверяет
     * наличие активной связи через модель ParentStudent.
     *
     * @param Request $request Входящий HTTP запрос
     * @param Closure $next Следующий middleware в цепочке
     * @return Response JSON ответ с ошибкой 403 или результат следующего middleware
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Получить текущего аутентифицированного пользователя
        $user = $request->user();

        // Проверка 1: Пользователь должен быть аутентифицирован
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Необходима аутентификация',
            ], 401);
        }

        // Проверка 2: Пользователь должен иметь роль 'parent'
        if ($user->role !== 'parent') {
            return response()->json([
                'success' => false,
                'message' => 'Доступ запрещен. Требуется роль родителя',
            ], 403);
        }

        // Проверка 3: Извлечь student_id из параметров маршрута
        $studentId = $request->route('studentId') ?? $request->route('student_id');

        if (!$studentId) {
            return response()->json([
                'success' => false,
                'message' => 'Не указан идентификатор ученика',
            ], 400);
        }

        // Проверка 4: Проверить наличие активной связи родитель-ученик
        $hasAccess = ParentStudent::where('parent_id', $user->id)
            ->where('student_id', $studentId)
            ->active()
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'У вас нет доступа к данным этого ученика',
            ], 403);
        }

        // Все проверки пройдены - передать управление следующему middleware
        return $next($request);
    }
}
