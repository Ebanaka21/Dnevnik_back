<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class StoreHomeworkRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'subject_id' => 'required|exists:subjects,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'due_date' => 'required|date|after:now',
            'max_points' => 'nullable|integer|min:1|max:100',
            'instructions' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*' => 'string|max:255',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Укажите название задания',
            'title.max' => 'Название не должно превышать 255 символов',
            'description.required' => 'Укажите описание задания',
            'subject_id.required' => 'Выберите предмет',
            'subject_id.exists' => 'Выбранный предмет не существует',
            'school_class_id.required' => 'Выберите класс',
            'school_class_id.exists' => 'Выбранный класс не существует',
            'due_date.required' => 'Укажите дату сдачи',
            'due_date.date' => 'Некорректный формат даты',
            'due_date.after' => 'Дата сдачи должна быть в будущем',
            'max_points.integer' => 'Максимальное количество баллов должно быть числом',
            'max_points.min' => 'Максимальное количество баллов должно быть не меньше 1',
            'max_points.max' => 'Максимальное количество баллов не должно превышать 100',
            'attachments.array' => 'Приложения должны быть массивом',
            'attachments.*.max' => 'Каждое приложение не должно превышать 255 символов',
        ];
    }
}
