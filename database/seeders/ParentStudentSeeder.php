<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ParentStudent;
use App\Models\ParentNotificationSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParentStudentSeeder extends Seeder
{
    /**
     * Создание связей родитель-ученик и настроек уведомлений
     */
    public function run(): void
    {
        $this->command->info('👪 Создание связей родитель-ученик...');

        // Получаем всех родителей и учеников
        $parents = User::where('role', 'parent')->get();
        $students = User::where('role', 'student')->get();

        if ($parents->isEmpty() || $students->isEmpty()) {
            $this->command->warn('⚠️  Родители или ученики не найдены. Пропускаем создание связей.');
            return;
        }

        $createdLinks = 0;
        $createdSettings = 0;

        // Проверяем существующие связи
        $existingLinks = ParentStudent::count();

        if ($existingLinks > 0) {
            $this->command->info("ℹ️  Найдено {$existingLinks} существующих связей родитель-ученик");

            // Создаем настройки уведомлений для существующих связей
            $linksWithoutSettings = ParentStudent::whereDoesntHave('notificationSettings')->get();

            foreach ($linksWithoutSettings as $link) {
                ParentNotificationSetting::create([
                    'parent_id' => $link->parent_id,
                    'student_id' => $link->student_id,
                    'notify_bad_grades' => true,
                    'notify_absences' => true,
                    'notify_late' => true,
                    'notify_homework_assigned' => true,
                    'notify_homework_deadline' => false,
                    'bad_grade_threshold' => 3,
                    'homework_deadline_days' => 1,
                ]);
                $createdSettings++;
            }
        } else {
            // Создаем новые связи, если их нет
            // Каждому ученику назначаем 1-2 родителей
            foreach ($students as $student) {
                $numParents = rand(1, 2); // 1 или 2 родителя
                $selectedParents = $parents->random(min($numParents, $parents->count()));

                foreach ($selectedParents as $parent) {
                    // Проверяем, не существует ли уже такая связь
                    $exists = ParentStudent::where('parent_id', $parent->id)
                        ->where('student_id', $student->id)
                        ->exists();

                    if (!$exists) {
                        // Создаем связь
                        $link = ParentStudent::create([
                            'parent_id' => $parent->id,
                            'student_id' => $student->id,
                            'relationship_type' => $this->getRandomRelationship(),
                            'is_primary' => $createdLinks % 2 === 0, // Каждый второй - основной
                            'can_view_grades' => true,
                            'can_view_attendance' => true,
                            'can_view_homework' => true,
                            'can_receive_notifications' => true,
                        ]);

                        // Создаем настройки уведомлений
                        ParentNotificationSetting::create([
                            'parent_id' => $parent->id,
                            'student_id' => $student->id,
                            'notify_grades' => true,
                            'notify_attendance' => rand(0, 1) === 1, // 50% вероятность
                            'notify_homework' => true,
                            'notify_announcements' => rand(0, 1) === 1,
                            'notify_schedule_changes' => rand(0, 1) === 1,
                            'email_notifications' => true,
                            'push_notifications' => rand(0, 1) === 1,
                        ]);

                        $createdLinks++;
                        $createdSettings++;
                    }
                }
            }
        }

        $totalLinks = ParentStudent::count();
        $totalSettings = ParentNotificationSetting::count();

        $this->command->info("✅ Создано новых связей: {$createdLinks}");
        $this->command->info("✅ Создано настроек уведомлений: {$createdSettings}");
        $this->command->info("📊 Всего связей родитель-ученик: {$totalLinks}");
        $this->command->info("📊 Всего настроек уведомлений: {$totalSettings}");
    }

    /**
     * Получить случайный тип родственной связи
     */
    private function getRandomRelationship(): string
    {
        $relationships = ['mother', 'father', 'guardian', 'grandmother', 'grandfather', 'other'];
        return $relationships[array_rand($relationships)];
    }
}
