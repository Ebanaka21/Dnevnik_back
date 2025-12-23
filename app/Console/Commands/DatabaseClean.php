<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DatabaseClean extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:clean {--force : Force the operation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean and recreate SQLite database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Cleaning SQLite Database...');

        // Database paths
        $dbPath = database_path('database.sqlite');
        $walPath = database_path('database.sqlite-wal');
        $shmPath = database_path('database.sqlite-shm');

        // Remove existing files
        $this->info('Removing corrupted database files...');

        if (File::exists($dbPath)) {
            File::delete($dbPath);
            $this->line('✓ Removed database.sqlite');
        }

        if (File::exists($walPath)) {
            File::delete($walPath);
            $this->line('✓ Removed database.sqlite-wal');
        }

        if (File::exists($shmPath)) {
            File::delete($shmPath);
            $this->line('✓ Removed database.sqlite-shm');
        }

        // Create new database file
        $this->info('Creating new database...');
        File::put($dbPath, '');
        $this->line('✓ Created new database.sqlite');

        // Set proper permissions
        chmod($dbPath, 0666);

        $this->info('✅ Database cleaned successfully!');

        $this->warn('⚠️  Run migrations to populate the database:');
        $this->line('   php artisan migrate:fresh --seed');

        return Command::SUCCESS;
    }
}
