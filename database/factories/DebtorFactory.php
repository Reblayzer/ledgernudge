<?php

namespace Database\Factories;

use App\Models\Debtor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debtor>
 */
class DebtorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company' => fake()->optional()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->e164PhoneNumber(),
            'external_ref' => fake()->unique()->bothify('CUST-#####'),
            'tone_policy' => fake()->randomElement([
                'Professional, firm, and respectful.',
                'Warm and friendly; first-name basis; assume good faith.',
                'Brief and formal; reference the contract terms; no chit-chat.',
            ]),
        ];
    }
}
