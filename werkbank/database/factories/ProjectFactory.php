<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(100000, 999999),
            'name' => fake()->sentence(3),
            'company_id' => Company::factory(),
            'start_date' => now()->toDateString(),
        ];
    }
}
