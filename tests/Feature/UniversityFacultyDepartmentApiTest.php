<?php

use App\Models\Faculty;
use App\Models\University;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\FacultySeeder;
use Database\Seeders\UniversitySeeder;

beforeEach(function () {
    $this->seed([
        UniversitySeeder::class,
        FacultySeeder::class,
        DepartmentSeeder::class,
    ]);
});

it('can fetch all universities', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/universities');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'universities' => [
            '*' => ['id', 'name', 'short_name', 'slug', 'type', 'location', 'state'],
        ],
    ]);
});

it('can fetch all faculties', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/faculties');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'faculties' => [
            '*' => ['id', 'name', 'slug', 'description'],
        ],
    ]);
});

it('can fetch departments by faculty', function () {
    $user = User::factory()->create();
    $faculty = Faculty::where('slug', 'communication-and-media-studies')->first();

    $response = $this->actingAs($user)->getJson("/api/faculties/{$faculty->id}/departments");

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'departments' => [
            '*' => ['id', 'faculty_id', 'name', 'slug', 'code'],
        ],
    ]);

    // Verify departments belong to the correct faculty
    $data = $response->json();
    foreach ($data['departments'] as $department) {
        expect($department['faculty_id'])->toBe($faculty->id);
    }
});

it('returns communication and media studies faculty with correct departments', function () {
    $user = User::factory()->create();
    $faculty = Faculty::where('slug', 'communication-and-media-studies')->first();

    $response = $this->actingAs($user)->getJson("/api/faculties/{$faculty->id}/departments");

    $response->assertSuccessful();

    $departments = $response->json('departments');
    $departmentNames = array_column($departments, 'name');

    expect($departments)->toHaveCount(6);
    expect($departmentNames)->toContain('Mass Communication');
    expect($departmentNames)->toContain('Journalism');
    expect($departmentNames)->toContain('Public Relations');
    expect($departmentNames)->toContain('Broadcasting');
    expect($departmentNames)->toContain('Film and Multimedia');
    expect($departmentNames)->toContain('Advertising');
});

it('can fetch all departments', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/departments');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'departments' => [
            '*' => ['id', 'faculty_id', 'name', 'slug', 'code'],
        ],
    ]);
});

it('requires authentication for university endpoint', function () {
    $response = $this->getJson('/api/universities');

    $response->assertUnauthorized();
});

it('requires authentication for faculty endpoint', function () {
    $response = $this->getJson('/api/faculties');

    $response->assertUnauthorized();
});

it('requires authentication for department endpoint', function () {
    $response = $this->getJson('/api/departments');

    $response->assertUnauthorized();
});

it('filters universities by type', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/universities?type=federal');

    $response->assertSuccessful();

    $universities = $response->json('universities');
    foreach ($universities as $university) {
        expect($university['type'])->toBe('federal');
    }
});

it('returns only active universities', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/universities');

    $response->assertSuccessful();

    $universities = $response->json('universities');
    foreach ($universities as $university) {
        // All returned universities should be active
        $dbUniversity = University::find($university['id']);
        expect($dbUniversity->is_active)->toBeTrue();
    }
});

it('returns only active faculties', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/faculties');

    $response->assertSuccessful();

    $faculties = $response->json('faculties');
    foreach ($faculties as $faculty) {
        $dbFaculty = Faculty::find($faculty['id']);
        expect($dbFaculty->is_active)->toBeTrue();
    }
});
