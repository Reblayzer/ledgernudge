<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\Debtor;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Builds a small, coherent demo dataset: one operator plus a handful of debtors,
 * each with a mix of past-due and paid invoices, and a matching invoice.created
 * event per invoice so the append-only log is non-empty from the start.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'operator@ledgernudge.test'],
            [
                'name' => 'Demo Operator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        Debtor::factory()
            ->count(8)
            ->create()
            ->each(function (Debtor $debtor): void {
                $invoices = Invoice::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->state(fn () => fake()->boolean(70)
                        ? ['status' => InvoiceStatus::Open]
                        : [])
                    ->for($debtor)
                    ->create();

                foreach ($invoices as $invoice) {
                    Event::create([
                        'debtor_id' => $debtor->id,
                        'invoice_id' => $invoice->id,
                        'type' => Event::INVOICE_CREATED,
                        'data' => [
                            'number' => $invoice->number,
                            'amount_cents' => $invoice->amount_cents,
                        ],
                    ]);
                }
            });
    }
}
