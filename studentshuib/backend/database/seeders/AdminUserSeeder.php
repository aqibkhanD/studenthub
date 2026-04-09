<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        DB::table('users')->updateOrInsert(
            ['email' => 'superadmin@diu.edu.bd'],
            [
                'name'              => 'System Administrator',
                'email'             => 'superadmin@diu.edu.bd',
                'password'          => Hash::make('Admin@1234!'),
                'role'              => 'super_admin',
                'is_active'         => true,
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        // Registrar admin
        $registrarDeptId = DB::table('departments')->where('slug', 'registrar')->value('id');
        DB::table('users')->updateOrInsert(
            ['email' => 'registrar.staff@diu.edu.bd'],
            [
                'name'              => 'Registrar Office Staff',
                'email'             => 'registrar.staff@diu.edu.bd',
                'password'          => Hash::make('Admin@1234!'),
                'role'              => 'admin',
                'department_id'     => $registrarDeptId,
                'is_active'         => true,
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        // Sample student
        DB::table('users')->updateOrInsert(
            ['email' => 'test.student@diu.edu.bd'],
            [
                'student_id'        => '221-15-5812',
                'name'              => 'Test Student',
                'email'             => 'test.student@diu.edu.bd',
                'phone'             => '+8801712345678',
                'password'          => Hash::make('Student@1234!'),
                'role'              => 'student',
                'program'           => 'B.Sc. in CSE',
                'batch'             => '55',
                'semester'          => '7th',
                'is_active'         => true,
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $this->command->info('Admin and test users seeded.');
        $this->command->warn('IMPORTANT: Change default passwords before going to production!');
    }
}
