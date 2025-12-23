<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Subject;

class TeacherSeeder extends Seeder
{
    /**
     * Создание 15 учителей разных предметов
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание учителей...');

        $teachers = $this->generateTeachers();
        $createdTeachers = collect();

        foreach ($teachers as $teacherData) {
            // Убираем несуществующие поля из данных
            $userData = array_intersect_key($teacherData, array_flip([
                'name', 'surname', 'second_name', 'email', 'phone', 'birthday', 'gender'
            ]));

            $teacher = User::updateOrCreate(
                ['email' => $teacherData['email']],
                array_merge($userData, [
                    'password' => Hash::make('teacher123'),
                ])
            );

            $createdTeachers->push($teacher);
        }

        // Связываем учителей с предметами
        $this->linkTeachersToSubjects($createdTeachers);

        $this->command->info("Создано учителей: {$createdTeachers->count()}");
        $this->command->info('Создание учителей завершено!');
    }

    private function generateTeachers()
    {
        return [
            [
                'name' => 'Мария',
                'surname' => 'Иванова',
                'second_name' => 'Петровна',
                'email' => 'ivanova.mp@school.ru',
                'phone' => '+7(900)111-11-11',
                'birthday' => '1975-03-25',
                'gender' => 'female',
            ],
            [
                'name' => 'Алексей',
                'surname' => 'Петров',
                'second_name' => 'Сергеевич',
                'email' => 'petrov.as@school.ru',
                'phone' => '+7(900)222-22-22',
                'birthday' => '1980-07-12',
                'gender' => 'male',
            ],
            [
                'name' => 'Елена',
                'surname' => 'Сидорова',
                'second_name' => 'Владимировна',
                'email' => 'sidorova.ev@school.ru',
                'phone' => '+7(900)333-33-33',
                'birthday' => '1982-11-08',
                'gender' => 'female',
            ],
            [
                'name' => 'Дмитрий',
                'surname' => 'Козлов',
                'second_name' => 'Николаевич',
                'email' => 'kozlov.dn@school.ru',
                'phone' => '+7(900)444-44-44',
                'birthday' => '1978-12-03',
                'gender' => 'male',
            ],
            [
                'name' => 'Анна',
                'surname' => 'Морозова',
                'second_name' => 'Ивановна',
                'email' => 'morozova.ai@school.ru',
                'phone' => '+7(900)555-55-55',
                'birthday' => '1985-09-18',
                'gender' => 'female',
            ],
            [
                'name' => 'Сергей',
                'surname' => 'Васильев',
                'second_name' => 'Петрович',
                'email' => 'vasilev.sp@school.ru',
                'phone' => '+7(900)666-66-66',
                'birthday' => '1970-06-30',
                'gender' => 'male',
            ],
            [
                'name' => 'Татьяна',
                'surname' => 'Новикова',
                'second_name' => 'Михайловна',
                'email' => 'novikova.tm@school.ru',
                'phone' => '+7(900)777-77-77',
                'birthday' => '1983-04-14',
                'gender' => 'female',
            ],
            [
                'name' => 'Владимир',
                'surname' => 'Соколов',
                'second_name' => 'Александрович',
                'email' => 'sokolov.va@school.ru',
                'phone' => '+7(900)888-88-88',
                'birthday' => '1976-10-22',
                'gender' => 'male',
            ],
            [
                'name' => 'Ирина',
                'surname' => 'Волкова',
                'second_name' => 'Сергеевна',
                'email' => 'volkova.is@school.ru',
                'phone' => '+7(900)999-99-99',
                'birthday' => '1987-01-09',
                'gender' => 'female',
            ],
            [
                'name' => 'Олег',
                'surname' => 'Белов',
                'second_name' => 'Александрович',
                'email' => 'belov.oa@school.ru',
                'phone' => '+7(900)101-01-01',
                'birthday' => '1979-08-15',
                'gender' => 'male',
            ],
            [
                'name' => 'Наталья',
                'surname' => 'Комарова',
                'second_name' => 'Викторовна',
                'email' => 'komarova.nv@school.ru',
                'phone' => '+7(900)202-02-02',
                'birthday' => '1984-05-20',
                'gender' => 'female',
            ],
            [
                'name' => 'Павел',
                'surname' => 'Медведев',
                'second_name' => 'Игоревич',
                'email' => 'medvedev.pi@school.ru',
                'phone' => '+7(900)303-03-03',
                'birthday' => '1981-12-10',
                'gender' => 'male',
            ],
            [
                'name' => 'Юлия',
                'surname' => 'Орлова',
                'second_name' => 'Андреевна',
                'email' => 'orlova.ya@school.ru',
                'phone' => '+7(900)404-04-04',
                'birthday' => '1986-02-28',
                'gender' => 'female',
            ],
            [
                'name' => 'Максим',
                'surname' => 'Тарасов',
                'second_name' => 'Дмитриевич',
                'email' => 'tarasov.md@school.ru',
                'phone' => '+7(900)505-05-05',
                'birthday' => '1977-07-07',
                'gender' => 'male',
            ],
            [
                'name' => 'Екатерина',
                'surname' => 'Федорова',
                'second_name' => 'Сергеевна',
                'email' => 'fedorova.es@school.ru',
                'phone' => '+7(900)606-06-06',
                'birthday' => '1989-11-11',
                'gender' => 'female',
            ]
        ];
    }

    private function linkTeachersToSubjects($teachers)
    {
        $this->command->info('Связываем учителей с предметами...');

        // Маппинг email -> предметы
        $teacherSubjects = [
            'ivanova.mp@school.ru' => ['Математика', 'Физика'],
            'petrov.as@school.ru' => ['Русский язык', 'Литература'],
            'sidorova.ev@school.ru' => ['История', 'Обществознание'],
            'kozlov.dn@school.ru' => ['Информатика', 'Математика'],
            'morozova.ai@school.ru' => ['Английский язык'],
            'vasilev.sp@school.ru' => ['Физкультура', 'ОБЖ'],
            'novikova.tm@school.ru' => ['Химия', 'Биология'],
            'sokolov.va@school.ru' => ['География', 'Музыка'],
            'volkova.is@school.ru' => ['Изобразительное искусство'],
            'belov.oa@school.ru' => ['Технология', 'Математика'],
            'komarova.nv@school.ru' => ['Литература', 'Русский язык'],
            'medvedev.pi@school.ru' => ['История', 'Обществознание'],
            'orlova.ya@school.ru' => ['Биология', 'Химия'],
            'tarasov.md@school.ru' => ['Информатика', 'Физика'],
            'fedorova.es@school.ru' => ['Математика', 'Алгебра', 'Геометрия'],
        ];

        foreach ($teachers as $teacher) {
            $email = $teacher->email;

            if (!isset($teacherSubjects[$email])) {
                continue;
            }

            $subjectIds = [];
            foreach ($teacherSubjects[$email] as $subjectName) {
                $subject = Subject::where('name', $subjectName)->first();
                if ($subject) {
                    $subjectIds[] = $subject->id;
                }
            }

            if (!empty($subjectIds)) {
                $teacher->subjects()->sync($subjectIds);
            }
        }

        $this->command->info('Учителя связаны с предметами');
    }
}
