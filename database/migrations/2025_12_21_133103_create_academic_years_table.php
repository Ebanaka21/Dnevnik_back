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
        // Academic Years
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('year_name'); // например "2025-2026"
            $table->date('start_date'); // дата начала учебного года
            $table->date('end_date'); // дата окончания учебного года
            $table->integer('total_weeks')->default(34); // общее количество учебных недель
            $table->boolean('is_active')->default(false); // активный учебный год
            $table->text('description')->nullable(); // описание учебного года
            $table->timestamps();

            $table->unique('year_name');
            $table->index(['is_active']);
        });

        // Academic Weeks
        Schema::create('academic_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->integer('week_number'); // номер недели (1-34)
            $table->date('start_date'); // дата начала недели
            $table->date('end_date'); // дата окончания недели
            $table->boolean('is_holiday')->default(false); // каникулы
            $table->string('week_type')->default('regular'); // regular, holiday, exam
            $table->text('notes')->nullable(); // заметки
            $table->timestamps();

            $table->unique(['academic_year_id', 'week_number']);
            $table->index(['start_date', 'end_date']);
        });

        // Thematic Blocks
        Schema::create('thematic_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_plan_id')->constrained('curriculum_plans')->onDelete('cascade');
            $table->string('title'); // название темы (например "Квадратные уравнения")
            $table->text('description')->nullable(); // подробное описание темы
            $table->integer('weeks_count'); // количество недель на тему
            $table->integer('order'); // порядок следования тем
            $table->json('learning_objectives')->nullable(); // учебные цели
            $table->json('required_materials')->nullable(); // необходимые материалы
            $table->timestamps();

            $table->index(['curriculum_plan_id', 'order']);
        });

        // Curriculum Plan Details
        Schema::create('curriculum_plan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curriculum_plan_id')->constrained('curriculum_plans')->onDelete('cascade');
            $table->foreignId('thematic_block_id')->constrained('thematic_blocks')->onDelete('cascade');
            $table->foreignId('academic_week_id')->constrained('academic_weeks')->onDelete('cascade');
            $table->integer('lessons_per_week')->default(1); // уроков в неделю по этой теме
            $table->text('weekly_objectives')->nullable(); // цели на неделю
            $table->json('materials_needed')->nullable(); // материалы на неделю
            $table->timestamps();

            $table->unique(['curriculum_plan_id', 'academic_week_id']);
            $table->index(['thematic_block_id']);
        });

        // Lesson Plans
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->foreignId('curriculum_plan_detail_id')->constrained('curriculum_plan_details')->onDelete('cascade');
            $table->string('lesson_type')->default('regular'); // regular, control, practical, test
            $table->text('lesson_topic')->nullable(); // конкретная тема урока
            $table->text('learning_objectives')->nullable(); // цели урока
            $table->text('materials')->nullable(); // материалы для урока
            $table->text('homework_assignment')->nullable(); // задание на дом
            $table->date('homework_due_date')->nullable(); // срок сдачи ДЗ
            $table->timestamps();

            $table->unique('lesson_id');
            $table->index(['curriculum_plan_detail_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
