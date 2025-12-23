<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class BulkAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole(['teacher', 'admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'school_class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'lesson_number' => 'nullable|integer|min:1|max:8',
            'attendance_data' => 'required|array',
            'attendance_data.*.student_id' => 'required|exists:users,id',
            'attendance_data.*.status' => 'required|in:present,absent,late,excused',
            'attendance_data.*.reason' => 'nullable|string|max:500',
            'attendance_data.*.comment' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'school_class_id.required' => 'Выберите класс',
            'school_class_idclass_id.exists' => 'Выбранный класс не существует',
            'subject_id.required' => 'Выберите предмет',
            'subject_id.exists' => 'Выбранный предмет не существует',
            'date.required' => 'Укажите дату',
            'date.date' => 'Некорректный формат даты',
            'lesson_number.integer' => 'Номер урока должен быть числом',
            'lesson_number.min' => 'Номер урока должен быть не меньше 1',
            'lesson_number.max' => 'Номер урока не должен превышать 8',
            'attendance_data.required' => 'Укажите данные посещаемости',
            'attendance_data.array' => 'Данные посещаемости должны быть массивом',
            'attendance_data.*.student_id.required' => 'Укажите ID ученика',
            'attendance_data.*.student_id.exists' => 'Указанный ученик не существует',
            'attendance_data.*.status.required' => 'Выберите статус посещаемости',
            'attendance_data.*.status.in' => 'Недопустимый статус посещаемости',
            'attendance_data.*.reason.max' => 'Причина не должна превышать 500 символов',
            'attendance_data.*.comment.max' => 'Комментарий не должен превышать 500 символов',
        ];
    }
}
