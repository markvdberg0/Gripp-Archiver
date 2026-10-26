<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->company(),
            'relation_type' => 'bedrijf',
            'is_customer' => true,
            'email' => fake()->safeEmail(),
            'visit_street' => fake()->streetName(),
            'visit_house_number' => (string) fake()->buildingNumber(),
            'visit_postcode' => fake()->postcode(),
            'visit_city' => fake()->city(),
            'visit_country' => 'Nederland',
            'invoice_send_by' => 'email',
            'payment_method' => 'overboeking',
            'payment_term_days' => 30,
            'active' => true,
        ];
    }

    public function supplier(): static
    {
        return $this->state(fn () => ['is_customer' => false, 'is_supplier' => true]);
    }
}
