<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FormTypeSeeder extends Seeder
{
    public function run(): void
    {
        // dept IDs match DepartmentSeeder insertion order
        $formTypes = [
            // --- Academic & Certification (dept 1 = Registrar) ---
            [
                'name' => 'Bonafide Certificate',      'slug' => 'bonafide-certificate',
                'category' => 'academic_certification', 'dept_slug' => 'registrar',
                'requires_documents' => false, 'allow_anonymous' => false,
                'auto_generate_doc'  => true,  'sla_hours' => 24, 'sort_order' => 1,
                'instructions' => 'Request a bonafide certificate confirming your current enrolment at DIU. Processing takes up to 24 hours.',
            ],
            [
                'name' => 'Character Certificate',     'slug' => 'character-certificate',
                'category' => 'academic_certification', 'dept_slug' => 'registrar',
                'requires_documents' => false, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 48, 'sort_order' => 2,
                'instructions' => 'Request a character certificate from the university.',
            ],
            [
                'name' => 'Migration Certificate',     'slug' => 'migration-certificate',
                'category' => 'academic_certification', 'dept_slug' => 'registrar',
                'requires_documents' => true, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 120, 'sort_order' => 3,
                'instructions' => 'Migration requires approval from Registrar, Exam Controller, and Dean. Please upload supporting documents. Processing time: up to 5 working days.',
            ],
            [
                'name' => 'Eligibility Certificate',   'slug' => 'eligibility-certificate',
                'category' => 'academic_certification', 'dept_slug' => 'exam-controller',
                'requires_documents' => true, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 72, 'sort_order' => 4,
                'instructions' => 'Upload your last semester result sheet and identity documents.',
            ],
            [
                'name' => 'Completion Letter',         'slug' => 'completion-letter',
                'category' => 'academic_certification', 'dept_slug' => 'exam-controller',
                'requires_documents' => false, 'allow_anonymous' => false,
                'auto_generate_doc'  => true,  'sla_hours' => 72, 'sort_order' => 5,
                'instructions' => 'Available only after all clearances are complete.',
            ],

            // --- Complaints (dept 3 = Student Affairs) ---
            [
                'name' => 'Student Complaint',         'slug' => 'student-complaint',
                'category' => 'complaint',              'dept_slug' => 'student-affairs',
                'requires_documents' => true,  'allow_anonymous' => true,
                'auto_generate_doc'  => false, 'sla_hours' => 24, 'sort_order' => 10,
                'instructions' => 'You may submit this complaint anonymously. Your identity will not be disclosed to the party complained against.',
            ],
            [
                'name' => 'Misconduct Report',         'slug' => 'misconduct-report',
                'category' => 'complaint',              'dept_slug' => 'student-affairs',
                'requires_documents' => true,  'allow_anonymous' => true,
                'auto_generate_doc'  => false, 'sla_hours' => 12, 'sort_order' => 11,
                'instructions' => 'For serious misconduct reports. Anonymous submission is supported.',
            ],

            // --- Career Counselling (dept 4) ---
            [
                'name' => 'Career Counseling Request', 'slug' => 'career-counseling',
                'category' => 'career_counseling',      'dept_slug' => 'career-services',
                'requires_documents' => false, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 48, 'sort_order' => 20,
                'instructions' => 'Book a session with a career counsellor. Please indicate preferred dates.',
            ],
            [
                'name' => 'Internship Letter Request', 'slug' => 'internship-letter',
                'category' => 'career_counseling',      'dept_slug' => 'career-services',
                'requires_documents' => false, 'allow_anonymous' => false,
                'auto_generate_doc'  => true,  'sla_hours' => 48, 'sort_order' => 21,
                'instructions' => 'Provide the company name, internship start date, and supervisor contact.',
            ],

            // --- Club & Co-curricular (dept 5) ---
            [
                'name' => 'Club Membership Application', 'slug' => 'club-membership',
                'category' => 'club_cocurricular',       'dept_slug' => 'clubs',
                'requires_documents' => false, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 72, 'sort_order' => 30,
                'instructions' => 'Select the club you wish to join and briefly describe your motivation.',
            ],
            [
                'name' => 'New Club Formation Request', 'slug' => 'new-club-formation',
                'category' => 'club_cocurricular',       'dept_slug' => 'clubs',
                'requires_documents' => true, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 168, 'sort_order' => 31,
                'instructions' => 'Upload a club proposal (goals, activities, founding members list). Requires Dean approval.',
            ],
            [
                'name' => 'Event/Committee Approval',   'slug' => 'event-approval',
                'category' => 'club_cocurricular',       'dept_slug' => 'clubs',
                'requires_documents' => true, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 96, 'sort_order' => 32,
                'instructions' => 'Upload event plan with proposed date, venue, and budget.',
            ],

            // --- Finance (dept 6) ---
            [
                'name' => 'Fee Waiver Request',         'slug' => 'fee-waiver',
                'category' => 'finance',                 'dept_slug' => 'finance',
                'requires_documents' => true, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 96, 'sort_order' => 40,
                'instructions' => 'Upload supporting financial documents (family income certificate, etc.).',
            ],
            [
                'name' => 'Scholarship Application',    'slug' => 'scholarship-application',
                'category' => 'finance',                 'dept_slug' => 'finance',
                'requires_documents' => true, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 120, 'sort_order' => 41,
                'instructions' => 'Attach last semester transcript and supporting documents.',
            ],

            // --- IT (dept 7) ---
            [
                'name' => 'IT Support Ticket',          'slug' => 'it-support',
                'category' => 'it_support',              'dept_slug' => 'it',
                'requires_documents' => false, 'allow_anonymous' => false,
                'auto_generate_doc'  => false, 'sla_hours' => 24, 'sort_order' => 50,
                'instructions' => 'Describe your issue in detail including any error messages.',
            ],
        ];

        foreach ($formTypes as $ft) {
            $deptId = DB::table('departments')->where('slug', $ft['dept_slug'])->value('id');

            if (!$deptId) {
                $this->command->warn("Department not found for slug: {$ft['dept_slug']} — skipping {$ft['name']}");
                continue;
            }

            DB::table('form_types')->updateOrInsert(
                ['slug' => $ft['slug']],
                [
                    'name'                => $ft['name'],
                    'category'            => $ft['category'],
                    'department_id'       => $deptId,
                    'instructions'        => $ft['instructions'],
                    'requires_documents'  => $ft['requires_documents'],
                    'allow_anonymous'     => $ft['allow_anonymous'],
                    'auto_generate_doc'   => $ft['auto_generate_doc'],
                    'sla_hours'           => $ft['sla_hours'],
                    'sort_order'          => $ft['sort_order'],
                    'is_active'           => true,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]
            );
        }

        $this->command->info('Form types seeded: ' . count($formTypes));
    }
}
