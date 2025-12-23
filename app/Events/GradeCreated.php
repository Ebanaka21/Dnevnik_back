<?php

namespace App\Events;

use App\Models\Grade;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие создания новой оценки
 *
 * Срабатывает при создании новой оценки в системе.
 * Используется для автоматической отправки уведомлений
 * ученику и его родителям.
 */
class GradeCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Созданная оценка
     *
     * @var Grade
     */
    public Grade $grade;

    /**
     * Создать новый экземпляр события
     *
     * @param Grade $grade Созданная оценка
     * @return void
     */
    public function __construct(Grade $grade)
    {
        $this->grade = $grade;
    }
}
