<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Demonstration data must always be requested explicitly.
        $this->call(SystemSeeder::class);
    }
}
