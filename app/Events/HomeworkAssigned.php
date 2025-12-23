<?php

namespace App\Events;

use App\Models\Homework;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие назначения домашнего задания
 *
 * Срабатывает при создании нового домашнего задания.
 * Используется для автоматической отправки уведомлений
 * ученикам класса и их родителям.
 */
class HomeworkAssigned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Назначенное домашнее задание
     *
     * @var Homework
     */
    public Homework $homework;

    /**
     * Создать новый экземпляр события
     *
     * @param Homework $homework Назначенное домашнее задание
     * @return void
     */
    public function __construct(Homework $homework)
    {
        $this->homework = $homework;
    }
}
