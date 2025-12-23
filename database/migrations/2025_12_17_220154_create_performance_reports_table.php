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
        Schema::create('performance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade'); // ученик
            $table->foreignId('school_class_id')->constrained('school_classes')->onDelete('cascade'); // класс
            $table->date('period_start'); // начало периода
            $table->date('period_end'); // конец периода
            $table->integer('total_grades')->default(0); // общее количество оценок
            $table->decimal('average_grade', 3, 2)->default(0.00); // средняя оценка
            $table->decimal('attendance_percentage', 5, 2)->default(0.00); // процент посещаемости
            $table->json('report_data')->nullable(); // дополнительные данные отчета
            $table->timestamps();

            $table->index(['student_id', 'period_start', 'period_end']); // для поиска отчетов ученика за период
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reports');
    }
};
