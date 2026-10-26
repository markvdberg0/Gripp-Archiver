<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->sentence(3),
            'supplier_id' => Company::factory()->supplier(),
            'date' => now()->toDateString(),
        ];
    }
}
