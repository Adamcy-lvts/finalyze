<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'title' => $this->faker->sentence(4),
            'slug' => Str::uuid()->toString(),
            'type' => $this->faker->randomElement(['undergraduate', 'postgraduate', 'hnd', 'nd']),
            'status' => 'setup',
            'topic_status' => 'topic_selection',
            'mode' => $this->faker->randomElement(['auto', 'manual']),
            'field_of_study' => $this->faker->words(2, true),
            'university' => $this->faker->company,
            'faculty' => $this->faker->words(2, true),
            'course' => $this->faker->words(3, true),
            'current_chapter' => 1,
            'is_active' => false,
            'settings' => [],
            'setup_data' => [],
        ];
    }
}
