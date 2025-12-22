<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherComment;
use App\Models\Grade;
use App\Models\Homework;
use AppModelsAttendance;
use App\Models\User;
use App\Models\ParentStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TeacherCommentController extends Controller
{
    /**
     * Получить список комментариев с фильтрацией
     */
    public function index(Request $request)
    {
        try {
            $query = TeacherComment::with([
                'user:id,name,email',
                'commentable' => function($morphTo) {
                    $morphTo->morphWith([
                        Grade::class => ['student:id,name,email', 'subject:id,name'],
                        Homework::class => ['subject:id,name', 'schoolClass:id,name,grade']
                    ]);
                }
            ]);

            // Фильтрация по типу сущности
            if ($request->has('commentable_type') && !empty($request->commentable_type)) {
                $query->where('commentable_type', $request->commentable_type);
            }

            // Фильтрация по ID сущности
            if ($request->has('commentable_id') && !empty($request->commentable_id)) {
                $query->where('commentable_id', $request->commentable_id);
            }

            // Фильтрация по видимости для ученика
            if ($request->has('visible_to_student') && $request->visible_to_student !== null) {
                $query->where('is_visible_to_student', $request->boolean('visible_to_student'));
            }

            // Фильтрация по видимости для родителя
            if ($request->has('visible_to_parent') && $request->visible_to_parent !== null) {
                $query->where('is_visible_to_parent', $request->boolean('visible_to_parent'));
            }

            // Фильтрация по автору комментария
            if ($request->has('user_id') && !empty($request->user_id)) {
                $query->where('user_id', $request->user_id);
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $comments = $query->paginate(20);

            return response()->json([
                'data' => $comments->items(),
                'pagination' => [
                    'current_page' => $comments->currentPage(),
                    'last_page' => $comments->lastPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in TeacherCommentController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении комментариев'], 500);
        }
    }

    /**
     * Создать новый комментарий
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'commentable_type' => 'required|string|in:App\Models\Grade,App\Models\Homework',
                'commentable_id' => 'required|integer',
                'content' => 'required|string|max:1000',
                'is_visible_to_student' => 'boolean',
                'is_visible_to_parent' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Проверяем существование связанной сущности
            $commentableType = $request->commentable_type;
            $commentableId = $request->commentable_id;

            if ($commentableType === 'App\Models\Grade') {
                $commentable = Grade::find($commentableId);
                if (!$commentable) {
                    return response()->json(['error' => 'Оценка не найдена'], 404);
                }
            } elseif ($commentableType === 'App\Models\Homework') {
                $commentable = Homework::find($commentableId);
                if (!$commentable) {
                    return response()->json(['error' => 'Задание не найдено'], 404);
                }
            }

            // Проверяем, что пользователь является учителем
            $user = User::findOrFail($request->user()->id);
            if ($user->role->name !== 'teacher') {
                return response()->json(['error' => 'Только учителя могут создавать комментарии'], 403);
            }

            $commentData = array_merge($validator->validated(), [
                'user_id' => $request->user()->id,
                'is_visible_to_student' => $request->boolean('is_visible_to_student', true),
                'is_visible_to_parent' => $request->boolean('is_visible_to_parent', false),
            ]);

            $comment = TeacherComment::create($commentData);

            // Загружаем связанные данные
            $comment->load([
                'user:id,name,email',
                'commentable' => function($morphTo) {
                    $morphTo->morphWith([
                        Grade::class => ['student:id,name,email', 'subject:id,name'],
                        Homework::class => ['subject:id,name', 'schoolClass:id,name,grade']
                    ]);
                }
            ]);

            Log::info('Teacher comment created successfully', [
                'comment_id' => $comment->id,
                'commentable_type' => $comment->commentable_type,
                'commentable_id' => $comment->commentable_id,
                'user_id' => $comment->user_id
            ]);

            return response()->json($comment, 201);

        } catch (\Exception $e) {
            Log::error('Error creating teacher comment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании комментария'], 500);
        }
    }

    /**
     * Получить детали конкретного комментария
     */
    public function show($id)
    {
        try {
            $comment = TeacherComment::with([
                'user:id,name,email',
                'commentable' => function($morphTo) {
                    $morphTo->morphWith([
                        Grade::class => ['student:id,name,email', 'subject:id,name'],
                        Homework::class => ['subject:id,name', 'schoolClass:id,name,grade']
                    ]);
                }
            ])->findOrFail($id);

            return response()->json($comment);

        } catch (\Exception $e) {
            Log::error("Error in TeacherCommentController::show", [
                'comment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Комментарий не найден'], 404);
        }
    }

    /**
     * Обновить комментарий
     */
    public function update(Request $request, $id)
    {
        try {
            $comment = TeacherComment::findOrFail($id);

            // Проверяем права доступа - только автор может редактировать
            if ($comment->user_id !== $request->user()->id) {
                return response()->json(['error' => 'Нет прав для редактирования этого комментария'], 403);
            }

            $validator = Validator::make($request->all(), [
                'content' => 'sometimes|required|string|max:1000',
                'is_visible_to_student' => 'boolean',
                'is_visible_to_parent' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $updateData = $validator->validated();
            if (isset($updateData['is_visible_to_student'])) {
                $updateData['is_visible_to_student'] = $request->boolean('is_visible_to_student');
            }
            if (isset($updateData['is_visible_to_parent'])) {
                $updateData['is_visible_to_parent'] = $request->boolean('is_visible_to_parent');
            }

            $comment->update($updateData);
            $comment->load([
                'user:id,name,email',
                'commentable' => function($morphTo) {
                    $morphTo->morphWith([
                        Grade::class => ['student:id,name,email', 'subject:id,name'],
                        Homework::class => ['subject:id,name', 'schoolClass:id,name,grade']
                    ]);
                }
            ]);

            Log::info('Teacher comment updated successfully', [
                'comment_id' => $comment->id,
                'updated_fields' => array_keys($updateData)
            ]);

            return response()->json($comment);

        } catch (\Exception $e) {
            Log::error('Error updating teacher comment', [
                'comment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении комментария'], 500);
        }
    }

    /**
     * Удалить комментарий
     */
    public function destroy($id)
    {
        try {
            $comment = TeacherComment::findOrFail($id);

            // Проверяем права доступа - только автор может удалить
            if ($comment->user_id !== request()->user()->id) {
                return response()->json(['error' => 'Нет прав для удаления этого комментария'], 403);
            }

            $comment->delete();

            Log::info('Teacher comment deleted successfully', [
                'comment_id' => $id
            ]);

            return response()->json(['message' => 'Комментарий успешно удален']);

        } catch (\Exception $e) {
            Log::error('Error deleting teacher comment', [
                'comment_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении комментария'], 500);
        }
    }

    /**
     * Получить комментарии к оценке
     */
    public function byGrade($gradeId)
    {
        try {
            $grade = Grade::findOrFail($gradeId);

            $comments = TeacherComment::with(['user:id,name,email'])
                ->where('commentable_type', 'App\Models\Grade')
                ->where('commentable_id', $gradeId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'grade' => $grade->load(['student:id,name,email', 'subject:id,name']),
                'comments' => $comments
            ]);

        } catch (\Exception $e) {
            Log::error("Error in TeacherCommentController::byGrade", [
                'grade_id' => $gradeId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Оценка не найдена или ошибка при получении комментариев'], 404);
        }
    }

    /**
     * Получить комментарии к заданию
     */
    public function byHomework($homeworkId)
    {
        try {
            $homework = Homework::findOrFail($homeworkId);

            $comments = TeacherComment::with(['user:id,name,email'])
                ->where('commentable_type', 'App\Models\Homework')
                ->where('commentable_id', $homeworkId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'homework' => $homework->load(['subject:id,name', 'schoolClass:id,name,grade']),
                'comments' => $comments
            ]);

        } catch (\Exception $e) {
            Log::error("Error in TeacherCommentController::byHomework", [
                'homework_id' => $homeworkId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Задание не найдено или ошибка при получении комментариев'], 404);
        }
    }

    /**
     * Получить комментарии, видимые ученику
     */
    public function visibleToStudent($studentId)
    {
        try {
            // Проверяем, что пользователь является учеником
            $student = User::findOrFail($studentId);
            if ($student->role->name !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            $comments = TeacherComment::with([
                'user:id,name,email',
                'commentable' => function($morphTo) {
                    $morphTo->morphWith([
                        Grade::class => ['student:id,name,email', 'subject:id,name'],
                        Homework::class => ['subject:id,name', 'schoolClass:id,name,grade']
                    ]);
                }
            ])
            ->where('is_visible_to_student', true)
            ->whereHas('commentable', function($query) use ($studentId) {
                $query->where(function($q) use ($studentId) {
                    $q->where('commentable_type', 'App\Models\Grade')
                      ->whereHasMorph('commentable', ['App\Models\Grade'], function($gradeQuery) use ($studentId) {
                          $gradeQuery->where('student_id', $studentId);
                      });
                })->orWhere(function($q) use ($studentId) {
                    $q->where('commentable_type', 'App\Models\Homework')
                      ->whereHasMorph('commentable', ['App\Models\Homework'], function($homeworkQuery) use ($studentId) {
                          $homeworkQuery->whereHas('schoolClass.students', function($studentQuery) use ($studentId) {
                              $studentQuery->where('user_id', $studentId);
                          });
                      });
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json([
                'student' => $student->only(['id', 'name', 'email']),
                'comments' => $comments
            ]);

        } catch (\Exception $e) {
            Log::error("Error in TeacherCommentController::visibleToStudent", [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении комментариев для ученика'], 500);
        }
    }

    /**
     * Получить комментарии, видимые родителю
     */
    public function visibleToParent($parentId)
    {
        try {
            // Проверяем, что пользователь является родителем
            $parent = User::findOrFail($parentId);
            if ($parent->role->name !== 'parent') {
                return response()->json(['error' => 'Пользователь не является родителем'], 400);
            }

            // Получаем список детей родителя
            $children = ParentStudent::where('parent_id', $parentId)->pluck('student_id');

            $comments = TeacherComment::with([
                'user:id,name,email',
                'commentable' => function($morphTo) {
                    $morphTo->morphWith([
                        Grade::class => ['student:id,name,email', 'subject:id,name'],
                        Homework::class => ['subject:id,name', 'schoolClass:id,name,grade']
                    ]);
                }
            ])
            ->where('is_visible_to_parent', true)
            ->whereHas('commentable', function($query) use ($children) {
                $query->where(function($q) use ($children) {
                    $q->where('commentable_type', 'App\Models\Grade')
                      ->whereHasMorph('commentable', ['App\Models\Grade'], function($gradeQuery) use ($children) {
                          $gradeQuery->whereIn('student_id', $children);
                      });
                })->orWhere(function($q) use ($children) {
                    $q->where('commentable_type', 'App\Models\Homework')
                      ->whereHasMorph('commentable', ['App\Models\Homework'], function($homeworkQuery) use ($children) {
                          $homeworkQuery->whereHas('schoolClass.students', function($studentQuery) use ($children) {
                              $studentQuery->whereIn('user_id', $children);
                          });
                      });
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

            return response()->json([
                'parent' => $parent->only(['id', 'name', 'email']),
                'children' => $children,
                'comments' => $comments
            ]);

        } catch (\Exception $e) {
            Log::error("Error in TeacherCommentController::visibleToParent", [
                'parent_id' => $parentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении комментариев для родителя'], 500);
        }
    }
}
