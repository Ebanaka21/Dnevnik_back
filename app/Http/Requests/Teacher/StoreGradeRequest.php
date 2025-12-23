<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeRequest extends FormRequest
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
            'grade_type_id' => 'required|exists:grade_types,id',
            'grade_value' => 'required|integer|min:1|max:5',
            'date' => 'required|date',
            'comment' => 'nullable|string|max:500',
            'max_points' => 'nullable|integer|min:1|max:100',
            'earned_points' => 'nullable|integer|min:0',
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
            'grade_type_id.required' => 'Выберите тип оценки',
            'grade_type_id.exists' => 'Выбранный тип оценки не существует',
            'grade_value.required' => 'Укажите оценку',
            'grade_value.integer' => 'Оценка должна быть числом',
            'grade_value.min' => 'Оценка должна быть не меньше 1',
            'grade_value.max' => 'Оценка должна быть не больше 5',
            'date.required' => 'Укажите дату',
            'date.date' => 'Некорректный формат даты',
            'comment.max' => 'Комментарий не должен превышать 500 символов',
            'max_points.integer' => 'Максимальное количество баллов должно быть числом',
            'max_points.min' => 'Максимальное количество баллов должно быть не меньше 1',
            'max_points.max' => 'Максимальное количество баллов не должно превышать 100',
            'earned_points.integer' => 'Количество полученных баллов должно быть числом',
            'earned_points.min' => 'Количество полученных баллов не может быть отрицательным',
        ];
    }
}
