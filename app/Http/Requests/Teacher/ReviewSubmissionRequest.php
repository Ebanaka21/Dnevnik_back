<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSubmissionRequest extends FormRequest
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
            'grade' => 'required|integer|min:1',
            'feedback' => 'nullable|string|max:1000',
            'status' => 'sometimes|required|in:reviewed',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'grade.required' => 'Укажите оценку',
            'grade.integer' => 'Оценка должна быть числом',
            'grade.min' => 'Оценка должна быть не меньше 1',
            'feedback.max' => 'Обратная связь не должна превышать 1000 символов',
            'status.in' => 'Недопустимый статус',
        ];
    }
}
