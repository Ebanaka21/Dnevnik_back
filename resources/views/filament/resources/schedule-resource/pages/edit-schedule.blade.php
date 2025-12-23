{{-- resources/views/filament/resources/schedule-resource/pages/edit-schedule.blade.php --}}

<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        {{-- Информация о классе и параметры --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Информация о классе</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Класс</div>
                    <div class="text-lg font-medium">{{ $record->full_name }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Учебный год</div>
                    <div class="text-lg font-medium">{{ $record->academic_year ?? 'Не указан' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Классный руководитель</div>
                    <div class="text-lg font-medium">{{ $record->classTeacher?->full_name ?? 'Не назначен' }}</div>
                </div>
            </div>
        </div>

        {{-- Таблица расписания 7x7 --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 overflow-x-auto">
            <h3 class="text-lg font-semibold mb-4">Расписание на неделю (Пн-Вс)</h3>

            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-700">
                        <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-left font-semibold">Урок</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-center font-semibold">Пн</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-center font-semibold">Вт</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-center font-semibold">Ср</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-center font-semibold">Чт</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-center font-semibold">Пт</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-center font-semibold">Сб</th>
                        <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-center font-semibold">Вс</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $daysOfWeek = [
                            1 => 'Понедельник',
                            2 => 'Вторник',
                            3 => 'Среда',
                            4 => 'Четверг',
                            5 => 'Пятница',
                            6 => 'Суббота',
                            7 => 'Воскресенье',
                        ];
                    @endphp

                    @for ($lessonNumber = 1; $lessonNumber <= 7; $lessonNumber++)
                        <tr>
                            <td class="border border-gray-300 dark:border-gray-600 px-4 py-2 bg-gray-50 dark:bg-gray-900 font-semibold">
                                <div>{{ $lessonNumber }}-й урок</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                    @php
                                        // Расчет времени урока
                                        $firstLessonTime = $this->scheduleData['first_lesson_time'] ?? '09:00';
                                        $lessonDuration = $this->scheduleData['lesson_duration'] ?? 45;
                                        $breakDuration = 15;

                                        if ($firstLessonTime) {
                                            $currentTime = \DateTime::createFromFormat('H:i', $firstLessonTime);
                                            for ($i = 1; $i < $lessonNumber; $i++) {
                                                $currentTime->modify("+{$lessonDuration} minutes");
                                                $currentTime->modify("+{$breakDuration} minutes");
                                            }
                                            $startTime = clone $currentTime;
                                            $endTime = clone $startTime;
                                            $endTime->modify("+{$lessonDuration} minutes");

                                            echo $startTime->format('H:i') . ' - ' . $endTime->format('H:i');
                                        }
                                    @endphp
                                </div>
                            </td>

                            @foreach ($daysOfWeek as $dayNumber => $dayName)
                                <td class="border border-gray-300 dark:border-gray-600 px-2 py-2">
                                    <div class="space-y-2">
                                        {{-- Предмет --}}
                                        <select
                                            wire:model="scheduleData.{{ $dayNumber }}.{{ $lessonNumber }}.subject_id"
                                            class="w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-2 py-1"
                                        >
                                            <option value="">Предмет</option>
                                            @foreach ($subjects as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>

                                        {{-- Учитель --}}
                                        <select
                                            wire:model="scheduleData.{{ $dayNumber }}.{{ $lessonNumber }}.teacher_id"
                                            class="w-full text-xs rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 px-2 py-1"
                                        >
                                            <option value="">Учитель</option>
                                            @foreach ($teachers as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        {{-- Кнопка сохранения --}}
        <div class="flex justify-end gap-3">
            <x-filament::button
                color="gray"
                tag="a"
                :href="$this->getResource()::getUrl('index')"
            >
                Отмена
            </x-filament::button>

            <x-filament::button type="submit">
                Сохранить расписание
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
