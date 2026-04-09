<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run all seeders in dependency order.
     *
     * php artisan migrate --seed
     * — or —
     * php artisan db:seed
     */
    public function run(): void
    {
        // Order matters: departments before form_types and admins
        $this->call([
            DepartmentSeeder::class,
            FormTypeSeeder::class,
            AdminSeeder::class,
        ]);
    }
}
