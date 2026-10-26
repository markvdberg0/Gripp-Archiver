<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'subject' => fake()->sentence(3),
            'status' => 'concept',
            'payment_method' => 'overboeking',
        ];
    }
}
