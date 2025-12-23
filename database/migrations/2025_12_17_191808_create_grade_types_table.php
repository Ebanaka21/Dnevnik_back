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
        Schema::create('grade_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // название типа (Контрольная, Тест, Домашняя работа, Проект)
            $table->string('short_name', 10)->nullable(); // краткое название (К/Р, Т, Д/З, П)
            $table->text('description')->nullable(); // описание типа оценки
            $table->unsignedTinyInteger('weight')->default(1); // вес оценки при расчете среднего
            $table->boolean('is_active')->default(true); // активен ли тип оценки
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_types');
    }
};
