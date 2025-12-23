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
        Schema::create('parent_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->onDelete('cascade'); // родитель
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade'); // ученик
            $table->enum('relationship', ['mother', 'father', 'guardian', 'other'])->default('mother'); // родственная связь
            $table->boolean('is_primary')->default(false); // основной родитель для уведомлений
            $table->timestamps();

            $table->unique(['parent_id', 'student_id']); // связь уникальна
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_students');
    }
};
