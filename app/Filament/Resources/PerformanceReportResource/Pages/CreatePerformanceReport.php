<?php

namespace App\Filament\Resources\PerformanceReportResource\Pages;

use App\Filament\Resources\PerformanceReportResource;
use App\Models\PerformanceReport;
use App\Models\User;
use App\Models\SchoolClass;
use App\Http\Controllers\Api\ReportController;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class CreatePerformanceReport extends CreateRecord
{
    protected static string $resource = PerformanceReportResource::class;

    /**
     * Создаем отчет напрямую через контроллер
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            // Валидация входных данных
            if (empty($data['student_id'])) {
                throw new \Exception('Не выбран ученик');
            }

            if (empty($data['school_class_id'])) {
                throw new \Exception('Не выбран класс');
            }

            if (empty($data['report_type'])) {
                throw new \Exception('Не выбран тип отчета');
            }

            if (empty($data['period_start']) || empty($data['period_end'])) {
                throw new \Exception('Не указан период отчета');
            }

            // Извлекаем данные формы
            $studentId = $data['student_id'];
            $classId = $data['school_class_id'];
            $reportType = $data['report_type'];
            $periodStart = $data['period_start'];
            $periodEnd = $data['period_end'];

            // Проверяем существование ученика и класса
            $student = User::find($studentId);
            if (!$student) {
                throw new \Exception('Ученик не найден');
            }

            $class = SchoolClass::find($classId);
            if (!$class) {
                throw new \Exception('Класс не найден');
            }

            // Проверяем на существующий отчет
            $existingReport = PerformanceReport::where('student_id', $studentId)
                ->where('school_class_id', $classId)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->first();

            if ($existingReport) {
                Notification::make()
                    ->title('Отчет уже существует')
                    ->body("Отчет для ученика {$student->full_name} за указанный период уже создан")
                    ->warning()
                    ->send();

                // Перенаправляем на список
                $this->redirect(route('filament.admin.resources.performance-reports.index'));
                return [];
            }

            $reportController = new ReportController();

            // Генерируем отчеты в зависимости от типа
            if ($reportType === 'performance' || $reportType === 'both') {
                $this->generatePerformanceReport($reportController, $studentId, $classId, $periodStart, $periodEnd);
            }

            if ($reportType === 'attendance' || $reportType === 'both') {
                $this->generateAttendanceReport($reportController, $studentId, $periodStart, $periodEnd);
            }

            Notification::make()
                ->title('Отчеты успешно сгенерированы')
                ->body("Созданы отчеты для ученика {$student->full_name} за период с {$periodStart} по {$periodEnd}")
                ->success()
                ->send();

            // Перенаправляем на список отчетов
            $this->redirect(route('filament.admin.resources.performance-reports.index'));

            // Возвращаем пустые данные (запись создана напрямую)
            return [];

        } catch (\Exception $e) {
            Log::error('Error generating report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);

            Notification::make()
                ->title('Ошибка при генерации отчета')
                ->body($e->getMessage())
                ->danger()
                ->send();

            // Возвращаем данные обратно в форму
            return $data;
        }
    }

    private function generatePerformanceReport($controller, $studentId, $classId, $periodStart, $periodEnd)
    {
        try {
            $request = Request::create('', 'POST', [
                'student_id' => $studentId,
                'school_class_id' => $classId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

            $response = $controller->generatePerformanceReport($request);

            if (!$response || !method_exists($response, 'getStatusCode')) {
                throw new \Exception('Некорректный ответ от контроллера');
            }

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $content = json_decode($response->getContent(), true);
                throw new \Exception($content['message'] ?? 'Ошибка при генерации отчета по успеваемости');
            }
        } catch (\Exception $e) {
            Log::error('Error in generatePerformanceReport', [
                'error' => $e->getMessage(),
                'student_id' => $studentId,
                'school_class_id' => $classId
            ]);
            throw new \Exception('Ошибка при генерации отчета по успеваемости: ' . $e->getMessage());
        }
    }

    private function generateAttendanceReport($controller, $studentId, $periodStart, $periodEnd)
    {
        try {
            $request = Request::create('', 'POST', [
                'student_id' => $studentId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

            $response = $controller->generateAttendanceReport($request);

            if (!$response || !method_exists($response, 'getStatusCode')) {
                throw new \Exception('Некорректный ответ от контроллера');
            }

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $content = json_decode($response->getContent(), true);
                throw new \Exception($content['message'] ?? 'Ошибка при генерации отчета по посещаемости');
            }
        } catch (\Exception $e) {
            Log::error('Error in generateAttendanceReport', [
                'error' => $e->getMessage(),
                'student_id' => $studentId
            ]);
            throw new \Exception('Ошибка при генерации отчета по посещаемости: ' . $e->getMessage());
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
