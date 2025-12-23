<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\StudentClass;
use App\Models\ParentStudent;

class UserSeeder extends Seeder
{
    /**
     * Создание тестовых пользователей для электронного дневника
     *
     * Создает пользователей всех ролей:
     * - Администраторы системы (role: admin)
     * - Учителя с предметами и классами (role: teacher)
     * - Ученики с классами (role: student)
     * - Родители с детьми (role: parent)
     *
     * Настройки доступа:
     * - Администратор: admin@school.ru / admin123
     * - Учителя: различные email / teacher123
     * - Ученики: различные email / student123
     * - Родители: различные email / parent123
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание пользователей...');

        // Создаем администраторов
        $this->createAdmins();

        // Создаем учителей
        $teachers = $this->createTeachers();

        // Создаем учеников
        $students = $this->createStudents();

        // Создаем родителей
        $parents = $this->createParents();

        // Создаем связи учителей с предметами
        $this->linkTeachersToSubjects($teachers);

        // Связываем учителей с классами как классные руководители
        $this->assignClassTeachers($teachers);

        // Связываем учеников с классами
        $this->linkStudentsToClasses($students);

        // Создаем связи родители-дети
        $this->createParentChildLinks($parents, $students);

        $this->command->info('Создание пользователей завершено!');
        $this->command->info('Созданные тестовые аккаунты:');
        $this->command->info('Администратор: admin@school.ru / admin123');
        $this->command->info('Учителя: различные email / teacher123');
        $this->command->info('Ученики: различные email / student123');
        $this->command->info('Родители: различные email / parent123');
    }

    private function createAdmins()
    {
        $admins = [
            [
                'name' => 'Администратор',
                'surname' => 'Системы',
                'second_name' => 'Главный',
                'email' => 'admin@school.ru',
                'phone' => '+7(900)123-45-67',
                'birthday' => '1985-05-15',
                'gender' => 'male',
                'role' => 'admin',
            ],
            [
                'name' => 'Модератор',
                'surname' => 'Системы',
                'second_name' => 'Заместитель',
                'email' => 'moderator@school.ru',
                'phone' => '+7(900)123-45-68',
                'birthday' => '1990-08-20',
                'gender' => 'female',
                'role' => 'admin',
            ]
        ];

        foreach ($admins as $adminData) {
            $userData = array_intersect_key($adminData, array_flip([
                'name', 'surname', 'second_name', 'email', 'phone', 'birthday', 'gender'
            ]));

            $userData['password'] = Hash::make('admin123');

            $user = User::updateOrCreate(
                ['email' => $adminData['email']],
                $userData
            );

            try {
                $user->update(['role' => 'admin']);
            } catch (\Exception $e) {
                // Column may not exist
            }
        }

        $this->command->info('Администраторы созданы');
    }

    private function createTeachers()
    {
        $teachers = [
            [
                'name' => 'Мария',
                'surname' => 'Иванова',
                'second_name' => 'Петровна',
                'email' => 'ivanova.mp@school.ru',
                'phone' => '+7(900)111-11-11',
                'birthday' => '1975-03-25',
                'gender' => 'female',
                'role' => 'teacher',
                'subjects' => ['Математика', 'Физика'],
            ],
            [
                'name' => 'Алексей',
                'surname' => 'Петров',
                'second_name' => 'Сергеевич',
                'email' => 'petrov.as@school.ru',
                'phone' => '+7(900)222-22-22',
                'birthday' => '1980-07-12',
                'gender' => 'male',
                'role' => 'teacher',
                'subjects' => ['Русский язык', 'Литература'],
            ],
            [
                'name' => 'Елена',
                'surname' => 'Сидорова',
                'second_name' => 'Владимировна',
                'email' => 'sidorova.ev@school.ru',
                'phone' => '+7(900)333-33-33',
                'birthday' => '1982-11-08',
                'gender' => 'female',
                'role' => 'teacher',
                'subjects' => ['История', 'Обществознание'],
            ],
            [
                'name' => 'Дмитрий',
                'surname' => 'Козлов',
                'second_name' => 'Николаевич',
                'email' => 'kozlov.dn@school.ru',
                'phone' => '+7(900)444-44-44',
                'birthday' => '1978-12-03',
                'gender' => 'male',
                'role' => 'teacher',
                'subjects' => ['Информатика', 'Математика'],
            ],
            [
                'name' => 'Анна',
                'surname' => 'Морозова',
                'second_name' => 'Ивановна',
                'email' => 'morozova.ai@school.ru',
                'phone' => '+7(900)555-55-55',
                'birthday' => '1985-09-18',
                'gender' => 'female',
                'role' => 'teacher',
                'subjects' => ['Английский язык'],
            ],
            [
                'name' => 'Сергей',
                'surname' => 'Васильев',
                'second_name' => 'Петрович',
                'email' => 'vasilev.sp@school.ru',
                'phone' => '+7(900)666-66-66',
                'birthday' => '1970-06-30',
                'gender' => 'male',
                'role' => 'teacher',
                'subjects' => ['Физкультура', 'ОБЖ'],
            ],
            [
                'name' => 'Татьяна',
                'surname' => 'Новикова',
                'second_name' => 'Михайловна',
                'email' => 'novikova.tm@school.ru',
                'phone' => '+7(900)777-77-77',
                'birthday' => '1983-04-14',
                'gender' => 'female',
                'role' => 'teacher',
                'subjects' => ['Химия', 'Биология'],
            ],
            [
                'name' => 'Владимир',
                'surname' => 'Соколов',
                'second_name' => 'Александрович',
                'email' => 'sokolov.va@school.ru',
                'phone' => '+7(900)888-88-88',
                'birthday' => '1976-10-22',
                'gender' => 'male',
                'role' => 'teacher',
                'subjects' => ['География', 'Музыка'],
            ],
            [
                'name' => 'Ирина',
                'surname' => 'Волкова',
                'second_name' => 'Сергеевна',
                'email' => 'volkova.is@school.ru',
                'phone' => '+7(900)999-99-99',
                'birthday' => '1987-01-09',
                'gender' => 'female',
                'role' => 'teacher',
                'subjects' => ['Изобразительное искусство'],
            ]
        ];

        $createdTeachers = collect();

        foreach ($teachers as $teacherData) {
            $userData = array_intersect_key($teacherData, array_flip([
                'name', 'surname', 'second_name', 'email', 'phone', 'birthday', 'gender'
            ]));

            $userData['password'] = Hash::make('teacher123');

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            try {
                $user->update(['role' => 'teacher']);
            } catch (\Exception $e) {
                // Column may not exist
            }

            $createdTeachers->push($user);
        }

        $this->command->info('Учителя созданы: ' . $createdTeachers->count());
        return $createdTeachers;
    }

    private function createStudents()
    {
        $students = [
            // 10А класс
            ['name' => 'Андрей', 'surname' => 'Алексеев', 'second_name' => 'Сергеевич', 'email' => 'alekseev.as@student.ru', 'birthday' => '2008-03-15', 'gender' => 'male', 'role' => 'student', 'class' => '10А'],
            ['name' => 'Анна', 'surname' => 'Борисова', 'second_name' => 'Дмитриевна', 'email' => 'borisova.ad@student.ru', 'birthday' => '2008-07-22', 'gender' => 'female', 'role' => 'student', 'class' => '10А'],
            ['name' => 'Максим', 'surname' => 'Волков', 'second_name' => 'Алексеевич', 'email' => 'volkov.ma@student.ru', 'birthday' => '2008-11-08', 'gender' => 'male', 'role' => 'student', 'class' => '10А'],
            ['name' => 'Екатерина', 'surname' => 'Григорьева', 'second_name' => 'Петровна', 'email' => 'grigorieva.ep@student.ru', 'birthday' => '2008-05-14', 'gender' => 'female', 'role' => 'student', 'class' => '10А'],
            ['name' => 'Никита', 'surname' => 'Дмитриев', 'second_name' => 'Иванович', 'email' => 'dmitriev.ni@student.ru', 'birthday' => '2008-09-03', 'gender' => 'male', 'role' => 'student', 'class' => '10А'],
            ['name' => 'Полина', 'surname' => 'Егорова', 'second_name' => 'Сергеевна', 'email' => 'egorova.ps@student.ru', 'birthday' => '2008-12-18', 'gender' => 'female', 'role' => 'student', 'class' => '10А'],

            // 10Б класс
            ['name' => 'Артем', 'surname' => 'Жуков', 'second_name' => 'Дмитриевич', 'email' => 'zhukov.ad@student.ru', 'birthday' => '2008-04-27', 'gender' => 'male', 'role' => 'student', 'class' => '10Б'],
            ['name' => 'София', 'surname' => 'Зайцева', 'second_name' => 'Алексеевна', 'email' => 'zaytseva.sa@student.ru', 'birthday' => '2008-08-11', 'gender' => 'female', 'role' => 'student', 'class' => '10Б'],
            ['name' => 'Роман', 'surname' => 'Иванов', 'second_name' => 'Петрович', 'email' => 'ivanov.rp@student.ru', 'birthday' => '2008-02-05', 'gender' => 'male', 'role' => 'student', 'class' => '10Б'],
            ['name' => 'Виктория', 'surname' => 'Кузнецова', 'second_name' => 'Сергеевна', 'email' => 'kuznetsova.vs@student.ru', 'birthday' => '2008-06-19', 'gender' => 'female', 'role' => 'student', 'class' => '10Б'],

            // 11А класс
            ['name' => 'Александр', 'surname' => 'Лебедев', 'second_name' => 'Михайлович', 'email' => 'lebedev.am@student.ru', 'birthday' => '2007-01-25', 'gender' => 'male', 'role' => 'student', 'class' => '11А'],
            ['name' => 'Мария', 'surname' => 'Морозова', 'second_name' => 'Александровна', 'email' => 'morozova.ma@student.ru', 'birthday' => '2007-10-12', 'gender' => 'female', 'role' => 'student', 'class' => '11А'],
        ];

        $createdStudents = collect();

        foreach ($students as $studentData) {
            $userData = array_intersect_key($studentData, array_flip([
                'name', 'surname', 'second_name', 'email', 'birthday', 'gender'
            ]));

            $userData['phone'] = '+7(900)000-00-0' . str_pad($createdStudents->count() + 1, 2, '0', STR_PAD_LEFT);
            $userData['password'] = Hash::make('student123');

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            try {
                $user->update(['role' => 'student']);
            } catch (\Exception $e) {
                // Column may not exist
            }

            $createdStudents->push($user);
        }

        $this->command->info('Ученики созданы: ' . $createdStudents->count());
        return $createdStudents;
    }

    private function createParents()
    {
        $parents = [
            ['name' => 'Сергей', 'surname' => 'Алексеев', 'second_name' => 'Петрович', 'email' => 'alekseev.sp@parent.ru', 'birthday' => '1980-05-10', 'gender' => 'male', 'role' => 'parent'],
            ['name' => 'Ольга', 'surname' => 'Алексеева', 'second_name' => 'Ивановна', 'email' => 'alekseeva.oi@parent.ru', 'birthday' => '1982-08-15', 'gender' => 'female', 'role' => 'parent'],
            ['name' => 'Дмитрий', 'surname' => 'Борисов', 'second_name' => 'Сергеевич', 'email' => 'borisov.ds@parent.ru', 'birthday' => '1979-12-03', 'gender' => 'male', 'role' => 'parent'],
            ['name' => 'Елена', 'surname' => 'Борисова', 'second_name' => 'Владимировна', 'email' => 'borisova.ev@parent.ru', 'birthday' => '1981-03-20', 'gender' => 'female', 'role' => 'parent'],
            ['name' => 'Алексей', 'surname' => 'Волков', 'second_name' => 'Николаевич', 'email' => 'volkov.an@parent.ru', 'birthday' => '1978-07-08', 'gender' => 'male', 'role' => 'parent'],
            ['name' => 'Татьяна', 'surname' => 'Волкова', 'second_name' => 'Михайловна', 'email' => 'volkova.tm@parent.ru', 'birthday' => '1980-11-25', 'gender' => 'female', 'role' => 'parent'],
        ];

        $createdParents = collect();

        foreach ($parents as $parentData) {
            $userData = array_intersect_key($parentData, array_flip([
                'name', 'surname', 'second_name', 'email', 'birthday', 'gender'
            ]));

            $userData['phone'] = '+7(900)100-10-10';
            $userData['password'] = Hash::make('parent123');

            $parent = User::updateOrCreate(
                ['email' => $parentData['email']],
                $userData
            );

            try {
                $parent->update(['role' => 'parent']);
            } catch (\Exception $e) {
                // Column may not exist
            }

            $createdParents->push($parent);
        }

        $this->command->info('Родители созданы: ' . $createdParents->count());
        return $createdParents;
    }

    private function linkTeachersToSubjects($teachers)
    {
        foreach ($teachers as $teacher) {
            $teacherData = collect([
                'ivanova.mp@school.ru' => ['Математика', 'Физика'],
                'petrov.as@school.ru' => ['Русский язык', 'Литература'],
                'sidorova.ev@school.ru' => ['История', 'Обществознание'],
                'kozlov.dn@school.ru' => ['Информатика', 'Математика'],
                'morozova.ai@school.ru' => ['Английский язык'],
                'vasilev.sp@school.ru' => ['Физкультура', 'ОБЖ'],
                'novikova.tm@school.ru' => ['Химия', 'Биология'],
                'sokolov.va@school.ru' => ['География', 'Музыка'],
                'volkova.is@school.ru' => ['Изобразительное искусство'],
            ]);

            $subjectNames = $teacherData->get($teacher->email, []);

            $subjectIds = [];
            foreach ($subjectNames as $subjectName) {
                $subject = Subject::where('name', $subjectName)->first();
                if ($subject) {
                    $subjectIds[] = $subject->id;
                }
            }
            if (!empty($subjectIds)) {
                $teacher->subjects()->sync($subjectIds);
            }
        }

        $this->command->info('Учителя привязаны к предметам');
    }

    private function assignClassTeachers($teachers)
    {
        // Назначаем классных руководителей
        $classTeacherAssignments = [
            'ivanova.mp@school.ru' => '10А',
            'petrov.as@school.ru' => '10Б',
            'sidorova.ev@school.ru' => '11А',
        ];

        foreach ($classTeacherAssignments as $email => $className) {
            $teacher = $teachers->where('email', $email)->first();
            if ($teacher) {
                $class = SchoolClass::where('name', $className)->where('academic_year', '2024-2025')->first();
                if ($class) {
                    $class->update(['class_teacher_id' => $teacher->id]);
                }
            }
        }

        $this->command->info('Классные руководители назначены');
    }

    private function linkStudentsToClasses($students)
    {
        $classAssignments = [
            'alekseev.as@student.ru' => '10А',
            'borisova.ad@student.ru' => '10А',
            'volkov.ma@student.ru' => '10А',
            'grigorieva.ep@student.ru' => '10А',
            'dmitriev.ni@student.ru' => '10А',
            'egorova.ps@student.ru' => '10А',
            'zhukov.ad@student.ru' => '10Б',
            'zaytseva.sa@student.ru' => '10Б',
            'ivanov.rp@student.ru' => '10Б',
            'kuznetsova.vs@student.ru' => '10Б',
            'lebedev.am@student.ru' => '11А',
            'morozova.ma@student.ru' => '11А',
        ];

        foreach ($classAssignments as $email => $className) {
            $student = $students->where('email', $email)->first();
            if ($student) {
                $class = SchoolClass::where('name', $className)->where('academic_year', '2024-2025')->first();
                if ($class) {
                    \Illuminate\Support\Facades\DB::table('student_classes')->updateOrInsert(
                        ['student_id' => $student->id, 'school_class_id' => $class->id, 'academic_year' => '2024-2025'],
                        ['school_class_id' => $class->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }

        $this->command->info('Ученики привязаны к классам');
    }

    private function createParentChildLinks($parents, $students)
    {
        $parentChildLinks = [
            'alekseev.sp@parent.ru' => 'alekseev.as@student.ru',
            'alekseeva.oi@parent.ru' => 'alekseev.as@student.ru',
            'borisov.ds@parent.ru' => 'borisova.ad@student.ru',
            'borisova.ev@parent.ru' => 'borisova.ad@student.ru',
            'volkov.an@parent.ru' => 'volkov.ma@student.ru',
            'volkova.tm@parent.ru' => 'volkov.ma@student.ru',
        ];

        foreach ($parentChildLinks as $parentEmail => $studentEmail) {
            $parent = $parents->where('email', $parentEmail)->first();
            $student = $students->where('email', $studentEmail)->first();

            if ($parent && $student) {
                ParentStudent::updateOrCreate(
                    ['parent_id' => $parent->id, 'student_id' => $student->id]
                );
            }
        }

        $this->command->info('Связи родители-дети созданы');
    }
}
