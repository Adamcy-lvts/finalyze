<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemSetup extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:setup
                            {--reset : Truncate core lookup tables before seeding (disabled in production)}';

    /**
     * The console command description.
     */
    protected $description = 'Bootstrap required system data (roles, permissions, settings, admin user)';

    /**
     * Tables that can be safely truncated in non-production environments.
     */
    protected array $resetTables = [
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'permissions',
        'roles',
        'users',
        'feature_flags',
        'system_settings',
        'project_categories',
        'faculty_structures',
        'faculty_chapters',
        'universities',
        'faculties',
        'departments',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $reset = (bool) $this->option('reset');

        if ($reset && app()->environment('production')) {
            $this->error('Reset is disabled in production to avoid data loss.');

            return self::FAILURE;
        }

        if ($reset) {
            $this->warn('Resetting core tables before seeding...');
            $this->truncateTables();
        }

        $this->info('Running core seeders...');
        Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);
        $this->line(Artisan::output());

        $this->info('System setup complete.');

        return self::SUCCESS;
    }

    protected function truncateTables(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->resetTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
