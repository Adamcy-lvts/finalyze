<?php

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectPrelimService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves preliminary pages with defaults and substitutions', function () {
    $user = User::factory()->create(['name' => 'Test User']);

    $project = Project::factory()
        ->for($user)
        ->create([
            'title' => 'Sample Project',
            'type' => 'thesis',
            'course' => 'Computer Science',
            'dedication' => null,
            'acknowledgements' => null,
            'abstract' => null,
            'declaration' => null,
            'certification' => null,
        ]);

    $service = app(ProjectPrelimService::class);
    $pages = collect($service->resolve($project));

    expect($pages)->toHaveCount(5);
    expect($pages->firstWhere('slug', 'declaration')['html'])->toContain('Test User');
    expect($pages->firstWhere('slug', 'dedication')['html'])->not->toBe('');
    expect($pages->firstWhere('slug', 'abstract')['html'])->toContain('This');
});

it('prefers project overrides over defaults', function () {
    $project = Project::factory()
        ->for(User::factory())
        ->create([
            'title' => 'Override Example',
            'dedication' => '<p>Custom dedication</p>',
            'abstract' => '<p>Custom abstract</p>',
        ]);

    $pages = collect(app(ProjectPrelimService::class)->resolve($project));

    expect($pages->firstWhere('slug', 'dedication')['html'])->toContain('Custom dedication');
    expect($pages->firstWhere('slug', 'abstract')['html'])->toContain('Custom abstract');
});
