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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade'); // предмет
            $table->foreignId('school_class_id')->constrained('school_classes')->onDelete('cascade'); // класс
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade'); // основной учитель
            $table->foreignId('replacement_teacher_id')->nullable()->constrained('users')->onDelete('set null'); // замещающий учитель
            $table->unsignedTinyInteger('day_of_week'); // день недели (1-7, понедель-воскресенье)
            $table->unsignedTinyInteger('lesson_number'); // номер урока (1-8)
            $table->time('start_time'); // время начала
            $table->time('end_time'); // время окончания
            $table->date('effective_from')->nullable(); // действует с даты
            $table->date('effective_to')->nullable(); // действует до даты
            $table->boolean('is_active')->default(true); // активно ли расписание
            $table->timestamps();

            $table->unique(['school_class_id', 'day_of_week', 'lesson_number']); // один урок в одно время для класса
            $table->index(['teacher_id', 'day_of_week', 'lesson_number']); // для поиска расписания учителя
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
