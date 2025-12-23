<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
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
            'student_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late,excused',
            'reason' => 'nullable|string|max:500',
            'lesson_number' => 'nullable|integer|min:1|max:8',
            'comment' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'student_id.required' => 'Выберите ученика',
            'student_id.exists' => 'Выбранный ученик не существует',
            'subject_id.required' => 'Выберите предмет',
            'subject_id.exists' => 'Выбранный предмет не существует',
            'date.required' => 'Укажите дату',
            'date.date' => 'Некорректный формат даты',
            'status.required' => 'Выберите статус посещаемости',
            'status.in' => 'Недопустимый статус посещаемости',
            'reason.max' => 'Причина не должна превышать 500 символов',
            'lesson_number.integer' => 'Номер урока должен быть числом',
            'lesson_number.min' => 'Номер урока должен быть не меньше 1',
            'lesson_number.max' => 'Номер урока не должен превышать 8',
            'comment.max' => 'Комментарий не должен превышать 500 символов',
        ];
    }
}
