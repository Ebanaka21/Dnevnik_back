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
        Schema::table('notifications', function (Blueprint $table) {
            // Целевой класс для уведомления (для классных руководителей)
            $table->foreignId('target_class_id')->nullable()->constrained('school_classes')->onDelete('set null')->after('user_id');

            // Приоритет уведомления
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal')->after('type');

            // Отправлять ли уведомление классному руководителю
            $table->boolean('send_to_class_teacher')->default(false)->after('priority');

            // Список конкретных получателей (для массовых уведомлений)
            $table->json('recipients')->nullable()->after('send_to_class_teacher');

            // Индексы для новых полей
            $table->index(['target_class_id', 'send_to_class_teacher']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['target_class_id']);
            $table->dropIndex(['target_class_id', 'send_to_class_teacher']);
            $table->dropIndex(['priority']);

            $table->dropColumn(['target_class_id', 'priority', 'send_to_class_teacher', 'recipients']);
        });
    }
};
