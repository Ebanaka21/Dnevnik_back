<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class StudentsSeeder extends Seeder
{
    /**
     * Создание большого количества тестовых учеников
     *
     * Генерирует реалистичные данные учеников для тестирования системы:
     * - Разные имена, фамилии, отчества
     * - Реалистичные даты рождения
     * - Разные классы обучения
     */
    public function run(): void
    {
        $this->command->info('Начинаем создание учеников...');

        $existingStudents = User::where('role', 'student')->count();
        $targetStudents = 150;

        if ($existingStudents >= $targetStudents) {
            $this->command->info("Уже создано {$existingStudents} учеников, пропускаем создание");
            return;
        }

        $studentsToCreate = $targetStudents - $existingStudents;
        $this->command->info("Будет создано {$studentsToCreate} учеников");

        $createdCount = 0;

        // Генерируем учеников для каждого класса (7-12 учеников на класс)
        $classes = [
            ['year' => 1, 'count' => 20], // 1А, 1Б
            ['year' => 2, 'count' => 20], // 2А, 2Б
            ['year' => 3, 'count' => 16], // 3А, 3Б
            ['year' => 4, 'count' => 8],  // 4А
            ['year' => 5, 'count' => 16], // 5А, 5Б
            ['year' => 6, 'count' => 16], // 6А, 6Б
            ['year' => 7, 'count' => 16], // 7А, 7Б
            ['year' => 8, 'count' => 16], // 8А, 8Б
            ['year' => 9, 'count' => 16], // 9А, 9Б
            ['year' => 10, 'count' => 16], // 10А, 10Б
            ['year' => 11, 'count' => 16], // 11А, 11Б
        ];

        foreach ($classes as $classInfo) {
            $studentsForYear = $this->generateStudentsForYear($classInfo['year'], $classInfo['count']);
            $createdCount += $this->createStudents($studentsForYear);
        }

        $this->command->info("Создано учеников: {$createdCount}");
        $this->command->info('Создание учеников завершено!');
    }

    private function generateStudentsForYear($year, $count)
    {
        $students = [];

        // Базовые данные для генерации
        $maleNames = ['Александр', 'Дмитрий', 'Максим', 'Сергей', 'Андрей', 'Алексей', 'Артем', 'Илья', 'Кирилл', 'Михаил', 'Никита', 'Матвей', 'Роман', 'Егор', 'Арсений', 'Иван', 'Денис', 'Евгений', 'Даниил', 'Тимофей', 'Владислав', 'Игорь', 'Владимир', 'Павел', 'Руслан', 'Марк', 'Константин', 'Тимур', 'Олег', 'Ярослав'];
        $femaleNames = ['Анна', 'Мария', 'Елена', 'Дарья', 'Алина', 'Ирина', 'Екатерина', 'Анастасия', 'Марина', 'Ольга', 'Юлия', 'Татьяна', 'Наталья', 'Виктория', 'Елизавета', 'Кристина', 'Милана', 'Алиса', 'Валерия', 'Ангелина', 'София', 'Ксения', 'Полина', 'Диана', 'Каролина', 'Вероника', 'Василиса', 'Александра', 'Маргарита', 'Ева'];

        $surnames = ['Иванов', 'Петров', 'Сидоров', 'Кузнецов', 'Смирнов', 'Попов', 'Васильев', 'Соколов', 'Михайлов', 'Новиков', 'Федоров', 'Морозов', 'Волков', 'Алексеев', 'Лебедев', 'Семенов', 'Егоров', 'Павлов', 'Козлов', 'Степанов', 'Николаев', 'Орлов', 'Андреев', 'Макаров', 'Никитин', 'Захаров', 'Зайцев', 'Соловьев', 'Борисов', 'Яковлев'];

        $malePatronymics = ['Александрович', 'Дмитриевич', 'Сергеевич', 'Андреевич', 'Алексеевич', 'Иванович', 'Михайлович', 'Владимирович', 'Николаевич', 'Павлович', 'Викторович', 'Юрьевич', 'Олегович', 'Васильевич', 'Геннадьевич', 'Евгеньевич', 'Борисович', 'Станиславович', 'Романович', 'Валерьевич'];
        $femalePatronymics = ['Александровна', 'Дмитриевна', 'Сергеевна', 'Андреевна', 'Алексеевна', 'Ивановна', 'Михайловна', 'Владимировна', 'Николаевна', 'Павловна', 'Викторовна', 'Юрьевна', 'Олеговна', 'Васильевна', 'Геннадьевна', 'Евгеньевна', 'Борисовна', 'Станиславовна', 'Романовна', 'Валерьевна'];

        for ($i = 0; $i < $count; $i++) {
            $isMale = rand(0, 1);
            $name = $isMale ? $maleNames[array_rand($maleNames)] : $femaleNames[array_rand($femaleNames)];
            $surname = $surnames[array_rand($surnames)];
            $secondName = $isMale ? $malePatronymics[array_rand($malePatronymics)] : $femalePatronymics[array_rand($femalePatronymics)];

            // Генерируем email
            $emailBase = strtolower($this->transliterate($surname . $name . $secondName));
            $email = $emailBase . rand(100, 999) . '@student.ru';

            // Генерируем дату рождения
            $birthYear = 2024 - $year - rand(6, 8); // Учитываем возраст для класса
            $birthMonth = rand(1, 12);
            $birthDay = rand(1, 28);
            $birthday = Carbon::create($birthYear, $birthMonth, $birthDay);

            $students[] = [
                'name' => $name,
                'surname' => $surname,
                'second_name' => $secondName,
                'email' => $email,
                'birthday' => $birthday->format('Y-m-d'),
                'gender' => $isMale ? 'male' : 'female',
                'role' => 'student',
                'year' => $year,
            ];
        }

        return $students;
    }

    private function createStudents($students)
    {
        $createdCount = 0;

        foreach ($students as $studentData) {
            $userData = array_intersect_key($studentData, array_flip([
                'name', 'surname', 'second_name', 'email', 'birthday', 'gender'
            ]));

            $userData['password'] = Hash::make('student123');
            $userData['phone'] = '+7(900)' . rand(100, 999) . '-' . rand(10, 99) . '-' . rand(10, 99);

            try {
                User::create($userData);
                $createdCount++;
            } catch (\Exception $e) {
                // Пропускаем дубликаты email
                continue;
            }
        }

        return $createdCount;
    }

    private function transliterate($text)
    {
        $transliteration = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Shch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya'
        ];

        return strtr($text, $transliteration);
    }
}
