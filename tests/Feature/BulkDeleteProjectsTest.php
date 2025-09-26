<?php

use App\Models\Project;
use App\Models\User;

it('can bulk delete multiple projects', function () {
    $user = User::factory()->create();
    $projects = Project::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->deleteJson(route('projects.bulk-destroy'), [
        'project_ids' => $projects->pluck('id')->toArray(),
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'success' => true,
        'deleted_count' => 3,
    ]);

    // Verify projects are deleted
    $this->assertDatabaseMissing('projects', ['id' => $projects->first()->id]);
});

it('cannot bulk delete projects that do not belong to the user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userProject = Project::factory()->create(['user_id' => $user->id]);
    $otherUserProject = Project::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->deleteJson(route('projects.bulk-destroy'), [
        'project_ids' => [$userProject->id, $otherUserProject->id],
    ]);

    $response->assertStatus(403);
    $response->assertJson([
        'success' => false,
        'message' => 'One or more projects do not belong to you or do not exist.',
    ]);
});

it('validates that project_ids is required and contains valid project IDs', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson(route('projects.bulk-destroy'), [
        'project_ids' => [],
    ]);

    $response->assertStatus(422);
});
