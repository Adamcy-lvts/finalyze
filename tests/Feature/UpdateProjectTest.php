<?php

use App\Models\Project;
use App\Models\User;

it('can render the edit project page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get(route('projects.edit', $project->slug));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('projects/Edit')
        ->has('project')
        ->where('project.id', $project->id)
        ->where('project.slug', $project->slug)
    );
});

it('cannot access edit page for projects belonging to other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->get(route('projects.edit', $project->slug));

    $response->assertForbidden();
});

it('can update basic project information', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'description' => 'Old description',
        'field_of_study' => 'Old field',
        'mode' => 'manual',
    ]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'description' => 'New description',
        'field_of_study' => 'Computer Science',
        'mode' => 'auto',
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
    ]);

    $response->assertRedirect(route('projects.show', $project->slug));

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'description' => 'New description',
        'field_of_study' => 'Computer Science',
        'mode' => 'auto',
    ]);
});

it('can update academic details including institutional fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'supervisor_name' => 'Dr. Old Name',
    ]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'description' => $project->description,
        'field_of_study' => $project->field_of_study,
        'mode' => $project->mode,
        'university' => 'New University',
        'faculty' => 'New Faculty',
        'course' => 'New Course',
        'supervisor_name' => 'Dr. New Supervisor',
    ]);

    $response->assertRedirect(route('projects.show', $project->slug));

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'university' => 'New University',
        'faculty' => 'New Faculty',
        'course' => 'New Course',
        'supervisor_name' => 'Dr. New Supervisor',
    ]);
});

it('can update preliminary pages content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'description' => $project->description,
        'field_of_study' => $project->field_of_study,
        'mode' => $project->mode,
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
        'dedication' => 'To my loving family',
        'acknowledgements' => 'I would like to thank...',
        'abstract' => 'This research explores...',
    ]);

    $response->assertRedirect(route('projects.show', $project->slug));

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'dedication' => 'To my loving family',
        'acknowledgements' => 'I would like to thank...',
        'abstract' => 'This research explores...',
    ]);
});

it('can update certification signatories', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $signatories = [
        ['name' => 'Dr. John Doe', 'title' => 'Supervisor'],
        ['name' => 'Prof. Jane Smith', 'title' => 'Head of Department'],
    ];

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'description' => $project->description,
        'field_of_study' => $project->field_of_study,
        'mode' => $project->mode,
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
        'certification_signatories' => $signatories,
    ]);

    $response->assertRedirect(route('projects.show', $project->slug));

    $project->refresh();
    expect($project->certification_signatories)->toBe($signatories);
});

it('can update tables and abbreviations', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $tables = [
        ['title' => 'Table 1', 'description' => 'Sample data'],
        ['title' => 'Table 2', 'description' => 'Analysis results'],
    ];

    $abbreviations = [
        ['abbreviation' => 'AI', 'full_form' => 'Artificial Intelligence'],
        ['abbreviation' => 'ML', 'full_form' => 'Machine Learning'],
    ];

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'description' => $project->description,
        'field_of_study' => $project->field_of_study,
        'mode' => $project->mode,
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
        'tables' => $tables,
        'abbreviations' => $abbreviations,
    ]);

    $response->assertRedirect(route('projects.show', $project->slug));

    $project->refresh();
    expect($project->tables)->toBe($tables);
    expect($project->abbreviations)->toBe($abbreviations);
});

it('merges settings correctly when updating', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'settings' => [
            'department' => 'Old Department',
            'matric_number' => 'OLD123',
            'academic_session' => '2022/2023',
            'ai_assistance_level' => 'minimal',
            'some_other_setting' => 'value',
        ],
    ]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'description' => $project->description,
        'field_of_study' => $project->field_of_study,
        'mode' => $project->mode,
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
        'settings' => [
            'department' => 'New Department',
            'matric_number' => 'NEW456',
            'academic_session' => '2024/2025',
        ],
    ]);

    $response->assertRedirect(route('projects.show', $project->slug));

    $project->refresh();
    expect($project->settings)->toMatchArray([
        'department' => 'New Department',
        'matric_number' => 'NEW456',
        'academic_session' => '2024/2025',
        'ai_assistance_level' => 'minimal', // Preserved
        'some_other_setting' => 'value', // Preserved
    ]);
});

it('cannot update projects belonging to other users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'description' => 'Hacked description',
        'field_of_study' => 'Hacking',
        'mode' => 'auto',
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
    ]);

    $response->assertForbidden();

    // Verify nothing was changed
    $this->assertDatabaseMissing('projects', [
        'id' => $project->id,
        'description' => 'Hacked description',
    ]);
});

it('validates required fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        // Missing required fields
        'description' => 'Some description',
    ]);

    $response->assertSessionHasErrors(['field_of_study', 'mode', 'university', 'faculty', 'course']);
});

it('validates field_of_study is required', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'field_of_study' => '', // Empty
        'mode' => 'auto',
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
    ]);

    $response->assertSessionHasErrors('field_of_study');
});

it('validates mode must be auto or manual', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'field_of_study' => $project->field_of_study,
        'mode' => 'invalid_mode',
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
    ]);

    $response->assertSessionHasErrors('mode');
});

it('validates description max length', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'description' => str_repeat('a', 1001), // Exceeds 1000 character limit
        'field_of_study' => $project->field_of_study,
        'mode' => $project->mode,
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
    ]);

    $response->assertSessionHasErrors('description');
});

it('validates certification signatories structure', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'field_of_study' => $project->field_of_study,
        'mode' => $project->mode,
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
        'certification_signatories' => [
            ['name' => 'Dr. John'], // Missing title
        ],
    ]);

    $response->assertSessionHasErrors('certification_signatories.0.title');
});

it('validates abbreviations structure', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'field_of_study' => $project->field_of_study,
        'mode' => $project->mode,
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
        'abbreviations' => [
            ['abbreviation' => 'AI'], // Missing full_form
        ],
    ]);

    $response->assertSessionHasErrors('abbreviations.0.full_form');
});

it('redirects to project show page after successful update', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patch(route('projects.update', $project->slug), [
        'description' => 'Updated description',
        'field_of_study' => $project->field_of_study,
        'mode' => $project->mode,
        'university' => $project->university,
        'faculty' => $project->faculty,
        'course' => $project->course,
    ]);

    $response->assertRedirect(route('projects.show', $project->slug));
    $response->assertSessionHas('success', 'Project details updated successfully');
});
