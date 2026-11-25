<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesSeeder::class);

        if (app()->environment('local', 'testing') || $this->command?->confirm('Add demo data?', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
