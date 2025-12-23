<?php

namespace App\Events;

use App\Models\Attendance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие отметки посещаемости
 *
 * Срабатывает при создании или обновлении записи о посещаемости.
 * Используется для автоматической отправки уведомлений
 * ученику и его родителям при отсутствии или опоздании.
 */
class AttendanceMarked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Запись о посещаемости
     *
     * @var Attendance
     */
    public Attendance $attendance;

    /**
     * Создать новый экземпляр события
     *
     * @param Attendance $attendance Запись о посещаемости
     * @return void
     */
    public function __construct(Attendance $attendance)
    {
        $this->attendance = $attendance;
    }
}
