-- ============================================================
--  DIU Student Services — Form Tracking System
--  Database Schema v1.0
--  Engine: MySQL 8.0+ | Charset: utf8mb4
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- 1. USERS
-- Covers students, department admins, super-admins
-- ============================================================
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id      VARCHAR(20)  UNIQUE NULL,          -- e.g. "221-15-5812" (NULL for staff)
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(191) UNIQUE NOT NULL,
    phone           VARCHAR(20)  NULL,                 -- BD mobile: +8801XXXXXXXXX
    password        VARCHAR(255) NOT NULL,
    role            ENUM('student','admin','super_admin') NOT NULL DEFAULT 'student',
    department_id   BIGINT UNSIGNED NULL,              -- for admin: which dept they manage
    program         VARCHAR(100) NULL,                 -- e.g. "B.Sc. in CSE"
    batch           VARCHAR(20)  NULL,                 -- e.g. "55"
    semester        VARCHAR(20)  NULL,                 -- e.g. "7th"
    profile_photo   VARCHAR(255) NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    email_verified_at TIMESTAMP  NULL,
    remember_token  VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_role          (role),
    INDEX idx_department    (department_id),
    INDEX idx_student_id    (student_id)
) ENGINE=InnoDB;

-- ============================================================
-- 2. DEPARTMENTS
-- Routing destinations — every form routes to one department
-- ============================================================
CREATE TABLE departments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,             -- e.g. "Registrar's Office"
    slug            VARCHAR(100) UNIQUE NOT NULL,      -- e.g. "registrar"
    description     TEXT         NULL,
    email           VARCHAR(191) NULL,                 -- dept contact email
    phone           VARCHAR(20)  NULL,
    head_user_id    BIGINT UNSIGNED NULL,              -- dept head (for escalation)
    sla_hours       SMALLINT UNSIGNED NOT NULL DEFAULT 48, -- default SLA in hours
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 3. FORM TYPES
-- Master list of all form types the system supports
-- ============================================================
CREATE TABLE form_types (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,             -- e.g. "Bonafide Certificate"
    slug            VARCHAR(100) UNIQUE NOT NULL,      -- e.g. "bonafide-certificate"
    category        ENUM(
                        'academic_certification',
                        'complaint',
                        'career_counseling',
                        'club_cocurricular',
                        'profile_portfolio',
                        'finance',
                        'it_support',
                        'other'
                    ) NOT NULL,
    department_id   BIGINT UNSIGNED NOT NULL,          -- default routing destination
    description     TEXT         NULL,
    instructions    TEXT         NULL,                 -- shown to student before submission
    requires_documents TINYINT(1) NOT NULL DEFAULT 0,
    allow_anonymous    TINYINT(1) NOT NULL DEFAULT 0,  -- only for complaints
    auto_generate_doc  TINYINT(1) NOT NULL DEFAULT 0,  -- auto PDF on approval?
    doc_template_path  VARCHAR(255) NULL,              -- path to PDF template
    sla_hours       SMALLINT UNSIGNED NULL,            -- override dept default if set
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_category      (category),
    INDEX idx_department    (department_id),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 4. FORM FIELDS
-- Dynamic field definitions per form type
-- Allows adding new fields without schema changes
-- ============================================================
CREATE TABLE form_fields (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_type_id    BIGINT UNSIGNED NOT NULL,
    label           VARCHAR(191) NOT NULL,             -- "Reason for Request"
    field_key       VARCHAR(100) NOT NULL,             -- "reason_for_request"
    field_type      ENUM(
                        'text', 'textarea', 'select',
                        'radio', 'checkbox', 'date',
                        'file', 'phone', 'email', 'number'
                    ) NOT NULL,
    options         JSON         NULL,                 -- for select/radio/checkbox options
    is_required     TINYINT(1)   NOT NULL DEFAULT 0,
    placeholder     VARCHAR(255) NULL,
    help_text       VARCHAR(500) NULL,
    validation_rules VARCHAR(500) NULL,               -- e.g. "max:500|regex:..."
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,

    UNIQUE KEY uq_form_field (form_type_id, field_key),
    FOREIGN KEY (form_type_id) REFERENCES form_types(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. WORKFLOWS
-- One workflow per form type (or shared across types)
-- Defines the approval chain model
-- ============================================================
CREATE TABLE workflows (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    form_type_id    BIGINT UNSIGNED UNIQUE NULL,       -- NULL = generic/shared
    type            ENUM('single','sequential','parallel') NOT NULL DEFAULT 'single',
    description     TEXT         NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (form_type_id) REFERENCES form_types(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 6. WORKFLOW STEPS
-- Individual steps within a workflow
-- ============================================================
CREATE TABLE workflow_steps (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id     BIGINT UNSIGNED NOT NULL,
    step_number     TINYINT UNSIGNED NOT NULL,         -- 1, 2, 3... (order for sequential)
    step_name       VARCHAR(150) NOT NULL,             -- "HOD Approval"
    department_id   BIGINT UNSIGNED NULL,              -- which dept handles this step
    assigned_role   ENUM('admin','super_admin') NOT NULL DEFAULT 'admin',
    action_required ENUM('approve','review','sign_off') NOT NULL DEFAULT 'approve',
    sla_hours       SMALLINT UNSIGNED NOT NULL DEFAULT 48,
    is_optional     TINYINT(1)   NOT NULL DEFAULT 0,

    UNIQUE KEY uq_workflow_step (workflow_id, step_number),
    FOREIGN KEY (workflow_id)   REFERENCES workflows(id)   ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 7. SUBMISSIONS  ← CORE TABLE
-- Every student form submission lives here
-- ============================================================
CREATE TABLE submissions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no    VARCHAR(30)  UNIQUE NOT NULL,      -- e.g. "DIU-2026-00421"
    form_type_id    BIGINT UNSIGNED NOT NULL,
    student_id      BIGINT UNSIGNED NULL,              -- NULL if anonymous
    is_anonymous    TINYINT(1)   NOT NULL DEFAULT 0,
    department_id   BIGINT UNSIGNED NOT NULL,          -- routed destination

    -- Status state machine
    status          ENUM(
                        'draft',
                        'submitted',
                        'routed',
                        'in_review',
                        'action_required',
                        'escalated',
                        'approved',
                        'rejected',
                        'returned',
                        'completed',
                        'cancelled'
                    ) NOT NULL DEFAULT 'draft',

    -- Flexible form data storage
    form_data       JSON         NOT NULL,             -- key-value pairs for all fields

    -- Admin assignment
    assigned_to     BIGINT UNSIGNED NULL,              -- which admin is handling it
    current_step    TINYINT UNSIGNED NOT NULL DEFAULT 1, -- for multi-step workflows

    -- Timing
    submitted_at    TIMESTAMP    NULL,
    sla_deadline    TIMESTAMP    NULL,                 -- computed on submission
    escalated_at    TIMESTAMP    NULL,
    resolved_at     TIMESTAMP    NULL,

    -- Admin notes (internal, not shown to student)
    internal_notes  TEXT         NULL,

    -- Output document (auto-generated or uploaded by admin)
    output_document VARCHAR(255) NULL,

    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status        (status),
    INDEX idx_student       (student_id),
    INDEX idx_department    (department_id),
    INDEX idx_form_type     (form_type_id),
    INDEX idx_assigned      (assigned_to),
    INDEX idx_submitted_at  (submitted_at),
    INDEX idx_sla_deadline  (sla_deadline),
    INDEX idx_reference     (reference_no),

    FOREIGN KEY (form_type_id)  REFERENCES form_types(id)  ON DELETE RESTRICT,
    FOREIGN KEY (student_id)    REFERENCES users(id)        ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id)  ON DELETE RESTRICT,
    FOREIGN KEY (assigned_to)   REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 8. SUBMISSION STATUS HISTORY  ← AUDIT LOG
-- Immutable record of every status change
-- ============================================================
CREATE TABLE submission_status_history (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id   BIGINT UNSIGNED NOT NULL,
    changed_by      BIGINT UNSIGNED NULL,              -- NULL = system-triggered
    from_status     VARCHAR(50)  NULL,
    to_status       VARCHAR(50)  NOT NULL,
    comment         TEXT         NULL,                 -- required on reject/return
    is_visible_to_student TINYINT(1) NOT NULL DEFAULT 1,
    step_number     TINYINT UNSIGNED NULL,
    changed_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_submission    (submission_id),
    INDEX idx_changed_by    (changed_by),
    INDEX idx_changed_at    (changed_at),

    FOREIGN KEY (submission_id) REFERENCES submissions(id)  ON DELETE CASCADE,
    FOREIGN KEY (changed_by)    REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 9. SUBMISSION DOCUMENTS
-- File uploads attached to a submission (by student or admin)
-- ============================================================
CREATE TABLE submission_documents (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id   BIGINT UNSIGNED NOT NULL,
    uploaded_by     BIGINT UNSIGNED NULL,
    file_name       VARCHAR(255) NOT NULL,             -- original filename
    file_path       VARCHAR(500) NOT NULL,             -- storage path
    file_size       INT UNSIGNED NOT NULL,             -- bytes
    mime_type       VARCHAR(100) NOT NULL,
    document_type   ENUM('student_upload','admin_upload','generated_output') NOT NULL DEFAULT 'student_upload',
    description     VARCHAR(255) NULL,
    is_public       TINYINT(1)   NOT NULL DEFAULT 1,  -- visible to student?
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_submission    (submission_id),
    FOREIGN KEY (submission_id) REFERENCES submissions(id)  ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by)   REFERENCES users(id)        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 10. SUBMISSION COMMENTS
-- Threaded comments between student and admin
-- ============================================================
CREATE TABLE submission_comments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id   BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NULL,              -- NULL = system message
    body            TEXT         NOT NULL,
    is_internal     TINYINT(1)   NOT NULL DEFAULT 0,  -- admin-only internal note
    is_system       TINYINT(1)   NOT NULL DEFAULT 0,  -- auto-generated by system
    parent_id       BIGINT UNSIGNED NULL,              -- for threading
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_submission    (submission_id),
    FOREIGN KEY (submission_id) REFERENCES submissions(id)  ON DELETE CASCADE,
    FOREIGN KEY (user_id)       REFERENCES users(id)        ON DELETE SET NULL,
    FOREIGN KEY (parent_id)     REFERENCES submission_comments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 11. APPROVAL RECORDS
-- Tracks each approver action in multi-step workflows
-- ============================================================
CREATE TABLE approval_records (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id   BIGINT UNSIGNED NOT NULL,
    workflow_step_id BIGINT UNSIGNED NOT NULL,
    approver_id     BIGINT UNSIGNED NULL,
    action          ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending',
    comment         TEXT         NULL,
    acted_at        TIMESTAMP    NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_approval (submission_id, workflow_step_id),
    INDEX idx_submission    (submission_id),
    INDEX idx_approver      (approver_id),

    FOREIGN KEY (submission_id)    REFERENCES submissions(id)      ON DELETE CASCADE,
    FOREIGN KEY (workflow_step_id) REFERENCES workflow_steps(id)   ON DELETE CASCADE,
    FOREIGN KEY (approver_id)      REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 12. NOTIFICATIONS
-- Log of every notification sent (in-app + SMS)
-- ============================================================
CREATE TABLE notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,
    submission_id   BIGINT UNSIGNED NULL,
    channel         ENUM('in_app','sms','email') NOT NULL,
    type            VARCHAR(100) NOT NULL,             -- e.g. "submission.approved"
    title           VARCHAR(255) NOT NULL,
    body            TEXT         NOT NULL,
    phone_number    VARCHAR(20)  NULL,                 -- for SMS
    is_read         TINYINT(1)   NOT NULL DEFAULT 0,
    sent_at         TIMESTAMP    NULL,
    read_at         TIMESTAMP    NULL,
    failed_at       TIMESTAMP    NULL,
    failure_reason  VARCHAR(500) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user          (user_id),
    INDEX idx_submission    (submission_id),
    INDEX idx_channel       (channel),
    INDEX idx_is_read       (is_read),
    INDEX idx_sent_at       (sent_at),

    FOREIGN KEY (user_id)       REFERENCES users(id)        ON DELETE SET NULL,
    FOREIGN KEY (submission_id) REFERENCES submissions(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 13. SLA ESCALATION RULES
-- Configurable escalation chains per dept
-- ============================================================
CREATE TABLE sla_escalation_rules (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id   BIGINT UNSIGNED NOT NULL,
    form_type_id    BIGINT UNSIGNED NULL,              -- NULL = applies to all types in dept
    escalate_after_hours SMALLINT UNSIGNED NOT NULL,
    escalate_to_user_id  BIGINT UNSIGNED NULL,         -- specific person, or NULL = dept head
    notify_student  TINYINT(1)   NOT NULL DEFAULT 1,
    escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 1, -- 1st, 2nd escalation etc.
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (department_id)  REFERENCES departments(id)  ON DELETE CASCADE,
    FOREIGN KEY (form_type_id)   REFERENCES form_types(id)   ON DELETE CASCADE,
    FOREIGN KEY (escalate_to_user_id) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 14. REFERENCE NUMBER SEQUENCE
-- Controls the auto-increment reference number per year
-- ============================================================
CREATE TABLE reference_sequences (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year            SMALLINT UNSIGNED NOT NULL,
    last_sequence   INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_year (year)
) ENGINE=InnoDB;

-- ============================================================
-- 15. AUDIT LOG
-- System-wide audit trail (beyond just status changes)
-- ============================================================
CREATE TABLE audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,
    action          VARCHAR(100) NOT NULL,             -- e.g. "submission.created"
    auditable_type  VARCHAR(100) NOT NULL,             -- model name
    auditable_id    BIGINT UNSIGNED NOT NULL,
    old_values      JSON         NULL,
    new_values      JSON         NULL,
    ip_address      VARCHAR(45)  NULL,
    user_agent      VARCHAR(500) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_user          (user_id),
    INDEX idx_auditable     (auditable_type, auditable_id),
    INDEX idx_created_at    (created_at),

    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA — Departments
-- ============================================================
INSERT INTO departments (name, slug, email, sla_hours) VALUES
('Registrar\'s Office',       'registrar',       'registrar@diu.edu.bd',       48),
('Exam Controller',           'exam-controller', 'exam@diu.edu.bd',            72),
('Student Affairs',           'student-affairs', 'studentaffairs@diu.edu.bd',  24),
('Career Services',           'career-services', 'career@diu.edu.bd',          48),
('Club & Co-curricular',      'clubs',           'clubs@diu.edu.bd',           72),
('Finance & Accounts',        'finance',         'finance@diu.edu.bd',         72),
('IT Department',             'it',              'it@diu.edu.bd',              24),
('Department Office (CSE)',   'dept-cse',        'cse@diu.edu.bd',             48),
('Department Office (EEE)',   'dept-eee',        'eee@diu.edu.bd',             48),
('Department Office (BBA)',   'dept-bba',        'bba@diu.edu.bd',             48);

-- ============================================================
-- SEED DATA — Form Types
-- ============================================================
INSERT INTO form_types (name, slug, category, department_id, requires_documents, allow_anonymous, auto_generate_doc, sla_hours, sort_order) VALUES
-- Academic & Certification (dept 1 = Registrar)
('Bonafide Certificate',        'bonafide-certificate',     'academic_certification', 1, 0, 0, 1, 24,  1),
('Character Certificate',       'character-certificate',    'academic_certification', 1, 0, 0, 0, 48,  2),
('Migration Certificate',       'migration-certificate',    'academic_certification', 1, 1, 0, 0, 120, 3),
('Eligibility Certificate',     'eligibility-certificate',  'academic_certification', 2, 1, 0, 0, 72,  4),
('Completion Letter',           'completion-letter',        'academic_certification', 2, 0, 0, 1, 72,  5),
-- Complaints (dept 3 = Student Affairs)
('Student Complaint',           'student-complaint',        'complaint',              3, 1, 1, 0, 24,  10),
('Misconduct Report',           'misconduct-report',        'complaint',              3, 1, 1, 0, 12,  11),
-- Career Counseling (dept 4)
('Career Counseling Request',   'career-counseling',        'career_counseling',      4, 0, 0, 0, 48,  20),
('Internship Letter Request',   'internship-letter',        'career_counseling',      4, 0, 0, 1, 48,  21),
-- Club & Co-curricular (dept 5)
('Club Membership Application', 'club-membership',          'club_cocurricular',      5, 0, 0, 0, 72,  30),
('New Club Formation Request',  'new-club-formation',       'club_cocurricular',      5, 1, 0, 0, 168, 31),
('Event/Committee Approval',    'event-approval',           'club_cocurricular',      5, 1, 0, 0, 96,  32),
-- Finance (dept 6)
('Fee Waiver Request',          'fee-waiver',               'finance',                6, 1, 0, 0, 96,  40),
('Scholarship Application',     'scholarship-application',  'finance',                6, 1, 0, 0, 120, 41),
-- IT (dept 7)
('IT Support Ticket',           'it-support',               'it_support',             7, 0, 0, 0, 24,  50);

-- ============================================================
-- SEED DATA — Workflows
-- ============================================================
-- Single-step workflows (most forms)
INSERT INTO workflows (name, form_type_id, type) VALUES
('Standard Single Approval',    NULL, 'single'),      -- generic, id=1
('Bonafide Auto-Generate',      1,    'single'),
('Migration Multi-Step',        3,    'sequential'),
('Fee Waiver Multi-Step',       13,   'sequential'),
('New Club Formation Chain',    11,   'sequential');

-- Workflow steps for Migration (3 steps)
INSERT INTO workflow_steps (workflow_id, step_number, step_name, department_id, action_required, sla_hours) VALUES
(3, 1, 'Registrar Review',      1, 'approve',   48),
(3, 2, 'Exam Controller Sign',  2, 'sign_off',  48),
(3, 3, 'Dean Final Approval',   3, 'approve',   24);

-- Workflow steps for Fee Waiver (2 steps)
INSERT INTO workflow_steps (workflow_id, step_number, step_name, department_id, action_required, sla_hours) VALUES
(4, 1, 'Finance Officer Review', 6, 'review',  48),
(4, 2, 'Dean Approval',         3, 'approve',  48);

-- Workflow steps for New Club (3 steps)
INSERT INTO workflow_steps (workflow_id, step_number, step_name, department_id, action_required, sla_hours) VALUES
(5, 1, 'Club Affairs Review',   5, 'approve',  72),
(5, 2, 'Student Affairs OK',    3, 'approve',  48),
(5, 3, 'Dean Final Sign-off',   3, 'sign_off', 48);
