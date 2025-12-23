<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class CheckNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check {--force : Принудительно выполнить проверку}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Автоматическая проверка и создание уведомлений для учителей';

    protected $notificationService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔔 Запуск автоматической проверки уведомлений...');
        $this->newLine();

        $startTime = microtime(true);

        try {
            // Выполняем автоматическую проверку
            $this->notificationService->runAutomaticChecks();

            $executionTime = round(microtime(true) - $startTime, 2);

            $this->info('✅ Проверка уведомлений завершена успешно!');
            $this->line("⏱️  Время выполнения: {$executionTime} сек");
            $this->line('📅 Следующая проверка будет выполнена согласно расписанию');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Ошибка при выполнении проверки уведомлений:');
            $this->error($e->getMessage());

            // Логируем ошибку
            \Illuminate\Support\Facades\Log::error('Error in CheckNotifications command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
