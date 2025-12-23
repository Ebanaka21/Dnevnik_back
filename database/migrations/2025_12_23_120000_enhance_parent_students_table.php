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
        Schema::table('parent_students', function (Blueprint $table) {
            // Добавить статус связи (активная, ожидает подтверждения, отклонена, отозвана)
            $table->enum('status', ['active', 'pending', 'rejected', 'revoked'])
                  ->default('active')
                  ->after('is_primary');

            // Время подтверждения связи
            $table->timestamp('verified_at')->nullable()->after('status');

            // Кто создал связь (администратор, родитель или учитель)
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->after('verified_at');

            // Причина отклонения (если статус rejected)
            $table->text('rejection_reason')->nullable()->after('created_by');

            // Индексы для оптимизации запросов
            $table->index(['parent_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parent_students', function (Blueprint $table) {
            // Удалить индексы
            $table->dropIndex(['parent_id', 'status']);
            $table->dropIndex(['student_id', 'status']);
            $table->dropIndex(['is_primary']);

            // Удалить внешний ключ
            $table->dropForeign(['created_by']);

            // Удалить колонки
            $table->dropColumn([
                'status',
                'verified_at',
                'created_by',
                'rejection_reason'
            ]);
        });
    }
};
