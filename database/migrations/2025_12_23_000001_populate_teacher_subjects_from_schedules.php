<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Заполняем teacher_subjects на основе расписания
        // Для каждого уникального сочетания учителя и предмета в расписании
        $schedules = DB::table('schedules')
            ->select('teacher_id', 'subject_id')
            ->where('is_active', true)
            ->distinct()
            ->get();

        foreach ($schedules as $schedule) {
            // Проверяем, нет ли уже такой записи
            $exists = DB::table('teacher_subjects')
                ->where('teacher_id', $schedule->teacher_id)
                ->where('subject_id', $schedule->subject_id)
                ->exists();

            if (!$exists) {
                DB::table('teacher_subjects')->insert([
                    'teacher_id' => $schedule->teacher_id,
                    'subject_id' => $schedule->subject_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Можно оставить данные или удалить всё
        // DB::table('teacher_subjects')->truncate();
    }
};
