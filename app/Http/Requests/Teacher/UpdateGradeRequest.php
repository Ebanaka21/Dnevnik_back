<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradeRequest extends FormRequest
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
            'grade_value' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
            'max_points' => 'nullable|integer|min:1|max:100',
            'earned_points' => 'nullable|integer|min:0',
            'date' => 'sometimes|required|date',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'grade_value.integer' => 'Оценка должна быть числом',
            'grade_value.min' => 'Оценка должна быть не меньше 1',
            'grade_value.max' => 'Оценка должна быть не больше 5',
            'comment.max' => 'Комментарий не должен превышать 500 символов',
            'max_points.integer' => 'Максимальное количество баллов должно быть числом',
            'max_points.min' => 'Максимальное количество баллов должно быть не меньше 1',
            'max_points.max' => 'Максимальное количество баллов не должно превышать 100',
            'earned_points.integer' => 'Количество полученных баллов должно быть числом',
            'earned_points.min' => 'Количество полученных баллов не может быть отрицательным',
            'date.date' => 'Некорректный формат даты',
        ];
    }
}
