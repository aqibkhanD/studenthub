<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $reg = Department::where('code', 'REG')->value('id');
        $sad = Department::where('code', 'SAD')->value('id');
        $cda = Department::where('code', 'CDA')->value('id');
        $sdc = Department::where('code', 'SDC')->value('id');

        $admins = [
            [
                'name'          => 'Rafiq Ahmed',
                'email'         => 'rafiq.ahmed@diu.edu.bd',
                'phone'         => '+8801711223344',
                'role'          => 'super_admin',
                'department_id' => null,
                'password'      => 'admin@diu2024',   // CHANGE IN PRODUCTION
            ],
            [
                'name'          => 'Nasreen Akter',
                'email'         => 'nasreen.akter@diu.edu.bd',
                'phone'         => '+8801811223345',
                'role'          => 'admin',
                'department_id' => $reg,
                'password'      => 'admin@diu2024',
            ],
            [
                'name'          => 'Kamal Hossain',
                'email'         => 'kamal.hossain@diu.edu.bd',
                'phone'         => '+8801711223346',
                'role'          => 'admin',
                'department_id' => $sad,
                'password'      => 'admin@diu2024',
            ],
            [
                'name'          => 'Shapna Begum',
                'email'         => 'shapna.begum@diu.edu.bd',
                'phone'         => '+8801911223347',
                'role'          => 'admin',
                'department_id' => $cda,
                'password'      => 'admin@diu2024',
            ],
            [
                'name'          => 'Tariq Mahmud',
                'email'         => 'tariq.mahmud@diu.edu.bd',
                'phone'         => '+8801611223348',
                'role'          => 'admin',
                'department_id' => $sdc,
                'password'      => 'admin@diu2024',
            ],
        ];

        foreach ($admins as $data) {
            Admin::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'phone'             => $data['phone'],
                    'role'              => $data['role'],
                    'department_id'     => $data['department_id'],
                    'password'          => Hash::make($data['password']),
                    'is_active'         => true,
                    'unsubscribe_token' => Str::random(64),
                ]
            );
        }

        $this->command->info('Admins seeded. IMPORTANT: Change all passwords before going to production.');
    }
}
