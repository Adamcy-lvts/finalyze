<?php

namespace Tests\Feature;

use App\Jobs\BulkGenerateProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BulkGenerateProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_generation_starts_correctly()
    {
        Bus::fake();

        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'status' => 'topic_approved',
            'type' => 'thesis',
            'field_of_study' => 'Computer Science',
            'faculty' => 'Engineering',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.projects.bulk-generate.start', $project));

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'generation_id']);

        Bus::assertDispatched(BulkGenerateProject::class);

        $this->assertDatabaseHas('project_generations', [
            'project_id' => $project->id,
            'status' => 'pending',
        ]);
    }

    public function test_bulk_generation_status_check()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Create a generation record
        $generation = $project->generations()->create([
            'status' => 'processing',
            'current_stage' => 'literature_mining',
            'progress' => 25,
            'message' => 'Collecting papers...',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.projects.bulk-generate.status', $project));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'processing',
                'current_stage' => 'literature_mining',
                'progress' => 25,
            ]);
    }

    public function test_bulk_generation_cancellation()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $generation = $project->generations()->create([
            'status' => 'processing',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.projects.bulk-generate.cancel', $project));

        $response->assertStatus(200);

        $this->assertDatabaseHas('project_generations', [
            'id' => $generation->id,
            'status' => 'cancelled',
        ]);
    }
}
