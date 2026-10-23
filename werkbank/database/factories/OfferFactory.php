<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(100000, 999999),
            'name' => fake()->sentence(3),
            'company_id' => Company::factory(),
            'status' => 'concept',
            'date' => now()->toDateString(),
            'total_excl' => 0, 'total_vat' => 0, 'total_incl' => 0,
        ];
    }
}
