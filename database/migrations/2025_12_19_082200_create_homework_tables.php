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
        // homeworks
        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade'); // предмет
            $table->foreignId('school_class_id')->constrained('school_classes')->onDelete('cascade'); // класс
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade'); // учитель
            $table->string('title'); // название задания
            $table->text('description'); // описание задания
            $table->date('assigned_date'); // дата выдачи
            $table->date('due_date'); // срок сдачи
            $table->unsignedSmallInteger('max_points')->default(100); // максимальное количество баллов
            $table->boolean('is_active')->default(true); // активно ли задание
            $table->timestamps();

            $table->index(['school_class_id', 'subject_id', 'due_date']); // для поиска заданий класса по предмету и сроку
        });

        // homework_submissions
        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_id')->constrained('homeworks')->onDelete('cascade'); // домашнее задание
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade'); // ученик
            $table->enum('status', ['not_submitted', 'submitted', 'reviewed', 'needs_revision'])->default('not_submitted'); // статус сдачи
            $table->text('content')->nullable(); // текст ответа (если без файла)
            $table->string('file_path')->nullable(); // путь к файлу с ответом
            $table->timestamp('submitted_at')->nullable(); // время сдачи
            $table->unsignedSmallInteger('points_earned')->nullable(); // полученные баллы
            $table->text('teacher_comment')->nullable(); // комментарий учителя
            $table->timestamp('reviewed_at')->nullable(); // время проверки
            $table->timestamps();

            $table->unique(['homework_id', 'student_id']); // ученик может сдать задание только один раз
            $table->index('student_id'); // для быстрого поиска всех сдач ученика
            $table->index('status'); // для фильтрации по статусу
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homeworks');
    }
};
