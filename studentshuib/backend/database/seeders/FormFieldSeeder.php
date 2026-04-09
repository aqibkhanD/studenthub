<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds form_fields for all 15 form types.
 * Fields are idempotent — safe to re-run.
 */
class FormFieldSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = $this->definitions();

        foreach ($definitions as $slug => $fields) {
            $formTypeId = DB::table('form_types')->where('slug', $slug)->value('id');

            if (!$formTypeId) {
                $this->command->warn("Form type not found: {$slug} — skipping.");
                continue;
            }

            foreach ($fields as $order => $field) {
                DB::table('form_fields')->updateOrInsert(
                    ['form_type_id' => $formTypeId, 'field_key' => $field['field_key']],
                    [
                        'form_type_id'     => $formTypeId,
                        'label'            => $field['label'],
                        'field_key'        => $field['field_key'],
                        'field_type'       => $field['field_type'],
                        'options'          => isset($field['options']) ? json_encode($field['options']) : null,
                        'is_required'      => $field['is_required'] ?? true,
                        'placeholder'      => $field['placeholder'] ?? null,
                        'help_text'        => $field['help_text'] ?? null,
                        'validation_rules' => $field['validation_rules'] ?? null,
                        'sort_order'       => $order + 1,
                        'is_active'        => true,
                    ]
                );
            }
        }

        $total = DB::table('form_fields')->count();
        $this->command->info("Form fields seeded. Total: {$total}");
    }

    // ----------------------------------------------------------------
    // Field definitions per form type slug
    // ----------------------------------------------------------------
    private function definitions(): array
    {
        return [

            // --------------------------------------------------------
            // Bonafide Certificate
            // --------------------------------------------------------
            'bonafide-certificate' => [
                [
                    'label' => 'Purpose of Certificate',
                    'field_key' => 'purpose',
                    'field_type' => 'select',
                    'options' => ['Scholarship Application', 'Visa Application', 'Bank Account Opening', 'Employment', 'Hostel Accommodation', 'Other'],
                    'is_required' => true,
                    'help_text' => 'Select the reason you need this certificate.',
                ],
                [
                    'label' => 'Additional Details',
                    'field_key' => 'additional_details',
                    'field_type' => 'textarea',
                    'is_required' => false,
                    'placeholder' => 'Any specific information to include on the certificate (e.g. company name for visa)...',
                ],
                [
                    'label' => 'Address To (Organisation)',
                    'field_key' => 'address_to',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'e.g. Embassy of Germany, ABC Company Ltd.',
                    'help_text' => 'Leave blank to use "Whom It May Concern".',
                ],
            ],

            // --------------------------------------------------------
            // Character Certificate
            // --------------------------------------------------------
            'character-certificate' => [
                [
                    'label' => 'Purpose',
                    'field_key' => 'purpose',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'e.g. Job Application, Higher Study Abroad',
                ],
                [
                    'label' => 'Address To (Organisation)',
                    'field_key' => 'address_to',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'e.g. University of Toronto',
                ],
            ],

            // --------------------------------------------------------
            // Migration Certificate
            // --------------------------------------------------------
            'migration-certificate' => [
                [
                    'label' => 'Destination University / Institution',
                    'field_key' => 'destination_university',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'Full name of the institution you are transferring to',
                ],
                [
                    'label' => 'Destination Country',
                    'field_key' => 'destination_country',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'e.g. Canada',
                ],
                [
                    'label' => 'Reason for Migration',
                    'field_key' => 'reason',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Explain your reason for transferring...',
                ],
                [
                    'label' => 'Last Completed Semester',
                    'field_key' => 'last_semester',
                    'field_type' => 'select',
                    'options' => ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'],
                    'is_required' => true,
                ],
                [
                    'label' => 'CGPA (Last Semester)',
                    'field_key' => 'cgpa',
                    'field_type' => 'number',
                    'is_required' => true,
                    'placeholder' => '3.75',
                    'validation_rules' => 'numeric|min:0|max:4',
                    'help_text' => 'CGPA on a 4.0 scale.',
                ],
                [
                    'label' => 'Clearance Documents',
                    'field_key' => 'clearance_documents',
                    'field_type' => 'file',
                    'is_required' => true,
                    'help_text' => 'Upload library clearance, fee clearance, and any other relevant documents. PDF/JPG only.',
                ],
            ],

            // --------------------------------------------------------
            // Eligibility Certificate
            // --------------------------------------------------------
            'eligibility-certificate' => [
                [
                    'label' => 'Program Applying For',
                    'field_key' => 'program_applying',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'e.g. MSc Computer Science',
                ],
                [
                    'label' => 'Institution Name',
                    'field_key' => 'institution',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'Name of the institution requiring this certificate',
                ],
                [
                    'label' => 'Purpose',
                    'field_key' => 'purpose',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'e.g. Admission to MSc programme',
                ],
                [
                    'label' => 'Supporting Documents (Transcript / Result Sheet)',
                    'field_key' => 'supporting_docs',
                    'field_type' => 'file',
                    'is_required' => true,
                    'help_text' => 'Upload your latest result sheet or transcript.',
                ],
            ],

            // --------------------------------------------------------
            // Completion Letter
            // --------------------------------------------------------
            'completion-letter' => [
                [
                    'label' => 'Address To',
                    'field_key' => 'address_to',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'Organisation or authority to address the letter to',
                ],
                [
                    'label' => 'Purpose',
                    'field_key' => 'purpose',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'e.g. Government job application, Post-graduate admission',
                ],
            ],

            // --------------------------------------------------------
            // Student Complaint
            // --------------------------------------------------------
            'student-complaint' => [
                [
                    'label' => 'Subject of Complaint',
                    'field_key' => 'subject',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'Brief title of your complaint',
                ],
                [
                    'label' => 'Nature of Complaint',
                    'field_key' => 'nature',
                    'field_type' => 'select',
                    'options' => ['Academic', 'Administrative', 'Harassment / Misconduct', 'Facilities', 'Financial', 'Other'],
                    'is_required' => true,
                ],
                [
                    'label' => 'Date of Incident',
                    'field_key' => 'date_of_incident',
                    'field_type' => 'date',
                    'is_required' => false,
                ],
                [
                    'label' => 'Against Whom (if applicable)',
                    'field_key' => 'against_whom',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'Name or designation — leave blank to keep anonymous',
                    'help_text' => 'This field will NOT be shared with the person mentioned if you submit anonymously.',
                ],
                [
                    'label' => 'Description',
                    'field_key' => 'description',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Describe what happened in detail...',
                ],
                [
                    'label' => 'Supporting Evidence',
                    'field_key' => 'evidence',
                    'field_type' => 'file',
                    'is_required' => false,
                    'help_text' => 'Upload screenshots, photos, or documents that support your complaint.',
                ],
            ],

            // --------------------------------------------------------
            // Misconduct Report
            // --------------------------------------------------------
            'misconduct-report' => [
                [
                    'label' => 'Type of Misconduct',
                    'field_key' => 'misconduct_type',
                    'field_type' => 'select',
                    'options' => ['Cheating / Plagiarism', 'Verbal Abuse', 'Physical Misconduct', 'Cyberbullying', 'Property Damage', 'Ragging', 'Other'],
                    'is_required' => true,
                ],
                [
                    'label' => 'Date of Incident',
                    'field_key' => 'incident_date',
                    'field_type' => 'date',
                    'is_required' => true,
                ],
                [
                    'label' => 'Location of Incident',
                    'field_key' => 'location',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'e.g. Room 301, Lab Block, Main Cafeteria',
                ],
                [
                    'label' => 'Description',
                    'field_key' => 'description',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Describe the incident in detail...',
                ],
                [
                    'label' => 'Witnesses',
                    'field_key' => 'witnesses',
                    'field_type' => 'textarea',
                    'is_required' => false,
                    'placeholder' => 'Name(s) and student/staff ID(s) of any witnesses',
                ],
                [
                    'label' => 'Evidence',
                    'field_key' => 'evidence',
                    'field_type' => 'file',
                    'is_required' => false,
                ],
            ],

            // --------------------------------------------------------
            // Career Counseling Request
            // --------------------------------------------------------
            'career-counseling' => [
                [
                    'label' => 'Counseling Topic',
                    'field_key' => 'topic',
                    'field_type' => 'select',
                    'options' => ['CV / Resume Review', 'Interview Preparation', 'Career Path Guidance', 'Higher Study Abroad', 'Job Search Strategy', 'Internship Guidance', 'LinkedIn Profile', 'Other'],
                    'is_required' => true,
                ],
                [
                    'label' => 'Current Situation',
                    'field_key' => 'current_situation',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Briefly describe your current academic/career situation...',
                ],
                [
                    'label' => 'Specific Questions or Goals',
                    'field_key' => 'specific_goals',
                    'field_type' => 'textarea',
                    'is_required' => false,
                    'placeholder' => 'What specific questions do you want answered in this session?',
                ],
                [
                    'label' => 'Preferred Meeting Dates',
                    'field_key' => 'preferred_dates',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'e.g. Mon/Wed afternoon, Tue morning',
                    'help_text' => 'We will confirm availability and contact you.',
                ],
                [
                    'label' => 'Upload CV / Resume (optional)',
                    'field_key' => 'cv_upload',
                    'field_type' => 'file',
                    'is_required' => false,
                    'help_text' => 'Sharing your CV in advance helps us prepare.',
                ],
            ],

            // --------------------------------------------------------
            // Internship Letter Request
            // --------------------------------------------------------
            'internship-letter' => [
                [
                    'label' => 'Company / Organisation Name',
                    'field_key' => 'company_name',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'e.g. Bangladesh Software Ltd.',
                ],
                [
                    'label' => 'Internship Role / Position',
                    'field_key' => 'internship_role',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'e.g. Junior Software Engineer Intern',
                ],
                [
                    'label' => 'Supervisor Name',
                    'field_key' => 'supervisor_name',
                    'field_type' => 'text',
                    'is_required' => true,
                ],
                [
                    'label' => 'Supervisor Email',
                    'field_key' => 'supervisor_email',
                    'field_type' => 'email',
                    'is_required' => true,
                ],
                [
                    'label' => 'Internship Start Date',
                    'field_key' => 'start_date',
                    'field_type' => 'date',
                    'is_required' => true,
                ],
                [
                    'label' => 'Duration (weeks)',
                    'field_key' => 'duration_weeks',
                    'field_type' => 'number',
                    'is_required' => true,
                    'placeholder' => '12',
                    'validation_rules' => 'integer|min:1|max:52',
                ],
                [
                    'label' => 'Offer Letter',
                    'field_key' => 'offer_letter',
                    'field_type' => 'file',
                    'is_required' => true,
                    'help_text' => 'Upload the offer letter from the company.',
                ],
            ],

            // --------------------------------------------------------
            // Club Join Application
            // --------------------------------------------------------
            'club-join-application' => [
                [
                    'label' => 'Club Name',
                    'field_key' => 'club_name',
                    'field_type' => 'select',
                    'options' => ['DIU Computer Club', 'DIU Debate Club', 'DIU Photography Club', 'DIU Cultural Club', 'DIU Sports Club', 'DIU Science Club', 'DIU Business Club', 'Other'],
                    'is_required' => true,
                ],
                [
                    'label' => 'Why Do You Want to Join?',
                    'field_key' => 'why_join',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Explain your motivation and what you hope to contribute...',
                ],
                [
                    'label' => 'Relevant Skills / Experience',
                    'field_key' => 'relevant_skills',
                    'field_type' => 'textarea',
                    'is_required' => false,
                    'placeholder' => 'Any skills, experience, or previous club membership...',
                ],
                [
                    'label' => 'Availability',
                    'field_key' => 'availability',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'e.g. Weekends, Tuesday/Thursday evenings',
                ],
            ],

            // --------------------------------------------------------
            // New Club Formation
            // --------------------------------------------------------
            'new-club-formation' => [
                [
                    'label' => 'Proposed Club Name',
                    'field_key' => 'club_name',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'Full proposed name of the club',
                ],
                [
                    'label' => 'Club Type / Category',
                    'field_key' => 'club_type',
                    'field_type' => 'select',
                    'options' => ['Academic', 'Cultural', 'Sports', 'Technology', 'Social / Community', 'Entrepreneurship', 'Other'],
                    'is_required' => true,
                ],
                [
                    'label' => 'Club Mission Statement',
                    'field_key' => 'mission',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Describe the purpose and goals of this club...',
                ],
                [
                    'label' => 'Proposed Activities',
                    'field_key' => 'proposed_activities',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'List planned events, workshops, or activities...',
                ],
                [
                    'label' => 'Founding Members (Min. 10 students)',
                    'field_key' => 'founding_members',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'List student names and IDs (one per line)',
                    'help_text' => 'At least 10 founding members are required.',
                ],
                [
                    'label' => 'Faculty Advisor',
                    'field_key' => 'faculty_advisor',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'Name of faculty member who has agreed to be advisor',
                ],
                [
                    'label' => 'Club Constitution / Proposal Document',
                    'field_key' => 'constitution_doc',
                    'field_type' => 'file',
                    'is_required' => false,
                    'help_text' => 'Upload a draft constitution or detailed proposal (PDF).',
                ],
            ],

            // --------------------------------------------------------
            // Event Permission Request
            // --------------------------------------------------------
            'event-permission' => [
                [
                    'label' => 'Event Name',
                    'field_key' => 'event_name',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'e.g. Annual Tech Fest 2026',
                ],
                [
                    'label' => 'Event Type',
                    'field_key' => 'event_type',
                    'field_type' => 'select',
                    'options' => ['Workshop', 'Seminar', 'Cultural Program', 'Sports Event', 'Competition', 'Networking Event', 'Other'],
                    'is_required' => true,
                ],
                [
                    'label' => 'Event Date',
                    'field_key' => 'event_date',
                    'field_type' => 'date',
                    'is_required' => true,
                ],
                [
                    'label' => 'Proposed Venue',
                    'field_key' => 'venue',
                    'field_type' => 'text',
                    'is_required' => true,
                    'placeholder' => 'e.g. DIU Auditorium, Block A Rooftop',
                ],
                [
                    'label' => 'Expected Attendees',
                    'field_key' => 'expected_attendees',
                    'field_type' => 'number',
                    'is_required' => true,
                    'placeholder' => '150',
                    'validation_rules' => 'integer|min:1',
                ],
                [
                    'label' => 'Event Description & Schedule',
                    'field_key' => 'description',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Describe the event and provide a rough timeline...',
                ],
                [
                    'label' => 'Budget (BDT)',
                    'field_key' => 'budget',
                    'field_type' => 'number',
                    'is_required' => false,
                    'placeholder' => '25000',
                ],
                [
                    'label' => 'Event Proposal Document',
                    'field_key' => 'proposal_doc',
                    'field_type' => 'file',
                    'is_required' => false,
                ],
            ],

            // --------------------------------------------------------
            // Student Portfolio / Profile Request
            // --------------------------------------------------------
            'student-portfolio' => [
                [
                    'label' => 'LinkedIn Profile URL',
                    'field_key' => 'linkedin_url',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'https://linkedin.com/in/yourname',
                    'validation_rules' => 'nullable|url',
                ],
                [
                    'label' => 'GitHub / Portfolio URL',
                    'field_key' => 'github_url',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'https://github.com/yourname',
                    'validation_rules' => 'nullable|url',
                ],
                [
                    'label' => 'Key Achievements',
                    'field_key' => 'achievements',
                    'field_type' => 'textarea',
                    'is_required' => false,
                    'placeholder' => 'Awards, competitions, certifications, publications...',
                ],
                [
                    'label' => 'Notable Projects',
                    'field_key' => 'project_highlights',
                    'field_type' => 'textarea',
                    'is_required' => false,
                    'placeholder' => 'Describe your top 2-3 projects with impact and technologies used...',
                ],
                [
                    'label' => 'Skills & Technologies',
                    'field_key' => 'skills',
                    'field_type' => 'textarea',
                    'is_required' => false,
                    'placeholder' => 'e.g. Python, Laravel, React, Machine Learning, Data Analysis...',
                ],
                [
                    'label' => 'CV / Resume',
                    'field_key' => 'cv_upload',
                    'field_type' => 'file',
                    'is_required' => false,
                    'help_text' => 'Upload an up-to-date CV (PDF preferred).',
                ],
            ],

            // --------------------------------------------------------
            // Fee Waiver Request
            // --------------------------------------------------------
            'fee-waiver-request' => [
                [
                    'label' => 'Reason for Waiver',
                    'field_key' => 'reason',
                    'field_type' => 'select',
                    'options' => ['Financial Hardship', 'Medical Emergency', 'Family Emergency', 'Academic Merit', 'Scholarship Gap', 'Other'],
                    'is_required' => true,
                ],
                [
                    'label' => 'Amount Requested (BDT)',
                    'field_key' => 'amount_requested',
                    'field_type' => 'number',
                    'is_required' => true,
                    'placeholder' => '15000',
                    'validation_rules' => 'numeric|min:100',
                ],
                [
                    'label' => 'Supporting Details',
                    'field_key' => 'details',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Explain your situation in detail...',
                ],
                [
                    'label' => 'Current Financial Situation',
                    'field_key' => 'financial_situation',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Describe your household income and financial constraints...',
                ],
                [
                    'label' => 'Supporting Documents',
                    'field_key' => 'supporting_docs',
                    'field_type' => 'file',
                    'is_required' => false,
                    'help_text' => 'Upload income certificate, medical certificate, or other relevant documents.',
                ],
            ],

            // --------------------------------------------------------
            // IT Support Ticket
            // --------------------------------------------------------
            'it-support-ticket' => [
                [
                    'label' => 'Issue Type',
                    'field_key' => 'issue_type',
                    'field_type' => 'select',
                    'options' => ['Network / WiFi', 'Student Portal Access', 'Email Account', 'Lab Computer', 'Software Installation', 'Printer / Scanner', 'ERP / Academic System', 'Other'],
                    'is_required' => true,
                ],
                [
                    'label' => 'Device / System Affected',
                    'field_key' => 'device_type',
                    'field_type' => 'text',
                    'is_required' => false,
                    'placeholder' => 'e.g. Personal laptop, Lab PC in Room 302, DIU Email',
                ],
                [
                    'label' => 'Urgency',
                    'field_key' => 'urgency',
                    'field_type' => 'select',
                    'options' => ['Low — I can wait a few days', 'Medium — needed within 24 hours', 'High — blocking my work right now'],
                    'is_required' => true,
                ],
                [
                    'label' => 'Problem Description',
                    'field_key' => 'description',
                    'field_type' => 'textarea',
                    'is_required' => true,
                    'placeholder' => 'Describe the issue, error messages, and steps to reproduce it...',
                ],
                [
                    'label' => 'Screenshot / Evidence',
                    'field_key' => 'screenshot',
                    'field_type' => 'file',
                    'is_required' => false,
                    'help_text' => 'A screenshot of the error helps us resolve it faster.',
                ],
            ],

        ];
    }
}
