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
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade'); // ученик
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade'); // предмет
            $table->foreignId('grade_type_id')->constrained('grade_types')->onDelete('cascade'); // тип оценки
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade'); // учитель
            $table->unsignedTinyInteger('value'); // значение оценки (1-5 или 1-100)
            $table->text('description')->nullable(); // описание работы/задания
            $table->date('date'); // дата выставления оценки
            $table->text('comment')->nullable(); // комментарий учителя
            $table->boolean('is_final')->default(false); // итоговая оценка за четверть/полугодие
            $table->timestamps();

            $table->index(['student_id', 'subject_id', 'date']); // для быстрого поиска оценок ученика по предмету и дате
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
