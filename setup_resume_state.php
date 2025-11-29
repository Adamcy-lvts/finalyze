<?php

use App\Models\CollectedPaper;
use App\Models\Project;
use App\Models\ProjectGeneration;
use App\Models\User;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find a user
$user = User::first();
if (! $user) {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
}

// Find or create a project
$project = Project::where('user_id', $user->id)->first();
if (! $project) {
    $project = Project::create([
        'user_id' => $user->id,
        'title' => 'Resume Test Project',
        'type' => 'thesis',
        'field_of_study' => 'Computer Science',
        'status' => 'setup',
    ]);
}

// Create some dummy papers to simulate literature mining done
if ($project->collectedPapers()->count() == 0) {
    CollectedPaper::create([
        'project_id' => $project->id,
        'title' => 'Test Paper 1',
        'source' => 'OpenAlex',
        'external_id' => '123',
        'publication_date' => '2023-01-01',
    ]);
}

// Create a failed generation record
ProjectGeneration::create([
    'project_id' => $project->id,
    'status' => 'failed',
    'current_stage' => 'literature_mining',
    'progress' => 20,
    'message' => 'Generation failed at literature mining',
    'details' => ['Error: Simulation of failure'],
]);

echo "Setup complete.\n";
echo 'User Email: '.$user->email."\n";
echo 'Project Slug: '.$project->slug."\n";
echo 'Project ID: '.$project->id."\n";
