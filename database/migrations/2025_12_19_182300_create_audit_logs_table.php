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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // пользователь, совершивший действие
            $table->string('action'); // тип действия: create, update, delete, login, logout
            $table->string('table_name')->nullable(); // название таблицы (для CRUD операций)
            $table->unsignedBigInteger('record_id')->nullable(); // ID за которой соверписи, сшалось действие
            $table->json('old_values')->nullable(); // предыдущие значения (для обновлений)
            $table->json('new_values')->nullable(); // новые значения (для созданий/обновлений)
            $table->string('ip_address', 45)->nullable(); // IP адрес пользователя
            $table->string('user_agent')->nullable(); // User-Agent браузера
            $table->string('url')->nullable(); // URL, с которого было совершено действие
            $table->string('method')->nullable(); // HTTP метод (GET, POST, PUT, DELETE)
            $table->json('metadata')->nullable(); // дополнительные метаданные
            $table->timestamps();

            // Индексы для оптимизации запросов
            $table->index(['user_id', 'created_at']);
            $table->index(['table_name', 'record_id']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
