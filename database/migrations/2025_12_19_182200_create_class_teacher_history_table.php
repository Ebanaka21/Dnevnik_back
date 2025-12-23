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
        Schema::create('class_teacher_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->onDelete('cascade'); // класс
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade'); // учитель
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->onDelete('set null'); // предмет (для предметных учителей, может быть null для классных руководителей)
            $table->date('assigned_from')->nullable(); // дата начала назначения
            $table->date('assigned_to')->nullable(); // дата окончания назначения
            $table->string('academic_year'); // учебный год
            $table->text('reason')->nullable(); // причина назначения/снятия
            $table->string('assignment_type')->default('class_teacher'); // тип назначения: class_teacher, subject_teacher, replacement
            $table->boolean('is_active')->default(false); // активно ли назначение в данный момент
            $table->timestamps();

            // Индексы для оптимизации запросов
            $table->index(['school_class_id', 'academic_year']);
            $table->index(['teacher_id', 'academic_year']);
            $table->index(['subject_id', 'academic_year']);
            $table->index(['assigned_from', 'assigned_to']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_teacher_history');
    }
};
