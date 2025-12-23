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
            // Добавить приоритет уведомления (только если не существует)
            if (!Schema::hasColumn('notifications', 'priority')) {
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                      ->default('normal')
                      ->after('type');
            }

            // Добавить категорию для более детальной классификации
            if (!Schema::hasColumn('notifications', 'category')) {
                $table->string('category', 50)->nullable()->after('priority');
            }

            // Добавить полиморфную связь с связанной сущностью (оценка, посещаемость, ДЗ и т.д.)
            if (!Schema::hasColumn('notifications', 'related_type')) {
                $table->nullableMorphs('related');
            }

            // Добавить время истечения уведомления
            if (!Schema::hasColumn('notifications', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('read_at');
            }
        });

        // Добавляем индексы отдельно, игнорируя ошибки если они уже существуют
        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['user_id', 'is_read', 'created_at']);
            });
        } catch (\Exception $e) {
            // Индекс уже существует
        }

        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['type', 'priority']);
            });
        } catch (\Exception $e) {
            // Индекс уже существует
        }

        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index('expires_at');
            });
        } catch (\Exception $e) {
            // Индекс уже существует
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Удалить индексы
            $table->dropIndex(['user_id', 'is_read', 'created_at']);
            $table->dropIndex(['type', 'priority']);
            $table->dropIndex(['expires_at']);

            // Удалить колонки
            $table->dropMorphs('related');
            $table->dropColumn([
                'priority',
                'category',
                'expires_at'
            ]);
        });
    }
};
