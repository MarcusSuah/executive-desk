<?php

namespace Database\Seeders;

use App\Support\RolesAndPermissions;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Create the application's roles and permissions.
     */
    public function run(): void
    {
        RolesAndPermissions::sync();
    }
}
