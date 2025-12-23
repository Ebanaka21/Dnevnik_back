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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // получатель уведомления
            $table->string('title'); // заголовок уведомления
            $table->text('message'); // сообщение
            $table->enum('type', ['grade', 'attendance', 'homework', 'announcement', 'other'])->default('other'); // тип уведомления
            $table->json('data')->nullable(); // дополнительные данные (ID оценки, предмета и т.д.)
            $table->boolean('is_read')->default(false); // прочитано ли уведомление
            $table->timestamp('read_at')->nullable(); // время прочтения
            $table->timestamps();

            $table->index(['user_id', 'is_read']); // для поиска непрочитанных уведомлений пользователя
            $table->index('created_at'); // для сортировки по времени создания
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
