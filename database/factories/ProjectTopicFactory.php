<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ProjectTopic>
 */
class ProjectTopicFactory extends Factory
{
    public function definition(): array
    {
        $field = $this->faker->randomElement(['Computer Science', 'Business Administration', 'Mechanical Engineering']);

        return [
            'project_id' => \App\Models\Project::factory(),
            'field_of_study' => $field,
            'faculty' => $this->faker->randomElement(['Engineering', 'Management Sciences', 'Science']),
            'department' => $this->faker->randomElement(['Computer Science', 'Mechanical Engineering', 'Business Administration']),
            'course' => $this->faker->randomElement(['Software Engineering', 'Thermodynamics', 'Marketing Strategy']),
            'university' => $this->faker->company,
            'academic_level' => $this->faker->randomElement(['undergraduate', 'postgraduate']),
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph,
            'difficulty' => $this->faker->randomElement(['easy', 'medium', 'hard']),
            'timeline' => $this->faker->randomElement(['3 months', '6 months', '9 months']),
            'resource_level' => $this->faker->randomElement(['low', 'medium', 'high']),
            'feasibility_score' => $this->faker->numberBetween(60, 95),
            'keywords' => $this->faker->words(5),
            'research_type' => $this->faker->randomElement(['qualitative', 'quantitative', 'mixed']),
            'selection_count' => 0,
            'last_selected_at' => null,
        ];
    }
}
