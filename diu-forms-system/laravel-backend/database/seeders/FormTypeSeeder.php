<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\FormType;
use Illuminate\Database\Seeder;

class FormTypeSeeder extends Seeder
{
    public function run(): void
    {
        $reg = Department::where('code', 'REG')->value('id');
        $sad = Department::where('code', 'SAD')->value('id');
        $cda = Department::where('code', 'CDA')->value('id');
        $sdc = Department::where('code', 'SDC')->value('id');
        $fin = Department::where('code', 'FIN')->value('id');
        $its = Department::where('code', 'ITS')->value('id');
        $cca = Department::where('code', 'CCA')->value('id');

        $formTypes = [

            // ── Academic & Certification ───────────────────────────
            [
                'slug'          => 'bonafide',
                'name'          => 'Bonafide Certificate',
                'category'      => 'academic_certification',
                'department_id' => $reg,
                'sla_hours'     => 24,
                'requires_docs' => false,
                'auto_generate' => true,
                'instructions'  => 'Confirms your active enrollment. Auto-generated within 24 hours of approval.',
                'sort_order'    => 1,
                'fields'        => [
                    ['key' => 'purpose', 'label' => 'Purpose', 'type' => 'select', 'required' => true,
                     'options' => ['Bank Account Opening', 'Visa Application', 'Scholarship Application', 'Government Job Application', 'Other']],
                    ['key' => 'addressed_to', 'label' => 'Addressed To (Organization)', 'type' => 'text', 'required' => false],
                    ['key' => 'additional_info', 'label' => 'Additional Information', 'type' => 'textarea', 'required' => false],
                ],
            ],
            [
                'slug'          => 'character-certificate',
                'name'          => 'Character Certificate',
                'category'      => 'academic_certification',
                'department_id' => $reg,
                'sla_hours'     => 48,
                'requires_docs' => false,
                'auto_generate' => false,
                'instructions'  => 'Issued upon graduation or as needed. Requires approval from the Registrar.',
                'sort_order'    => 2,
                'fields'        => [
                    ['key' => 'purpose',      'label' => 'Purpose',       'type' => 'select',   'required' => true,
                     'options' => ['Job Application', 'Higher Education Admission', 'Visa Application', 'Other']],
                    ['key' => 'addressed_to', 'label' => 'Addressed To',  'type' => 'text',     'required' => false],
                ],
            ],
            [
                'slug'          => 'migration-certificate',
                'name'          => 'Migration Certificate',
                'category'      => 'academic_certification',
                'department_id' => $reg,
                'sla_hours'     => 72,
                'requires_docs' => true,
                'instructions'  => 'Required when transferring to another university. Attach your admission confirmation from the new institution.',
                'sort_order'    => 3,
                'fields'        => [
                    ['key' => 'destination_university', 'label' => 'Destination University', 'type' => 'text',     'required' => true],
                    ['key' => 'reason',                 'label' => 'Reason for Migration',   'type' => 'textarea', 'required' => true],
                    ['key' => 'admission_letter',       'label' => 'Admission Confirmation (PDF/Image)', 'type' => 'file', 'required' => true],
                ],
            ],
            [
                'slug'          => 'completion-certificate',
                'name'          => 'Completion Certificate',
                'category'      => 'academic_certification',
                'department_id' => $reg,
                'sla_hours'     => 48,
                'requires_docs' => false,
                'auto_generate' => false,
                'instructions'  => 'Confirms that all coursework has been completed. Available after final semester results are published.',
                'sort_order'    => 4,
                'fields'        => [
                    ['key' => 'program',  'label' => 'Program Completed', 'type' => 'text',   'required' => true],
                    ['key' => 'purpose',  'label' => 'Purpose',           'type' => 'select', 'required' => true,
                     'options' => ['Job Application', 'Visa', 'Further Studies', 'Other']],
                ],
            ],

            // ── Complaints ─────────────────────────────────────────
            [
                'slug'            => 'student-complaint',
                'name'            => 'Student Complaint',
                'category'        => 'complaint',
                'department_id'   => $sdc,
                'sla_hours'       => 96,
                'requires_docs'   => false,
                'allow_anonymous' => true,
                'instructions'    => 'All complaints are treated with strict confidentiality. You may submit anonymously.',
                'sort_order'      => 10,
                'fields'          => [
                    ['key' => 'complaint_type', 'label' => 'Type of Complaint', 'type' => 'select', 'required' => true,
                     'options' => ['Academic Misconduct', 'Harassment', 'Grading Dispute', 'Facility Issue', 'Staff Conduct', 'Other']],
                    ['key' => 'description',    'label' => 'Description',       'type' => 'textarea', 'required' => true],
                    ['key' => 'date_of_incident','label' => 'Date of Incident',  'type' => 'date',     'required' => false],
                    ['key' => 'evidence',        'label' => 'Supporting Evidence (optional)', 'type' => 'file', 'required' => false],
                ],
            ],

            // ── Career Counseling ──────────────────────────────────
            [
                'slug'          => 'career-counseling',
                'name'          => 'Career Counseling Request',
                'category'      => 'career_counseling',
                'department_id' => $cda,
                'sla_hours'     => 72,
                'requires_docs' => false,
                'instructions'  => 'Submit your request and a Career Development officer will reach out to schedule a session.',
                'sort_order'    => 20,
                'fields'        => [
                    ['key' => 'session_type',  'label' => 'Session Type',           'type' => 'select',   'required' => true,
                     'options' => ['CV Review', 'Career Planning', 'Interview Prep', 'Job Search Help', 'LinkedIn Profile Review']],
                    ['key' => 'availability',  'label' => 'Preferred Days & Times', 'type' => 'textarea', 'required' => true],
                    ['key' => 'goals',         'label' => 'What are your goals?',   'type' => 'textarea', 'required' => false],
                ],
            ],
            [
                'slug'          => 'internship-support',
                'name'          => 'Internship Support Request',
                'category'      => 'career_counseling',
                'department_id' => $cda,
                'sla_hours'     => 72,
                'requires_docs' => false,
                'sort_order'    => 21,
                'fields'        => [
                    ['key' => 'industry',    'label' => 'Target Industry',      'type' => 'select', 'required' => true,
                     'options' => ['Technology', 'Finance', 'Healthcare', 'Education', 'Manufacturing', 'NGO/INGO', 'Other']],
                    ['key' => 'description', 'label' => 'What kind of help do you need?', 'type' => 'textarea', 'required' => true],
                ],
            ],

            // ── Club & Co-curricular ───────────────────────────────
            [
                'slug'          => 'club-membership',
                'name'          => 'Club Membership Application',
                'category'      => 'club_cocurricular',
                'department_id' => $cca,
                'sla_hours'     => 120,
                'requires_docs' => false,
                'sort_order'    => 30,
                'fields'        => [
                    ['key' => 'club_name',   'label' => 'Club Name',           'type' => 'text',     'required' => true],
                    ['key' => 'reason',      'label' => 'Why do you want to join?', 'type' => 'textarea', 'required' => true],
                    ['key' => 'skills',      'label' => 'Relevant Skills',     'type' => 'textarea', 'required' => false],
                ],
            ],
            [
                'slug'          => 'new-club-proposal',
                'name'          => 'New Club Proposal',
                'category'      => 'club_cocurricular',
                'department_id' => $cca,
                'sla_hours'     => 168,
                'requires_docs' => true,
                'sort_order'    => 31,
                'fields'        => [
                    ['key' => 'club_name',      'label' => 'Proposed Club Name',     'type' => 'text',     'required' => true],
                    ['key' => 'objectives',     'label' => 'Club Objectives',        'type' => 'textarea', 'required' => true],
                    ['key' => 'activities',     'label' => 'Planned Activities',     'type' => 'textarea', 'required' => true],
                    ['key' => 'founding_members','label' => 'Founding Members (min 10)', 'type' => 'textarea', 'required' => true],
                    ['key' => 'proposal_doc',   'label' => 'Full Proposal Document', 'type' => 'file',     'required' => true],
                ],
            ],

            // ── Finance ────────────────────────────────────────────
            [
                'slug'          => 'fee-waiver',
                'name'          => 'Fee Waiver / Scholarship Request',
                'category'      => 'finance',
                'department_id' => $fin,
                'sla_hours'     => 120,
                'requires_docs' => true,
                'sort_order'    => 40,
                'fields'        => [
                    ['key' => 'waiver_type',  'label' => 'Waiver Type',            'type' => 'select',   'required' => true,
                     'options' => ['Full Waiver', 'Partial Waiver', 'Installment Plan', 'Hardship Scholarship']],
                    ['key' => 'reason',       'label' => 'Reason for Request',     'type' => 'textarea', 'required' => true],
                    ['key' => 'amount',       'label' => 'Amount Requested (BDT)', 'type' => 'text',     'required' => false],
                    ['key' => 'supporting_doc','label' => 'Supporting Document',   'type' => 'file',     'required' => true],
                ],
            ],

            // ── IT Support ─────────────────────────────────────────
            [
                'slug'          => 'it-support',
                'name'          => 'IT Support Ticket',
                'category'      => 'it_support',
                'department_id' => $its,
                'sla_hours'     => 24,
                'requires_docs' => false,
                'sort_order'    => 50,
                'fields'        => [
                    ['key' => 'issue_type',   'label' => 'Issue Type',     'type' => 'select', 'required' => true,
                     'options' => ['Email / Account Access', 'LMS / Blended', 'Lab / Computer', 'Network / WiFi', 'Software', 'Other']],
                    ['key' => 'description',  'label' => 'Description',    'type' => 'textarea', 'required' => true],
                    ['key' => 'screenshot',   'label' => 'Screenshot (optional)', 'type' => 'file', 'required' => false],
                ],
            ],
        ];

        foreach ($formTypes as $ft) {
            FormType::firstOrCreate(
                ['slug' => $ft['slug']],
                array_merge($ft, ['is_active' => true])
            );
        }
    }
}
