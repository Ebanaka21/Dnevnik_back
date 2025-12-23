@php
    $daysOfWeek = [
        1 => 'Понедельник',
        2 => 'Вторник',
        3 => 'Среда',
        4 => 'Четверг',
        5 => 'Пятница',
        6 => 'Суббота',
    ];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Информация о классе --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-4">Информация о классе</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Класс</div>
                    <div class="text-lg font-medium">{{ $record->schoolClass->full_name ?? 'Не указано' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Учебный год</div>
                    <div class="text-lg font-medium">{{ $record->academic_year ?? 'Не указан' }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Классный руководитель</div>
                    <div class="text-lg font-medium">{{ $record->schoolClass->classTeacher?->full_name ?? 'Не назначен' }}</div>
                </div>
            </div>
        </div>

        {{-- Расписание по дням --}}
        @foreach ($daysOfWeek as $dayNum => $dayName)
            @php
                $daySchedules = collect($this->getScheduleData())->get($dayName, collect());
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">{{ $dayName }}</h3>

                @if ($daySchedules->isEmpty())
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <p>Расписание не заполнено</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($daySchedules as $lesson)
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Урок {{ $lesson->lesson_number }}</label>
                                    <div class="text-gray-700 dark:text-gray-300 font-semibold">{{ $lesson->subject->name }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Учитель</label>
                                    <div class="text-gray-700 dark:text-gray-300">{{ $lesson->teacher->full_name }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Начало</label>
                                    <div class="text-gray-700 dark:text-gray-300">{{ substr($lesson->start_time, 0, 5) }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium mb-1">Конец</label>
                                    <div class="text-gray-700 dark:text-gray-300">{{ substr($lesson->end_time, 0, 5) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
