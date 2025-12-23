<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
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
            'comment' => 'required|string|max:1000',
            'visible_to_student' => 'boolean',
            'visible_to_parent' => 'boolean',
            'type' => 'nullable|in:general,academic,behavioral,achievement',
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
            'comment.required' => 'Введите комментарий',
            'comment.max' => 'Комментарий не должен превышать 1000 символов',
            'visible_to_student.boolean' => 'Недопустимое значение для видимости ученику',
            'visible_to_parent.boolean' => 'Недопустимое значение для видимости родителю',
            'type.in' => 'Недопустимый тип комментария',
        ];
    }
}
