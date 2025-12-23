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
        // subjects
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // название предмета (Математика, Русский язык и т.д.)
            $table->string('short_name', 10)->nullable(); // краткое название (Мат, Рус и т.д.)
            $table->string('subject_code', 10); // код предмета
            $table->text('description')->nullable(); // описание предмета
            $table->unsignedTinyInteger('hours_per_week')->default(1); // количество часов в неделю
            $table->boolean('is_active')->default(true); // активен ли предмет
            $table->timestamps();
        });

        // school_classes
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // например, "10А", "11Б"
            $table->unsignedTinyInteger('year'); // год обучения (1-11)
            $table->string('letter'); // буква класса (А, Б, В и т.д.)
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->onDelete('set null'); // классный руководитель
            $table->unsignedSmallInteger('max_students')->default(30); // максимальное количество учеников
            $table->text('description')->nullable(); // описание класса
            $table->string('academic_year'); // учебный год
            $table->boolean('is_active')->default(true); // активен ли класс
            $table->timestamps();
        });

        // student_classes
        Schema::create('student_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade'); // ученик
            $table->foreignId('school_class_id')->constrained('school_classes')->onDelete('cascade'); // класс
            $table->string('academic_year'); // учебный год
            $table->boolean('is_active')->default(true); // активен ли ученик в классе
            $table->date('enrolled_at')->nullable(); // дата поступления в класс
            $table->date('graduated_at')->nullable(); // дата выпуска (если выпустился)
            $table->timestamps();

            $table->unique(['student_id', 'school_class_id', 'academic_year']); // уникальность по ученику, классу и году
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_classes');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('subjects');
    }
};
