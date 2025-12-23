<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GradeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gradeTypes = [
            [
                'name' => 'Контрольная работа',
                'short_name' => 'К/Р',
                'description' => 'Контрольная работа по пройденному материалу',
                'weight' => 3,
            ],
            [
                'name' => 'Самостоятельная работа',
                'short_name' => 'С/Р',
                'description' => 'Самостоятельная работа на уроке',
                'weight' => 1,
            ],
            [
                'name' => 'Домашняя работа',
                'short_name' => 'Д/З',
                'description' => 'Задание на дом',
                'weight' => 1,
            ],
            [
                'name' => 'Проект',
                'short_name' => 'Пр',
                'description' => 'Проектная работа',
                'weight' => 5,
            ],
            [
                'name' => 'Ответ на уроке',
                'short_name' => 'Отв',
                'description' => 'Устный ответ на уроке',
                'weight' => 1,
            ],
            [
                'name' => 'Лабораторная работа',
                'short_name' => 'Л/Р',
                'description' => 'Лабораторная работа',
                'weight' => 2,
            ],
        ];

        foreach ($gradeTypes as $gradeType) {
            \App\Models\GradeType::create($gradeType);
        }
    }
}
