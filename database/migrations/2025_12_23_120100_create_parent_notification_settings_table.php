<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parent_notification_settings', function (Blueprint $table) {
            $table->id();

            // Связи с родителем и учеником
            $table->foreignId('parent_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->foreignId('student_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Настройки уведомлений по типам событий
            $table->boolean('notify_bad_grades')->default(true);
            $table->boolean('notify_absences')->default(true);
            $table->boolean('notify_late')->default(true);
            $table->boolean('notify_homework_assigned')->default(true);
            $table->boolean('notify_homework_deadline')->default(false);

            // Пороговые значения
            $table->unsignedTinyInteger('bad_grade_threshold')->default(3); // уведомлять если оценка <= этого значения
            $table->unsignedTinyInteger('homework_deadline_days')->default(1); // за сколько дней до дедлайна уведомлять

            $table->timestamps();

            // Уникальная пара родитель-ученик
            $table->unique(['parent_id', 'student_id']);

            // Индексы для оптимизации
            $table->index('parent_id');
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_notification_settings');
    }
};
