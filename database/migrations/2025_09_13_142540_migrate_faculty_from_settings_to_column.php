<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate faculty data from settings JSON column to faculty column
        $projects = DB::table('projects')
            ->whereNotNull('settings')
            ->get();

        foreach ($projects as $project) {
            $settings = json_decode($project->settings, true);

            if (isset($settings['faculty']) && ! empty($settings['faculty'])) {
                // Update the faculty column with the value from settings
                DB::table('projects')
                    ->where('id', $project->id)
                    ->update(['faculty' => $settings['faculty']]);

                // Remove faculty from settings
                unset($settings['faculty']);

                // Update settings without faculty
                DB::table('projects')
                    ->where('id', $project->id)
                    ->update(['settings' => json_encode($settings)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migrate faculty data back from faculty column to settings
        $projects = DB::table('projects')
            ->whereNotNull('faculty')
            ->get();

        foreach ($projects as $project) {
            $settings = json_decode($project->settings, true) ?? [];

            // Add faculty back to settings
            $settings['faculty'] = $project->faculty;

            // Update settings with faculty
            DB::table('projects')
                ->where('id', $project->id)
                ->update([
                    'settings' => json_encode($settings),
                    'faculty' => null,
                ]);
        }
    }
};
