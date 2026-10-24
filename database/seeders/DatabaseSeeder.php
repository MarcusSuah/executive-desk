<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only roles and permissions are seeded by default. Sample data is opt-in and
     * restricted to local environments: php artisan db:seed --class=DevelopmentSeeder
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
}
