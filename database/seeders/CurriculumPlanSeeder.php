<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurriculumPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Проверяем, есть ли уже планы для 2025-2026
        $existingPlans = DB::table('curriculum_plans')
            ->where('academic_year', '2025-2026')
            ->count();

        if ($existingPlans > 0) {
            echo "Curriculum plans for 2025-2026 already exist. Skipping...\n";
            return;
        }

        // Получить существующие классы, предметы и учителей
        $classes = DB::table('school_classes')->pluck('id', 'name');
        $subjects = DB::table('subjects')->pluck('id', 'name');
        $teachers = DB::table('users')->where('role', 'teacher')->pluck('id');

        if ($classes->isEmpty() || $subjects->isEmpty() || $teachers->isEmpty()) {
            return;
        }

        $curriculumPlans = [];

        // Для каждого класса назначим несколько предметов с учителями
        foreach ($classes as $className => $classId) {
            $assignedSubjects = [];

            // Математика - почти во всех классах
            if (isset($subjects['Математика'])) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Математика'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(3, 5)
                ];
            }

            // Русский язык - почти во всех классах
            if (isset($subjects['Русский язык'])) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Русский язык'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(4, 6)
                ];
            }

            // Литература - в старших классах
            if (isset($subjects['Литература']) && (str_starts_with($className, '8') || str_starts_with($className, '9') || str_starts_with($className, '10') || str_starts_with($className, '11'))) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Литература'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(2, 4)
                ];
            }

            // История - в средних и старших классах
            if (isset($subjects['История']) && (str_starts_with($className, '5') || str_starts_with($className, '6') || str_starts_with($className, '7') || str_starts_with($className, '8') || str_starts_with($className, '9') || str_starts_with($className, '10') || str_starts_with($className, '11'))) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['История'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(2, 3)
                ];
            }

            // Физика - в старших классах
            if (isset($subjects['Физика']) && (str_starts_with($className, '7') || str_starts_with($className, '8') || str_starts_with($className, '9') || str_starts_with($className, '10') || str_starts_with($className, '11'))) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Физика'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(2, 4)
                ];
            }

            // Химия - в старших классах
            if (isset($subjects['Химия']) && (str_starts_with($className, '8') || str_starts_with($className, '9') || str_starts_with($className, '10') || str_starts_with($className, '11'))) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Химия'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(2, 3)
                ];
            }

            // Биология - в старших классах
            if (isset($subjects['Биология']) && (str_starts_with($className, '6') || str_starts_with($className, '7') || str_starts_with($className, '8') || str_starts_with($className, '9') || str_starts_with($className, '10') || str_starts_with($className, '11'))) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Биология'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(2, 3)
                ];
            }

            // География - в средних классах
            if (isset($subjects['География']) && (str_starts_with($className, '6') || str_starts_with($className, '7') || str_starts_with($className, '8') || str_starts_with($className, '9') || str_starts_with($className, '10') || str_starts_with($className, '11'))) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['География'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(1, 2)
                ];
            }

            // Обществознание - в старших классах
            if (isset($subjects['Обществознание']) && (str_starts_with($className, '10') || str_starts_with($className, '11'))) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Обществознание'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(1, 2)
                ];
            }

            // Иностранный язык - в большинстве классов
            if (isset($subjects['Английский язык'])) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Английский язык'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(2, 3)
                ];
            }

            // Физкультура - во всех классах
            if (isset($subjects['Физическая культура'])) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Физическая культура'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(2, 3)
                ];
            }

            // Искусство/Музыка - в младших классах
            if (isset($subjects['Изобразительное искусство']) && (str_starts_with($className, '1') || str_starts_with($className, '2') || str_starts_with($className, '3') || str_starts_with($className, '4'))) {
                $assignedSubjects[] = [
                    'subject_id' => $subjects['Изобразительное искусство'],
                    'teacher_id' => $teachers->random(),
                    'hours_per_week' => rand(1, 2)
                ];
            }

            // Создаем записи для каждого предмета в классе
            foreach ($assignedSubjects as $subjectData) {
                $curriculumPlans[] = [
                    'school_class_id' => $classId,
                    'subject_id' => $subjectData['subject_id'],
                    'teacher_id' => $subjectData['teacher_id'],
                    'academic_year' => '2025-2026',
                    'hours_per_week' => $subjectData['hours_per_week'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Вставляем данные
        if (!empty($curriculumPlans)) {
            DB::table('curriculum_plans')->insert($curriculumPlans);
            echo "Created " . count($curriculumPlans) . " curriculum plans for 2025-2026\n";
        }
    }
}
