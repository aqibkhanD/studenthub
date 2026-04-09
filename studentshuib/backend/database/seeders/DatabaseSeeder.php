<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            FormTypeSeeder::class,
            WorkflowSeeder::class,
            AdminUserSeeder::class,
            FormFieldSeeder::class,
            SlaEscalationRuleSeeder::class,
        ]);
    }
}
