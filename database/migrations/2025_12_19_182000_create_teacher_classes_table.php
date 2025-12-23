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
        Schema::create('teacher_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade'); // учитель
            $table->foreignId('school_class_id')->constrained('school_classes')->onDelete('cascade'); // класс
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade'); // предмет
            $table->string('academic_year'); // учебный год (например, "2024-2025")
            $table->boolean('is_active')->default(true); // активна ли связь
            $table->timestamp('assigned_at')->nullable(); // дата назначения
            $table->timestamps();

            // Уникальность комбинации учитель+класс+предмет+год
            $table->unique(['teacher_id', 'school_class_id', 'subject_id', 'academic_year']);

            // Индексы для оптимизации запросов
            $table->index(['teacher_id', 'academic_year']);
            $table->index(['school_class_id', 'subject_id']);
            $table->index(['subject_id', 'academic_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_classes');
    }
};
