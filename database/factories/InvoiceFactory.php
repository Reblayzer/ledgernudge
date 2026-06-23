<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Debtor;
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
            'debtor_id' => Debtor::factory(),
            'number' => 'INV-'.fake()->unique()->numberBetween(10000, 99999),
            'amount_cents' => fake()->numberBetween(5_000, 500_000),
            'amount_paid_cents' => 0,
            'currency' => 'usd',
            'due_date' => fake()->dateTimeBetween('-30 days', '+30 days')->format('Y-m-d'),
            'status' => InvoiceStatus::Open,
            'paid_at' => null,
        ];
    }

    public function pastDue(): static
    {
        return $this->state(fn () => [
            'due_date' => fake()->dateTimeBetween('-45 days', '-1 days')->format('Y-m-d'),
            'status' => InvoiceStatus::Open,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_paid_cents' => $attributes['amount_cents'],
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
