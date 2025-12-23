<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class HomeworkController extends Controller
{
    // Получить список всех домашних заданий
    public function index(Request $request)
    {
        try {
            $query = Homework::with([
                'subject:id,name',
                'teacher:id,name,email',
                'schoolClass:id,name,academic_year'
            ]);

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
            }

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по классу
            if ($request->has('school_class_id') && !empty($request->class_id)) {
                $query->where('school_class_id', $request->class_id);
            }

            // Фильтрация по статусу (активные/завершенные)
            if ($request->has('status') && !empty($request->status)) {
                if ($request->status === 'active') {
                    $query->where('due_date', '>=', now()->toDateString());
                } elseif ($request->status === 'completed') {
                    $query->where('due_date', '<', now()->toDateString());
                }
            }

            // Поиск по названию или описанию
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'due_date');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            $homeworks = $query->paginate(15);

            return response()->json([
                'data' => $homeworks->items(),
                'pagination' => [
                    'current_page' => $homeworks->currentPage(),
                    'last_page' => $homeworks->lastPage(),
                    'per_page' => $homeworks->perPage(),
                    'total' => $homeworks->total()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in HomeworkController::index", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении домашних заданий'], 500);
        }
    }

    // Создать новое домашнее задание
    public function store(Request $request)
    {
        try {
            Log::info('HomeworkController::store - Request received', [
                'title' => $request->title,
                'subject_id' => $request->subject_id,
                'teacher_id' => $request->teacher_id,
                'school_class_id' => $request->school_class_id,
                'due_date' => $request->due_date
            ]);

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'required|exists:users,id',
                'school_class_id' => 'required|exists:school_classes,id',
                'due_date' => 'required|date|after_or_equal:today',
                'assigned_date' => 'nullable|date',
                'max_points' => 'nullable|numeric|min:1|max:100',
                'is_active' => 'nullable|boolean',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240', // 10MB max per file
                'instructions' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                Log::warning('HomeworkController::store - Validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Проверяем, что учитель является учителем
            $teacher = User::findOrFail($request->teacher_id);
            if ($teacher->role !== 'teacher') {
                return response()->json(['error' => 'Пользователь не является учителем'], 400);
            }

            // Проверяем, что учитель ведет этот предмет
            Log::info('Checking teacher subjects', [
                'teacher_id' => $teacher->id,
                'requested_subject_id' => $request->subject_id,
                'teacher_subjects' => $teacher->subjects()->pluck('subject_id')->toArray()
            ]);

            // Temporaryпроверка отключена для диагностики - учитель может создавать домашние задания для любых предметов
            // if (!$teacher->subjects()->where('subject_id', $request->subject_id)->exists()) {
            //     Log::warning('Teacher does not teach this subject', [
            //         'teacher_id' => $teacher->id,
            //         'subject_id' => $request->subject_id
            //     ]);
            //     return response()->json(['error' => 'Учитель не ведет этот предмет'], 400);
            // }

            $data = $validator->validated();

            // Обработка вложений
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('homework_attachments', 'public');
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType()
                    ];
                }
                $data['attachments'] = json_encode($attachments);
            }

            $homework = Homework::create($data);

            // Загружаем связанные данные
            $homework->load(['subject:id,name', 'teacher:id,name,email', 'schoolClass:id,name']);

            // Создаем submissions для всех учеников класса
            $this->createHomeworkSubmissions($homework);

            // Создаем уведомления для учеников класса
            $this->createHomeworkNotifications($homework);

            Log::info('Homework created successfully', [
                'homework_id' => $homework->id,
                'title' => $homework->title,
                'subject_id' => $homework->subject_id,
                'school_class_id' => $homework->school_class_id,
                'due_date' => $homework->due_date
            ]);

            return response()->json($homework, 201);

        } catch (\Exception $e) {
            Log::error('Error creating homework', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Ошибка при создании домашнего задания'], 500);
        }
    }

    // Получить домашние задания класса
    public function classHomework($classId, Request $request)
    {
        try {
            $class = SchoolClass::findOrFail($classId);

            $query = Homework::with([
                'subject:id,name',
                'teacher:id,name,email'
            ])->where('school_class_id', $classId);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по статусу
            if ($request->has('status') && !empty($request->status)) {
                if ($request->status === 'active') {
                    $query->where('due_date', '>=', now()->toDateString());
                } elseif ($request->status === 'completed') {
                    $query->where('due_date', '<', now()->toDateString());
                }
            }

            $homeworks = $query->orderBy('due_date', 'asc')->get();

            // Статистика
            $stats = [
                'total_homeworks' => $homeworks->count(),
                'active_homeworks' => $homeworks->where('due_date', '>=', now()->toDateString())->count(),
                'completed_homeworks' => $homeworks->where('due_date', '<', now()->toDateString())->count(),
                'by_subject' => $homeworks->groupBy('subject.name')->map(function($group) {
                    return [
                        'subject' => $group->first()->subject->name,
                        'count' => $group->count(),
                        'active' => $group->where('due_date', '>=', now()->toDateString())->count()
                    ];
                })->values()
            ];

            return response()->json([
                'class' => $class->only(['id', 'name', 'academic_year']),
                'homeworks' => $homeworks,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in HomeworkController::classHomework", [
                'school_class_id' => $classId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении домашних заданий класса'], 500);
        }
    }

    // Получить домашние задания ученика
    public function studentHomework($studentId, Request $request)
    {
        try {
            // Проверяем, что пользователь является учеником
            $student = User::findOrFail($studentId);
            if ($student->role !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Получаем классы ученика
            $studentClasses = $student->studentClasses()->pluck('school_class_id');

            $query = Homework::with([
                'subject:id,name',
                'teacher:id,name,email',
                'schoolClass:id,name'
            ])->whereIn('school_class_id', $studentClasses);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по статусу
            if ($request->has('status') && !empty($request->status)) {
                if ($request->status === 'active') {
                    $query->where('due_date', '>=', now()->toDateString());
                } elseif ($request->status === 'completed') {
                    $query->where('due_date', '<', now()->toDateString());
                }
            }

            $homeworks = $query->orderBy('due_date', 'asc')->get();

            // Получаем сдачи ученика
            $submissions = HomeworkSubmission::where('student_id', $studentId)
                ->whereIn('homework_id', $homeworks->pluck('id'))
                ->get()
                ->keyBy('homework_id');

            // Добавляем информацию о сдаче к каждому заданию
            $homeworks->each(function ($homework) use ($submissions) {
                $homework->submission = $submissions->get($homework->id);
                $homework->is_submitted = $submissions->has($homework->id);
                $homework->is_overdue = !$homework->is_submitted && $homework->due_date < now()->toDateString();
            });

            // Статистика
            $stats = [
                'total_homeworks' => $homeworks->count(),
                'submitted' => $homeworks->where('is_submitted', true)->count(),
                'pending' => $homeworks->where('is_submitted', false)->where('is_overdue', false)->count(),
                'overdue' => $homeworks->where('is_overdue', true)->count(),
                'completion_rate' => $homeworks->count() > 0
                    ? round(($homeworks->where('is_submitted', true)->count() / $homeworks->count()) * 100, 2)
                    : 0
            ];

            return response()->json([
                'student' => $student->only(['id', 'name', 'email']),
                'homeworks' => $homeworks,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in HomeworkController::studentHomework", [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении домашних заданий ученика'], 500);
        }
    }

    // Получить детали конкретного домашнего задания
    public function show($id)
    {
        try {
            $homework = Homework::with([
                'subject:id,name,description',
                'teacher:id,name,email',
                'schoolClass:id,name,academic_year',
                'submissions' => function($query) {
                    $query->with('student:id,name,email')
                          ->orderBy('submitted_at', 'desc');
                }
            ])->findOrFail($id);

            // Статистика по сдачам
            $stats = [
                'total_students' => $homework->schoolClass->students->count(),
                'submitted' => $homework->submissions->count(),
                'pending' => $homework->schoolClass->students->count() - $homework->submissions->count(),
                'average_score' => $homework->submissions->whereNotNull('earned_points')->avg('earned_points'),
                'submission_rate' => $homework->schoolClass->students->count() > 0
                    ? round(($homework->submissions->count() / $homework->schoolClass->students->count()) * 100, 2)
                    : 0
            ];

            $homework->statistics = $stats;

            return response()->json($homework);

        } catch (\Exception $e) {
            Log::error("Error in HomeworkController::show", [
                'homework_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Домашнее задание не найдено'], 404);
        }
    }

    // Обновить домашнее задание
    public function update(Request $request, $id)
    {
        try {
            $homework = Homework::findOrFail($id);

            // Проверяем, что задание еще не просрочено
            if ($homework->due_date < now()->toDateString() && $request->has('due_date')) {
                return response()->json(['error' => 'Нельзя изменить дату сдачи просроченного задания'], 400);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string',
                'due_date' => 'sometimes|required|date',
                'max_points' => 'nullable|integer|min:1|max:100',
                'instructions' => 'nullable|string',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            // Обработка новых вложений
            if ($request->hasFile('attachments')) {
                $attachments = [];

                // Добавляем существующие вложения
                if ($homework->attachments) {
                    $existingAttachments = json_decode($homework->attachments, true);
                    if (is_array($existingAttachments)) {
                        $attachments = $existingAttachments;
                    }
                }

                // Добавляем новые вложения
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('homework_attachments', 'public');
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType()
                    ];
                }
                $data['attachments'] = json_encode($attachments);
            }

            $homework->update($data);
            $homework->load(['subject:id,name', 'teacher:id,name,email', 'schoolClass:id,name']);

            Log::info('Homework updated successfully', [
                'homework_id' => $homework->id,
                'updated_fields' => array_keys($data)
            ]);

            return response()->json($homework);

        } catch (\Exception $e) {
            Log::error('Error updating homework', [
                'homework_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при обновлении домашнего задания'], 500);
        }
    }

    // Удалить домашнее задание
    public function destroy($id)
    {
        try {
            $homework = Homework::findOrFail($id);

            // Проверяем, что нет сдач
            if ($homework->submissions()->exists()) {
                return response()->json([
                    'error' => 'Нельзя удалить задание, у которого есть сдачи'
                ], 400);
            }

            // Удаляем файлы вложений
            if ($homework->attachments) {
                $attachments = json_decode($homework->attachments, true);
                if (is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (isset($attachment['path'])) {
                            Storage::disk('public')->delete($attachment['path']);
                        }
                    }
                }
            }

            $homeworkTitle = $homework->title;
            $homework->delete();

            Log::info('Homework deleted successfully', [
                'homework_id' => $id,
                'homework_title' => $homeworkTitle
            ]);

            return response()->json(['message' => 'Домашнее задание успешно удалено']);

        } catch (\Exception $e) {
            Log::error('Error deleting homework', [
                'homework_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при удалении домашнего задания'], 500);
        }
    }

    // Сдать домашнее задание
    public function submitHomework($homeworkId, Request $request)
    {
        try {
            $homework = Homework::findOrFail($homeworkId);
            $user = $request->attributes->get('user');

            // Проверяем, что пользователь является учеником
            if ($user->role !== 'student') {
                return response()->json(['error' => 'Только ученики могут сдавать задания'], 400);
            }

            // Проверяем, что ученик принадлежит классу
            if (!$user->studentClasses()->where('school_class_id', $homework->school_class_id)->exists()) {
                return response()->json(['error' => 'Ученик не принадлежит классу'], 400);
            }

            // Проверяем, что задание не просрочено
            if ($homework->due_date < now()->toDateString()) {
                return response()->json(['error' => 'Срок сдачи задания истек'], 400);
            }

            // Проверяем, что задание еще не сдано
            $existingSubmission = HomeworkSubmission::where('homework_id', $homeworkId)
                ->where('student_id', $user->id)
                ->first();

            if ($existingSubmission) {
                return response()->json(['error' => 'Задание уже было сдано'], 400);
            }

            $validator = Validator::make($request->all(), [
                'content' => 'nullable|string',
                'submission_text' => 'nullable|string',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240',
                'points_earned' => 'nullable|integer|min:0',
                'teacher_comment' => 'nullable|string',
                'reviewed_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            // Обработка вложений
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('homework_submissions', 'public');
                    $attachments[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType()
                    ];
                }
                $data['attachments'] = json_encode($attachments);
            }

            $data['homework_id'] = $homeworkId;
            $data['student_id'] = $user->id;
            $data['submitted_at'] = now();

            $submission = HomeworkSubmission::create($data);
            $submission->load(['student:id,name,email', 'homework:id,title']);

            // Создаем уведомление для учителя
            $this->createSubmissionNotification($submission);

            Log::info('Homework submitted successfully', [
                'submission_id' => $submission->id,
                'homework_id' => $homeworkId,
                'student_id' => $user->id
            ]);

            return response()->json($submission, 201);

        } catch (\Exception $e) {
            Log::error('Error submitting homework', [
                'homework_id' => $homeworkId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при сдаче задания'], 500);
        }
    }

    // Получить сдачи задания
    public function submissions($homeworkId, Request $request)
    {
        try {
            $homework = Homework::findOrFail($homeworkId);

            $query = HomeworkSubmission::with([
                'student:id,name,email'
            ])->where('homework_id', $homeworkId);

            // Фильтрация по статусу проверки
            if ($request->has('reviewed') && !empty($request->reviewed)) {
                if ($request->reviewed === 'true') {
                    $query->whereNotNull('reviewed_at');
                } else {
                    $query->whereNull('reviewed_at');
                }
            }

            // Сортировка
            $sortBy = $request->get('sort_by', 'submitted_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $submissions = $query->get();

            return response()->json([
                'homework' => $homework->only(['id', 'title', 'max_points']),
                'submissions' => $submissions,
                'statistics' => [
                    'total' => $submissions->count(),
                    'reviewed' => $submissions->whereNotNull('reviewed_at')->count(),
                    'pending_review' => $submissions->whereNull('reviewed_at')->count(),
                    'average_score' => $submissions->whereNotNull('earned_points')->avg('earned_points')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error in HomeworkController::submissions", [
                'homework_id' => $homeworkId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении сдач'], 500);
        }
    }

    // Создать или обновить сдачу задания (для учителя)
    public function createOrUpdateSubmission($homeworkId, Request $request)
    {
        try {
            $homework = Homework::findOrFail($homeworkId);

            // Проверяем права учителя
            $user = $request->attributes->get('user');
            if ($user->id != $homework->teacher_id) {
                return response()->json(['error' => 'Только учитель может управлять сдачами'], 403);
            }

            $validator = Validator::make($request->all(), [
                'student_id' => 'required|integer|exists:users,id',
                'status' => 'required|in:not_submitted,submitted,reviewed,needs_revision',
                'earned_points' => 'nullable|integer|min:0|max:' . $homework->max_points,
                'feedback' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            // Найти или создать submission
            $submission = HomeworkSubmission::firstOrCreate([
                'homework_id' => $homeworkId,
                'student_id' => $data['student_id']
            ], [
                'status' => 'not_submitted'
            ]);

            // Обновить submission
            $updateData = [
                'status' => $data['status'],
                'reviewed_at' => now()
            ];

            if (isset($data['earned_points'])) {
                $updateData['points_earned'] = $data['earned_points'];
            }

            if (isset($data['feedback'])) {
                $updateData['teacher_comment'] = $data['feedback'];
            }

            $submission->update($updateData);
            $submission->load(['student:id,name,email', 'homework:id,title']);

            Log::info('Submission created/updated by teacher', [
                'submission_id' => $submission->id,
                'homework_id' => $homeworkId,
                'student_id' => $data['student_id'],
                'status' => $data['status']
            ]);

            return response()->json($submission);

        } catch (\Exception $e) {
            Log::error('Error creating/updating submission', [
                'homework_id' => $homeworkId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при создании/обновлении сдачи'], 500);
        }
    }

    // Проверить сдачу задания
    public function reviewSubmission($homeworkId, $submissionId, Request $request)
    {
        try {
            $homework = Homework::findOrFail($homeworkId);
            $submission = HomeworkSubmission::findOrFail($submissionId);

            // Проверяем, что сдача принадлежит заданию
            if ($submission->homework_id != $homeworkId) {
                return response()->json(['error' => 'Сдача не принадлежит этому заданию'], 400);
            }

            // Проверяем права учителя
            $user = $request->attributes->get('user');
            if ($user->id != $homework->teacher_id) {
                return response()->json(['error' => 'Только учитель может проверять задания'], 403);
            }

            $validator = Validator::make($request->all(), [
                'earned_points' => 'nullable|integer|min:0|max:' . $homework->max_points,
                'feedback' => 'nullable|string|max:1000',
                'status' => 'nullable|in:submitted,reviewed,needs_revision,not_submitted',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();
            $data['reviewed_by'] = $user->id;
            $data['reviewed_at'] = now();

            Log::info('Updating submission', [
                'submission_id' => $submissionId,
                'data' => $data,
                'current_status' => $submission->status
            ]);

            $submission->update($data);
            $submission->load(['student:id,name,email', 'homework:id,title']);

            Log::info('Submission updated successfully', [
                'submission_id' => $submissionId,
                'new_status' => $submission->status,
                'updated_at' => $submission->updated_at
            ]);

            // Создаем уведомление для ученика
            $this->createReviewNotification($submission);

            Log::info('Homework submission reviewed', [
                'submission_id' => $submissionId,
                'homework_id' => $homeworkId,
                'earned_points' => $data['earned_points'] ?? null
            ]);

            return response()->json($submission);

        } catch (\Exception $e) {
            Log::error('Error reviewing homework submission', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при проверке задания'], 500);
        }
    }

    // Создать submissions для всех учеников класса
    private function createHomeworkSubmissions(Homework $homework)
    {
        try {
            $students = $homework->schoolClass->students;

            Log::info('Creating homework submissions', [
                'homework_id' => $homework->id,
                'class_id' => $homework->school_class_id,
                'students_count' => $students->count(),
                'students_ids' => $students->pluck('id')->toArray()
            ]);

            foreach ($students as $student) {
                $submission = HomeworkSubmission::create([
                    'homework_id' => $homework->id,
                    'student_id' => $student->id,
                    'status' => 'not_submitted'
                ]);

                Log::info('Created submission', [
                    'submission_id' => $submission->id,
                    'homework_id' => $homework->id,
                    'student_id' => $student->id
                ]);
            }

            Log::info('Created homework submissions', [
                'homework_id' => $homework->id,
                'students_count' => $students->count()
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to create homework submissions', [
                'homework_id' => $homework->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    // Создать уведомления о новом задании
    private function createHomeworkNotifications(Homework $homework)
    {
        try {
            $students = $homework->schoolClass->students;

            foreach ($students as $student) {
                $notification = new \App\Models\Notification([
                    'user_id' => $student->id,
                    'title' => 'Новое домашнее задание',
                    'message' => "Назначено новое задание по предмету {$homework->subject->name}: {$homework->title}",
                    'type' => 'homework_assigned',
                    'related_id' => $homework->id,
                    'is_read' => false
                ]);
                $notification->save();
            }

        } catch (\Exception $e) {
            Log::warning('Failed to create homework notifications', [
                'homework_id' => $homework->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Создать уведомление о сдаче задания
    private function createSubmissionNotification(HomeworkSubmission $submission)
    {
        try {
            $notification = new \App\Models\Notification([
                'user_id' => $submission->homework->teacher_id,
                'title' => 'Сдано домашнее задание',
                'message' => "Ученик {$submission->student->name} сдал задание: {$submission->homework->title}",
                'type' => 'homework_submitted',
                'related_id' => $submission->id,
                'is_read' => false
            ]);
            $notification->save();

        } catch (\Exception $e) {
            Log::warning('Failed to create submission notification', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Создать уведомление о проверке задания
    private function createReviewNotification(HomeworkSubmission $submission)
    {
        try {
            $notification = new \App\Models\Notification([
                'user_id' => $submission->student_id,
                'title' => 'Задание проверено',
                'message' => "Ваше задание '{$submission->homework->title}' было проверено",
                'type' => 'homework_reviewed',
                'related_id' => $submission->id,
                'is_read' => false
            ]);
            $notification->save();

        } catch (\Exception $e) {
            Log::warning('Failed to create review notification', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Получить задания по предмету
    public function bySubject($subjectId, Request $request)
    {
        try {
            $subject = Subject::findOrFail($subjectId);

            $query = Homework::with([
                'teacher:id,name,email',
                'schoolClass:id,name,academic_year'
            ])->where('subject_id', $subjectId);

            // Фильтрация по классу
            if ($request->has('school_class_id') && !empty($request->class_id)) {
                $query->where('school_class_id', $request->class_id);
            }

            // Фильтрация по учителю
            if ($request->has('teacher_id') && !empty($request->teacher_id)) {
                $query->where('teacher_id', $request->teacher_id);
            }

            // Фильтрация по статусу
            if ($request->has('status') && !empty($request->status)) {
                if ($request->status === 'active') {
                    $query->where('due_date', '>=', now()->toDateString());
                } elseif ($request->status === 'completed') {
                    $query->where('due_date', '<', now()->toDateString());
                }
            }

            // Поиск
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $homeworks = $query->orderBy('due_date', 'asc')->get();

            // Статистика
            $stats = [
                'total_homeworks' => $homeworks->count(),
                'active_homeworks' => $homeworks->where('due_date', '>=', now()->toDateString())->count(),
                'completed_homeworks' => $homeworks->where('due_date', '<', now()->toDateString())->count(),
                'by_class' => $homeworks->groupBy('school_class_id')->map(function($group) {
                    $class = $group->first()->schoolClass;
                    return [
                        'class' => $class->only(['id', 'name', 'academic_year']),
                        'count' => $group->count()
                    ];
                })->values()
            ];

            return response()->json([
                'subject' => $subject->only(['id', 'name', 'description']),
                'homeworks' => $homeworks,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in HomeworkController::bySubject", [
                'subject_id' => $subjectId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении заданий по предмету'], 500);
        }
    }

    // Получить задания учителя
    public function byTeacher($teacherId, Request $request)
    {
        try {
            $teacher = User::findOrFail($teacherId);
            if ($teacher->role !== 'teacher') {
                return response()->json(['error' => 'Пользователь не является учителем'], 400);
            }

            $query = Homework::with([
                'subject:id,name',
                'schoolClass:id,name,academic_year'
            ])->where('teacher_id', $teacherId);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по классу
            if ($request->has('class_id') && !empty($request->class_id)) {
                $query->where('school_class_id', $request->class_id);
            }

            // Фильтрация по статусу
            if ($request->has('status') && !empty($request->status)) {
                if ($request->status === 'active') {
                    $query->where('due_date', '>=', now()->toDateString());
                } elseif ($request->status === 'completed') {
                    $query->where('due_date', '<', now()->toDateString());
                }
            }

            $homeworks = $query->orderBy('due_date', 'desc')->get();

            // Статистика
            $stats = [
                'total_homeworks' => $homeworks->count(),
                'active_homeworks' => $homeworks->where('due_date', '>=', now()->toDateString())->count(),
                'completed_homeworks' => $homeworks->where('due_date', '<', now()->toDateString())->count(),
                'by_subject' => $homeworks->groupBy('subject_id')->map(function($group) {
                    $subject = $group->first()->subject;
                    return [
                        'subject' => $subject->only(['id', 'name']),
                        'count' => $group->count()
                    ];
                })->values(),
                'by_class' => $homeworks->groupBy('school_class_id')->map(function($group) {
                    $class = $group->first()->schoolClass;
                    return [
                        'class' => $class->only(['id', 'name', 'academic_year']),
                        'count' => $group->count()
                    ];
                })->values()
            ];

            return response()->json([
                'teacher' => $teacher->only(['id', 'name', 'email']),
                'homeworks' => $homeworks,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in HomeworkController::byTeacher", [
                'teacher_id' => $teacherId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении заданий учителя'], 500);
        }
    }

    // Получить невыполненные задания ученика
    public function pending($studentId, Request $request)
    {
        try {
            // Проверяем, что пользователь является учеником
            $student = User::findOrFail($studentId);
            if ($student->role !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Получаем классы ученика
            $studentClasses = $student->studentClasses()->pluck('school_class_id');

            // Получаем все задания для классов ученика
            $allHomeworks = Homework::with([
                'subject:id,name',
                'teacher:id,name,email',
                'schoolClass:id,name'
            ])->whereIn('school_class_id', $studentClasses)
              ->where('due_date', '>=', now()->toDateString()) // Только активные задания
              ->get();

            // Получаем сданные задания
            $submittedHomeworkIds = HomeworkSubmission::where('student_id', $studentId)
                ->whereIn('homework_id', $allHomeworks->pluck('id'))
                ->pluck('homework_id');

            // Находим невыполненные задания
            $pendingHomeworks = $allHomeworks->whereNotIn('id', $submittedHomeworkIds);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $pendingHomeworks = $pendingHomeworks->where('subject_id', $request->subject_id);
            }

            // Фильтрация по классу
            if ($request->has('class_id') && !empty($request->class_id)) {
                $pendingHomeworks = $pendingHomeworks->where('school_class_id', $request->class_id);
            }

            $pendingHomeworks = $pendingHomeworks->sortBy('due_date')->values();

            // Статистика
            $stats = [
                'total_pending' => $pendingHomeworks->count(),
                'due_today' => $pendingHomeworks->where('due_date', now()->toDateString())->count(),
                'due_this_week' => $pendingHomeworks->whereBetween('due_date', [
                    now()->toDateString(),
                    now()->addWeek()->toDateString()
                ])->count(),
                'by_subject' => $pendingHomeworks->groupBy('subject.name')->map(function($group) {
                    return [
                        'subject' => $group->first()->subject->name,
                        'count' => $group->count(),
                        'urgent' => $group->where('due_date', '<=', now()->addDays(2)->toDateString())->count()
                    ];
                })->values(),
                'urgent_tasks' => $pendingHomeworks->where('due_date', '<=', now()->addDays(2)->toDateString())->count()
            ];

            return response()->json([
                'student' => $student->only(['id', 'name', 'email']),
                'pending_homeworks' => $pendingHomeworks,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in HomeworkController::pending", [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении невыполненных заданий'], 500);
        }
    }

    // Получить задания ребенка для родителя
    public function getChildHomeworks($studentId, Request $request)
    {
        try {
            // Проверяем, что пользователь является учеником
            $student = User::findOrFail($studentId);
            if ($student->role !== 'student') {
                return response()->json(['error' => 'Пользователь не является учеником'], 400);
            }

            // Проверяем, что текущий пользователь является родителем этого ученика
            $user = $request->attributes->get('user');
            if (!$student->parentStudents()->where('parent_id', $user->id)->exists()) {
                return response()->json(['error' => 'Вы не являетесь родителем этого ученика'], 403);
            }

            // Получаем классы ученика
            $studentClasses = $student->studentClasses()->pluck('school_class_id');

            $query = Homework::with([
                'subject:id,name',
                'teacher:id,name,email',
                'schoolClass:id,name'
            ])->whereIn('school_class_id', $studentClasses);

            // Фильтрация по предмету
            if ($request->has('subject_id') && !empty($request->subject_id)) {
                $query->where('subject_id', $request->subject_id);
            }

            // Фильтрация по статусу
            if ($request->has('status') && !empty($request->status)) {
                if ($request->status === 'active') {
                    $query->where('due_date', '>=', now()->toDateString());
                } elseif ($request->status === 'completed') {
                    $query->where('due_date', '<', now()->toDateString());
                }
            }

            $homeworks = $query->orderBy('due_date', 'asc')->get();

            // Получаем сдачи ученика
            $submissions = HomeworkSubmission::where('student_id', $studentId)
                ->whereIn('homework_id', $homeworks->pluck('id'))
                ->get()
                ->keyBy('homework_id');

            // Добавляем информацию о сдаче к каждому заданию
            $homeworks->each(function ($homework) use ($submissions) {
                $homework->submission = $submissions->get($homework->id);
                $homework->is_submitted = $submissions->has($homework->id);
                $homework->is_overdue = !$homework->is_submitted && $homework->due_date < now()->toDateString();
            });

            // Статистика
            $stats = [
                'total_homeworks' => $homeworks->count(),
                'submitted' => $homeworks->where('is_submitted', true)->count(),
                'pending' => $homeworks->where('is_submitted', false)->where('is_overdue', false)->count(),
                'overdue' => $homeworks->where('is_overdue', true)->count(),
                'completion_rate' => $homeworks->count() > 0
                    ? round(($homeworks->where('is_submitted', true)->count() / $homeworks->count()) * 100, 2)
                    : 0
            ];

            return response()->json([
                'student' => $student->only(['id', 'name', 'email']),
                'homeworks' => $homeworks,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error("Error in HomeworkController::getChildHomeworks", [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении домашних заданий ребенка'], 500);
        }
    }

    // Получить задания класса (алиас для classHomework)
    public function byClass($classId, Request $request)
    {
        return $this->classHomework($classId, $request);
    }

    // Закрыть задание
    public function close($id, Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            $homework = Homework::where('id', $id)
                ->where('teacher_id', $teacherId)
                ->findOrFail($id);

            if ($homework->status === 'closed') {
                return response()->json(['error' => 'Задание уже закрыто'], 400);
            }

            $homework->update(['status' => 'closed']);

            Log::info('Homework closed successfully by teacher', [
                'homework_id' => $homework->id,
                'teacher_id' => $teacherId
            ]);

            return response()->json([
                'message' => 'Задание успешно закрыто',
                'homework' => $homework
            ]);

        } catch (\Exception $e) {
            Log::error('Error closing homework', [
                'homework_id' => $id,
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при закрытии задания'], 500);
        }
    }

    // Получить статистику домашних заданий учителя
    public function teacherStatistics(Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            $query = Homework::where('teacher_id', $teacherId);

            // Общая статистика
            $totalHomework = $query->count();
            $activeHomework = $query->where('status', 'active')->count();
            $closedHomework = $query->where('status', 'closed')->count();

            // Статистика по предметам
            $subjectStats = $query->clone()->with('subject:id,name')
                ->get()
                ->groupBy('subject_id')
                ->map(function($group) {
                    $subject = $group->first()->subject;
                    return [
                        'subject' => $subject->only(['id', 'name']),
                        'total_homework' => $group->count(),
                        'active' => $group->where('status', 'active')->count(),
                        'closed' => $group->where('status', 'closed')->count()
                    ];
                })->values();

            // Статистика по классам
            $classStats = $query->clone()->with('schoolClass:id,name')
                ->get()
                ->groupBy('school_class_id')
                ->map(function($group) {
                    $class = $group->first()->schoolClass;
                    return [
                        'class' => $class->only(['id', 'name']),
                        'total_homework' => $group->count(),
                        'active' => $group->where('status', 'active')->count(),
                        'closed' => $group->where('status', 'closed')->count()
                    ];
                })->values();

            // Месячная статистика
            $monthlyStats = Homework::where('teacher_id', $teacherId)
                ->selectRaw('
                    DATE_FORMAT(created_at, "%Y-%m") as month,
                    COUNT(*) as total_homework,
                    SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_homework,
                    SUM(CASE WHEN status = "closed" THEN 1 ELSE 0 END) as closed_homework
                ')
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get();

            // Статистика по выполнениям
            $submissionStats = HomeworkSubmission::whereHas('homework', function($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })->get();

            $totalSubmissions = $submissionStats->count();
            $submitted = $submissionStats->where('status', 'submitted')->count();
            $reviewed = $submissionStats->where('status', 'reviewed')->count();
            $graded = $submissionStats->whereNotNull('earned_points')->count();

            $statistics = [
                'overview' => [
                    'total_homework' => $totalHomework,
                    'active_homework' => $activeHomework,
                    'closed_homework' => $closedHomework
                ],
                'by_subjects' => $subjectStats,
                'by_classes' => $classStats,
                'monthly' => $monthlyStats,
                'submissions' => [
                    'total_submissions' => $totalSubmissions,
                    'submitted' => $submitted,
                    'reviewed' => $reviewed,
                    'graded' => $graded,
                    'submission_rate' => $totalSubmissions > 0 ? round(($submitted / $totalSubmissions) * 100, 2) : 0
                ]
            ];

            return response()->json($statistics);

        } catch (\Exception $e) {
            Log::error('Error in HomeworkController::teacherStatistics', [
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Ошибка при получении статистики'], 500);
        }
    }

    // Получить список учеников для массового создания заданий
    public function getStudentsByClass($classId, Request $request)
    {
        try {
            $user = $request->attributes->get('user');
            $teacherId = $user->id;

            // Проверяем, что учитель преподает в этом классе
            $classExists = SchoolClass::whereHas('teachers', function($query) use ($teacherId) {
                $query->where('user_id', $teacherId);
            })->where('id', $classId)->exists();

            if (!$classExists) {
                return response()->json(['error' => 'У вас нет доступа к этому классу'], 403);
            }

            $students = User::whereHas('studentClasses', function($query) use ($classId) {
                $query->where('school_class_id', $classId);
            })
            ->select('id', 'name', 'email', 'student_number')
            ->get();

            return response()->json([
                'school_class_id' => $classId,
                'students' => $students,
                'total_students' => $students->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error in HomeworkController::getStudentsByClass', [
                'school_class_id' => $classId,
                'teacher_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Ошибка при получении списка учеников'], 500);
        }
    }
}
