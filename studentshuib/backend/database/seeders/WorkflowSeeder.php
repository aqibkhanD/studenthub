<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Generic single-step workflow (used by most form types)
        $genericId = DB::table('workflows')->insertGetId([
            'name'         => 'Standard Single Approval',
            'form_type_id' => null,
            'type'         => 'single',
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Migration Certificate — 3-step sequential
        $migrationFormId = DB::table('form_types')->where('slug', 'migration-certificate')->value('id');
        if ($migrationFormId) {
            $wfId = DB::table('workflows')->insertGetId([
                'name'         => 'Migration Multi-Step',
                'form_type_id' => $migrationFormId,
                'type'         => 'sequential',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $this->insertSteps($wfId, [
                [1, 'Registrar Review',      'registrar',       'approve',  48],
                [2, 'Exam Controller Sign',  'exam-controller', 'sign_off', 48],
                [3, 'Dean Final Approval',   'student-affairs', 'approve',  24],
            ]);
        }

        // Fee Waiver — 2-step sequential
        $feeWaiverFormId = DB::table('form_types')->where('slug', 'fee-waiver')->value('id');
        if ($feeWaiverFormId) {
            $wfId = DB::table('workflows')->insertGetId([
                'name'         => 'Fee Waiver Multi-Step',
                'form_type_id' => $feeWaiverFormId,
                'type'         => 'sequential',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $this->insertSteps($wfId, [
                [1, 'Finance Officer Review', 'finance',         'review',  48],
                [2, 'Dean Approval',          'student-affairs', 'approve', 48],
            ]);
        }

        // New Club Formation — 3-step sequential
        $newClubFormId = DB::table('form_types')->where('slug', 'new-club-formation')->value('id');
        if ($newClubFormId) {
            $wfId = DB::table('workflows')->insertGetId([
                'name'         => 'New Club Formation Chain',
                'form_type_id' => $newClubFormId,
                'type'         => 'sequential',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
            $this->insertSteps($wfId, [
                [1, 'Club Affairs Review',  'clubs',           'approve',  72],
                [2, 'Student Affairs OK',   'student-affairs', 'approve',  48],
                [3, "Dean Final Sign-off",  'student-affairs', 'sign_off', 48],
            ]);
        }

        $this->command->info('Workflows seeded.');
    }

    private function insertSteps(int $workflowId, array $steps): void
    {
        foreach ($steps as [$num, $name, $deptSlug, $action, $sla]) {
            $deptId = DB::table('departments')->where('slug', $deptSlug)->value('id');
            DB::table('workflow_steps')->insert([
                'workflow_id'     => $workflowId,
                'step_number'     => $num,
                'step_name'       => $name,
                'department_id'   => $deptId,
                'action_required' => $action,
                'sla_hours'       => $sla,
                'is_optional'     => false,
                'assigned_role'   => 'admin',
            ]);
        }
    }
}
