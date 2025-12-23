<?php

namespace App\Services;

use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TeacherDataValidationService
{
    /**
     * Валидирует данные для создания оценки
     */
    public function validateGradeData(array $data, ?int $teacherId = null): bool
    {
        $rules = [
            'user_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'grade' => 'required|integer|min:1|max:5',
            'grade_type_id' => 'required|exists:grade_types,id',
            'comment' => 'nullable|string|max:1000',
            'date' => 'nullable|date|before_or_equal:today'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Дополнительная бизнес-логика
        if ($teacherId && !$this->canTeacherGradeStudent($teacherId, $data['user_id'], $data['subject_id'])) {
            throw ValidationException::withMessages([
                'teacher' => 'Учитель не может выставлять оценки этому ученику по данному предмету'
            ]);
        }

        return true;
    }

    /**
     * Валидирует данные посещаемости
     */
    public function validateAttendanceData(array $data, ?int $teacherId = null): bool
    {
        $rules = [
            'user_id' => 'required|exists:users,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date|before_or_equal:today',
            'status' => 'required|in:present,absent,late,sick,excused',
            'reason' => 'nullable|string|max:500'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Бизнес-логика: если отсутствие, то нужна причина
        if (in_array($data['status'], ['absent', 'sick']) && empty($data['reason'])) {
            throw ValidationException::withMessages([
                'reason' => 'Причина отсутствия обязательна при отметке об отсутствии'
            ]);
        }

        // Проверка прав учителя
        if ($teacherId && !$this->canTeacherMarkAttendance($teacherId, $data)) {
            throw ValidationException::withMessages([
                'teacher' => 'Учитель не может отмечать посещаемость для данного класса'
            ]);
        }

        return true;
    }

    /**
     * Валидирует данные домашнего задания
     */
    public function validateHomeworkData(array $data, ?int $teacherId = null): bool
    {
        $rules = [
            'title' => 'required|string|min:3|max:255',
            'description' => 'required|string|min:10|max:2000',
            'subject_id' => 'required|exists:subjects,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'due_date' => 'required|date|after:today',
            'max_points' => 'required|integer|min:1|max:1000',
            'attachment' => 'nullable|file|max:10240' // 10MB max
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Проверка даты - не слишком далеко в будущем (максимум 3 месяца)
        $dueDate = Carbon::parse($data['due_date']);
        if ($dueDate->diffInDays(Carbon::now()) > 90) {
            throw ValidationException::withMessages([
                'due_date' => 'Срок сдачи не может превышать 3 месяца от текущей даты'
            ]);
        }

        // Проверка прав учителя
        if ($teacherId && !$this->canTeacherCreateHomework($teacherId, $data)) {
            throw ValidationException::withMessages([
                'teacher' => 'Учитель не может создавать задания для данного класса'
            ]);
        }

        return true;
    }

    /**
     * Валидирует связь учителя с классом и предметом
     */
    public function validateTeacherClassRelationship(int $teacherId, int $classId, int $subjectId): bool
    {
        $teacher = User::find($teacherId);
        if (!$teacher || $teacher->role !== 'teacher') {
            throw ValidationException::withMessages([
                'teacher' => 'Указанный пользователь не является учителем'
            ]);
        }

        // Проверяем, что учитель ведет данный предмет в данном классе
        $hasRelationship = $teacher->subjects()
            ->where('subject_id', $subjectId)
            ->where('school_class_id', $classId)
            ->exists();

        if (!$hasRelationship) {
            throw ValidationException::withMessages([
                'relationship' => 'Учитель не ведет данный предмет в указанном классе'
            ]);
        }

        return true;
    }

    /**
     * Валидирует принадлежность ученика к классу
     */
    public function validateStudentClassMembership(int $studentId, int $classId): bool
    {
        $student = User::find($studentId);
        if (!$student || $student->role !== 'student') {
            throw ValidationException::withMessages([
                'student' => 'Указанный пользователь не является учеником'
            ]);
        }

        $isMember = $student->schoolClasses()->where('school_class_id', $classId)->exists();

        if (!$isMember) {
            throw ValidationException::withMessages([
                'membership' => 'Ученик не принадлежит к указанному классу'
            ]);
        }

        return true;
    }

    /**
     * Санитизирует входные данные
     */
    public function sanitizeInputData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Удаляем потенциально опасные теги
                $sanitized[$key] = strip_tags($value);

                // Удаляем лишние пробелы
                $sanitized[$key] = trim(preg_replace('/\s+/', ' ', $sanitized[$key]));

                // Экранируем кавычки для безопасности
                $sanitized[$key] = addslashes($sanitized[$key]);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Валидирует данные уведомления
     */
    public function validateNotificationData(array $data): bool
    {
        $rules = [
            'title' => 'required|string|min:3|max:255',
            'message' => 'required|string|min:10|max:1000',
            'recipient_type' => 'required|in:student,parent,teacher,class',
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'exists:users,id',
            'delivery_method' => 'required|in:email,sms,in-app,push'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Проверяем, что получатели соответствуют типу
        $this->validateRecipientsMatchType($data['recipient_type'], $data['recipient_ids']);

        return true;
    }

    /**
     * Валидирует диапазон дат
     */
    public function validateDateRange(Carbon $startDate, Carbon $endDate, ?int $maxDays = null): bool
    {
        if ($startDate->greaterThan($endDate)) {
            throw ValidationException::withMessages([
                'date_range' => 'Дата начала должна быть раньше даты окончания'
            ]);
        }

        if ($startDate->isFuture()) {
            throw ValidationException::withMessages([
                'start_date' => 'Дата начала не может быть в будущем'
            ]);
        }

        if ($maxDays && $startDate->diffInDays($endDate) > $maxDays) {
            throw ValidationException::withMessages([
                'date_range' => "Диапазон дат не может превышать {$maxDays} дней"
            ]);
        }

        return true;
    }

    /**
     * Валидирует загрузку файлов
     */
    public function validateFileUpload(array $fileData): bool
    {
        $rules = [
            'filename' => 'required|string|min:1|max:255',
            'mime_type' => 'required|string',
            'size' => 'required|integer|min:1|max:52428800', // 50MB max
            'extension' => 'required|string|in:pdf,doc,docx,txt,jpg,jpeg,png,gif,zip,rar'
        ];

        $validator = Validator::make($fileData, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Проверяем опасные расширения
        $dangerousExtensions = ['php', 'js', 'html', 'exe', 'bat', 'sh'];
        if (in_array(strtolower($fileData['extension']), $dangerousExtensions)) {
            throw ValidationException::withMessages([
                'extension' => 'Недопустимое расширение файла'
            ]);
        }

        // Проверяем mime type на соответствие расширению
        $this->validateMimeType($fileData);

        return true;
    }

    /**
     * Валидирует параметры пагинации
     */
    public function validatePaginationParams($page, $perPage): bool
    {
        $rules = [
            'page' => 'integer|min:1|max:10000',
            'per_page' => 'integer|min:1|max:100'
        ];

        $validator = Validator::make([
            'page' => $page,
            'per_page' => $perPage
        ], $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return true;
    }

    /**
     * Валидирует действия учителя
     */
    public function validateTeacherAction(array $data): bool
    {
        $rules = [
            'teacher_id' => 'required|exists:users,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'action' => 'required|in:create_grade,update_grade,delete_grade,mark_attendance,create_homework,send_notification'
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Проверяем права на выполнение действия
        $this->validateTeacherPermissions($data['teacher_id'], $data['action']);

        return true;
    }

    /**
     * Проверяет, может ли учитель выставлять оценки ученику
     */
    private function canTeacherGradeStudent(int $teacherId, int $studentId, int $subjectId): bool
    {
        // Проверяем, что учитель ведет предмет и ученик в его классе
        return User::find($teacherId)->subjects()
            ->where('subject_id', $subjectId)
            ->whereHas('schoolClasses.students', function ($query) use ($studentId) {
                $query->where('user_id', $studentId);
            })->exists();
    }

    /**
     * Проверяет права учителя на отметку посещаемости
     */
    private function canTeacherMarkAttendance(int $teacherId, array $attendanceData): bool
    {
        return $this->canTeacherGradeStudent(
            $teacherId,
            $attendanceData['user_id'],
            $attendanceData['subject_id']
        );
    }

    /**
     * Проверяет права учителя на создание домашних заданий
     */
    private function canTeacherCreateHomework(int $teacherId, array $homeworkData): bool
    {
        return User::find($teacherId)->subjects()
            ->where('subject_id', $homeworkData['subject_id'])
            ->where('school_class_id', $homeworkData['school_class_id'])
            ->exists();
    }

    /**
     * Проверяет соответствие получателей типу
     */
    private function validateRecipientsMatchType(string $type, array $recipientIds): void
    {
        $users = User::whereIn('id', $recipientIds)->get();

        foreach ($users as $user) {
            if ($type === 'student' && $user->role !== 'student') {
                throw ValidationException::withMessages([
                    'recipient_type' => "Пользователь {$user->id} не является учеником"
                ]);
            }

            if ($type === 'parent' && $user->role !== 'parent') {
                throw ValidationException::withMessages([
                    'recipient_type' => "Пользователь {$user->id} не является родителем"
                ]);
            }

            if ($type === 'teacher' && $user->role !== 'teacher') {
                throw ValidationException::withMessages([
                    'recipient_type' => "Пользователь {$user->id} не является учителем"
                ]);
            }
        }
    }

    /**
     * Валидирует соответствие mime type и расширения
     */
    private function validateMimeType(array $fileData): void
    {
        $mimeExtensions = [
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'text/plain' => ['txt'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'application/zip' => ['zip'],
            'application/x-rar-compressed' => ['rar']
        ];

        if (!isset($mimeExtensions[$fileData['mime_type']])) {
            throw ValidationException::withMessages([
                'mime_type' => 'Недопустимый тип файла'
            ]);
        }

        if (!in_array(strtolower($fileData['extension']), $mimeExtensions[$fileData['mime_type']])) {
            throw ValidationException::withMessages([
                'mime_type' => 'Тип файла не соответствует расширению'
            ]);
        }
    }

    /**
     * Проверяет права учителя на выполнение действий
     */
    private function validateTeacherPermissions(int $teacherId, string $action): void
    {
        $teacher = User::find($teacherId);

        if (!$teacher || $teacher->role !== 'teacher') {
            throw ValidationException::withMessages([
                'teacher' => 'Указанный пользователь не является учителем'
            ]);
        }

        // Здесь можно добавить дополнительную логику проверки прав
        // Например, проверка конкретных разрешений для разных действий
    }
}
