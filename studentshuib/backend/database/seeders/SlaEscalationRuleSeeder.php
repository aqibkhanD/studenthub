<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SlaEscalationRuleSeeder extends Seeder
{
    /**
     * Seed default SLA escalation rules per department.
     *
     * Strategy:
     *  Level 1 — triggers at 50% of the department's SLA hours.
     *            escalate_to_user_id = NULL (means dept head).
     *  Level 2 — triggers at 75% of the department's SLA hours.
     *            escalate_to_user_id = NULL (super-admin will be notified by the
     *            SlaMonitorCommand when no specific user is set).
     *
     * form_type_id = NULL means the rule applies to ALL form types for that dept.
     */
    public function run(): void
    {
        // Map each department slug → [sla_hours, rules]
        // Rules are expressed as percentage of sla_hours (rounded to nearest hour).
        $departmentRules = [
            'registrar' => [
                'sla_hours' => 48,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => true],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
            'exam-controller' => [
                'sla_hours' => 72,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => true],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
            'student-affairs' => [
                'sla_hours' => 24,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => true],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
            'career-services' => [
                'sla_hours' => 48,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => false],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
            'clubs' => [
                'sla_hours' => 72,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => false],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
            'finance' => [
                'sla_hours' => 72,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => true],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
            'it' => [
                'sla_hours' => 24,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => false],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
            'dept-cse' => [
                'sla_hours' => 48,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => true],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
            'dept-eee' => [
                'sla_hours' => 48,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => true],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
            'dept-bba' => [
                'sla_hours' => 48,
                'levels' => [
                    ['level' => 1, 'pct' => 0.50, 'notify_student' => true],
                    ['level' => 2, 'pct' => 0.75, 'notify_student' => true],
                ],
            ],
        ];

        $inserted = 0;

        foreach ($departmentRules as $slug => $config) {
            $department = DB::table('departments')->where('slug', $slug)->first();

            if (! $department) {
                $this->command->warn("Department [{$slug}] not found — skipping.");
                continue;
            }

            foreach ($config['levels'] as $rule) {
                $escalateAfterHours = (int) round($config['sla_hours'] * $rule['pct']);

                // Ensure at least 1 hour gap between trigger points
                $escalateAfterHours = max(1, $escalateAfterHours);

                DB::table('sla_escalation_rules')->updateOrInsert(
                    [
                        'department_id'    => $department->id,
                        'form_type_id'     => null,
                        'escalation_level' => $rule['level'],
                    ],
                    [
                        'escalate_after_hours' => $escalateAfterHours,
                        'escalate_to_user_id'  => null, // NULL = department head
                        'notify_student'       => $rule['notify_student'],
                        'created_at'           => now(),
                    ]
                );

                $inserted++;
            }
        }

        $this->command->info("SLA escalation rules seeded: {$inserted} rules across " . count($departmentRules) . ' departments.');
    }
}
