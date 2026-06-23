<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the LedgerNudge demo dataset.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
