<?php

namespace App\Console\Commands;

use Database\Seeders\FacultyStructureSeeder;
use Illuminate\Console\Command;

class SeedFacultyStructures extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'faculty:seed
                            {--force : Force seed even if structures exist}';

    /**
     * The console command description.
     */
    protected $description = 'Seed faculty structures with standardized academic formats';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🏛️  Seeding Faculty Structures...');

        try {
            $seeder = new FacultyStructureSeeder;
            $seeder->run();

            $this->info('✅ Faculty structures seeded successfully!');
            $this->line('');
            $this->line('📋 Available Faculty Structures:');
            $this->line('  • Science - Natural and Physical Sciences');
            $this->line('  • Engineering - All Engineering Disciplines');
            $this->line('  • Social Sciences - Psychology, Sociology, etc.');
            $this->line('  • Management Science - Business and Management');
            $this->line('  • Medical - Health Sciences and Medicine');
            $this->line('  • Law - Legal Studies');
            $this->line('');
            $this->info('🚀 Ready for Project Guidance implementation!');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Failed to seed faculty structures:');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
