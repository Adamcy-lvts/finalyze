<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Chapter>
 */
class ChapterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'chapter_number' => $this->faker->numberBetween(1, 6),
            'title' => $this->faker->sentence(3),
            'slug' => Str::slug($this->faker->unique()->sentence(3)),
            'content' => $this->faker->paragraphs(3, true),
            'status' => 'draft',
            'word_count' => $this->faker->numberBetween(500, 1500),
            'target_word_count' => $this->faker->numberBetween(1500, 2500),
            'outline' => [],
            'summary' => $this->faker->sentence(),
            'version' => 1,
        ];
    }
}
