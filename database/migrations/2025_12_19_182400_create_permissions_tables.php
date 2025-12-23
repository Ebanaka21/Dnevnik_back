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
        // Таблица permissions
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // название права (например, 'grades.view', 'students.edit')
            $table->string('display_name'); // отображаемое название
            $table->text('description')->nullable(); // описание права
            $table->string('category'); // категория: grades, attendance, homework, students, classes, users, reports, settings
            $table->string('resource')->nullable(); // ресурс (students, grades, classes и т.д.)
            $table->string('action'); // действие: view, create, edit, delete, manage, export
            $table->boolean('is_system')->default(false); // системное право (нельзя удалить)
            $table->timestamps();

            // Индексы
            $table->index(['category', 'resource']);
            $table->index('action');
        });

        // Таблица role_permissions (связь ролей и прав)
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade'); // роль
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade'); // право
            $table->timestamps();

            // Уникальность связи роль-право
            $table->unique(['role_id', 'permission_id']);

            // Индексы
            $table->index('permission_id');
        });

        // Таблица user_permissions (индивидуальные права пользователей)
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // пользователь
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade'); // право
            $table->boolean('granted')->default(true); // предоставлено (true) или отозвано (false)
            $table->foreignId('granted_by')->nullable()->constrained('users')->onDelete('set null'); // кто предоставил право
            $table->timestamp('granted_at')->nullable(); // когда предоставлено право
            $table->timestamp('expires_at')->nullable(); // когда истекает право
            $table->text('reason')->nullable(); // причина предоставления/отзыва
            $table->timestamps();

            // Уникальность связи пользователь-право
            $table->unique(['user_id', 'permission_id']);

            // Индексы
            $table->index(['user_id', 'granted']);
            $table->index(['permission_id', 'granted']);
            $table->index('expires_at');
        });

        // Таблица class_permissions (права на конкретные классы)
        Schema::create('class_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // пользователь
            $table->foreignId('school_class_id')->constrained('school_classes')->onDelete('cascade'); // класс
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade'); // право
            $table->boolean('granted')->default(true); // предоставлено ли право на этот класс
            $table->foreignId('granted_by')->nullable()->constrained('users')->onDelete('set null'); // кто предоставил право
            $table->timestamp('granted_at')->nullable(); // когда предоставлено право
            $table->text('reason')->nullable(); // причина
            $table->timestamps();

            // Уникальность связи пользователь-класс-право
            $table->unique(['user_id', 'school_class_id', 'permission_id']);

            // Индексы
            $table->index(['user_id', 'granted']);
            $table->index(['school_class_id', 'granted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_permissions');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
