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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade'); // ученик
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade'); // предмет
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade'); // учитель
            $table->date('date'); // дата занятия
            $table->enum('status', ['present', 'absent', 'late', 'sick', 'excused'])->default('present'); // статус посещаемости
            $table->text('reason')->nullable(); // причина отсутствия/опоздания
            $table->time('arrival_time')->nullable(); // время прихода (для опоздавших)
            $table->integer('lesson_number')->nullable(); // номер урока
            $table->text('comment')->nullable(); // комментарий
            $table->timestamps();

            // Уникальность с учетом lesson_number - один статус на ученика/предмет/дату/урок
            $table->unique(['student_id', 'subject_id', 'date', 'lesson_number']);
            $table->index(['student_id', 'date']); // для отчета по посещаемости ученика
            $table->index(['teacher_id', 'date']); // для отчета учителя
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
