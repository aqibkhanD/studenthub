<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'name'      => "Registrar's Office",
                'code'      => 'REG',
                'email'     => 'registrar@diu.edu.bd',
                'head_name' => 'Md. Anwarul Haque',
                'sla_hours' => 24,
            ],
            [
                'name'      => 'Student Affairs Division',
                'code'      => 'SAD',
                'email'     => 'student.affairs@diu.edu.bd',
                'head_name' => 'Dr. Fahmida Sultana',
                'sla_hours' => 48,
            ],
            [
                'name'      => 'Career Development & Alumni',
                'code'      => 'CDA',
                'email'     => 'career@diu.edu.bd',
                'head_name' => 'Md. Rafiqul Islam',
                'sla_hours' => 72,
            ],
            [
                'name'      => 'Student Discipline Committee',
                'code'      => 'SDC',
                'email'     => 'discipline@diu.edu.bd',
                'head_name' => 'Prof. Abul Kalam',
                'sla_hours' => 96,
            ],
            [
                'name'      => 'Finance & Accounts',
                'code'      => 'FIN',
                'email'     => 'finance@diu.edu.bd',
                'head_name' => 'Md. Shahidul Islam',
                'sla_hours' => 72,
            ],
            [
                'name'      => 'IT Support & Infrastructure',
                'code'      => 'ITS',
                'email'     => 'itsupport@diu.edu.bd',
                'head_name' => 'Engr. Tanvir Ahmed',
                'sla_hours' => 24,
            ],
            [
                'name'      => 'Club & Co-curricular Activities',
                'code'      => 'CCA',
                'email'     => 'clubs@diu.edu.bd',
                'head_name' => 'Ms. Nusrat Jahan',
                'sla_hours' => 120,
            ],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], $dept);
        }
    }
}
