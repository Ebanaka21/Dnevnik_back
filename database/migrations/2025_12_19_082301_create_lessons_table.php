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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade'); // предмет
            $table->foreignId('school_class_id')->constrained('school_classes')->onDelete('cascade'); // класс
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade'); // учитель
            $table->string('title'); // название урока
            $table->text('description')->nullable(); // описание урока
            $table->date('date'); // дата проведения урока
            $table->unsignedTinyInteger('lesson_number')->nullable(); // номер урока в расписании (1-8)
            $table->time('start_time')->nullable(); // время начала
            $table->time('end_time')->nullable(); // время окончания
            $table->text('homework_assignment')->nullable(); // задание на дом
            $table->timestamps();

            $table->index(['school_class_id', 'subject_id', 'date']); // для поиска уроков класса по предмету и дате
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
