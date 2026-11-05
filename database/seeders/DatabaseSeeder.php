<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Funds, purposes, the default rate and default accounts come from the migrations.
     * The demo data is for local development only and must never reach production.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipped: demo data is only seeded locally. Use php artisan fund:create-admin.');

            return;
        }

        $this->call(DemoDataSeeder::class);
    }
}
