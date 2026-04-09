<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => "Registrar's Office",     'slug' => 'registrar',       'email' => 'registrar@diu.edu.bd',       'sla_hours' => 48],
            ['name' => 'Exam Controller',         'slug' => 'exam-controller', 'email' => 'exam@diu.edu.bd',            'sla_hours' => 72],
            ['name' => 'Student Affairs',         'slug' => 'student-affairs', 'email' => 'studentaffairs@diu.edu.bd',  'sla_hours' => 24],
            ['name' => 'Career Services',         'slug' => 'career-services', 'email' => 'career@diu.edu.bd',          'sla_hours' => 48],
            ['name' => 'Club & Co-curricular',    'slug' => 'clubs',           'email' => 'clubs@diu.edu.bd',           'sla_hours' => 72],
            ['name' => 'Finance & Accounts',      'slug' => 'finance',         'email' => 'finance@diu.edu.bd',         'sla_hours' => 72],
            ['name' => 'IT Department',           'slug' => 'it',              'email' => 'it@diu.edu.bd',              'sla_hours' => 24],
            ['name' => 'Department Office (CSE)', 'slug' => 'dept-cse',        'email' => 'cse@diu.edu.bd',             'sla_hours' => 48],
            ['name' => 'Department Office (EEE)', 'slug' => 'dept-eee',        'email' => 'eee@diu.edu.bd',             'sla_hours' => 48],
            ['name' => 'Department Office (BBA)', 'slug' => 'dept-bba',        'email' => 'bba@diu.edu.bd',             'sla_hours' => 48],
        ];

        foreach ($departments as $dept) {
            DB::table('departments')->updateOrInsert(
                ['slug' => $dept['slug']],
                array_merge($dept, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Departments seeded: ' . count($departments));
    }
}
