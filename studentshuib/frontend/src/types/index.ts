// ============================================================
// StudentsHub — shared TypeScript types
// ============================================================

export type UserRole = 'student' | 'admin' | 'dept_head' | 'super_admin' | 'management';

export interface User {
  id: number;
  student_id: string | null;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  program: string | null;
  batch: string | null;
  semester: string | null;
  profile_photo: string | null;
  department: { id: number; name: string } | null;
  is_active: boolean;
  last_login_at: string | null;
}

export type SubmissionStatus =
  | 'draft' | 'submitted' | 'routed' | 'in_review'
  | 'action_required' | 'escalated' | 'approved'
  | 'rejected' | 'returned' | 'completed' | 'cancelled';

export type FormCategory =
  | 'academic_certification' | 'complaint' | 'career_counseling'
  | 'club_cocurricular' | 'profile_portfolio' | 'finance' | 'it_support' | 'other';

export interface FormType {
  id: number;
  name: string;
  slug: string;
  category: FormCategory;
  department_id: number | null;
  department: { id: number; name: string } | null;
  description: string | null;
  instructions: string | null;
  requires_documents: boolean;
  allow_anonymous: boolean;  // DB column name
  is_active: boolean;
  sla_hours: number | null;
  fields?: FormField[];
}

export type FieldType = 'text' | 'textarea' | 'select' | 'radio' | 'checkbox' | 'date' | 'file' | 'phone' | 'email' | 'number';

export interface FormField {
  id: number;
  form_type_id?: number;
  label: string;
  field_key: string;
  field_type: FieldType;
  options: string[] | null;
  is_required: boolean;
  is_active: boolean;
  placeholder: string | null;
  help_text: string | null;
  validation_rules: string | null;
  sort_order: number;
}

export interface Submission {
  id: number;
  reference_no: string;
  status: SubmissionStatus;
  form_type: { id: number; name: string; slug: string; category: FormCategory } | null;
  department: { id: number; name: string } | null;
  is_anonymous: boolean;
  submitted_at: string | null;
  sla_deadline: string | null;
  sla_breached: boolean;
  created_at: string;
  form_data?: Record<string, unknown>;
  status_history?: StatusHistoryEntry[];
  documents?: SubmissionDocument[];
  comments?: SubmissionComment[];
  approval_records?: ApprovalRecord[];
  student?: {
    id: number; name: string; email: string;
    student_id: string | null; phone: string | null;
    department: { id: number; name: string } | null;
  } | null;
  assigned_to?: User | null;
}

export interface StatusHistoryEntry {
  id: number;
  from_status: SubmissionStatus | null;
  new_status: SubmissionStatus;   // used by backend
  to_status: SubmissionStatus;    // alias
  comment: string | null;
  is_visible_to_student: boolean;
  created_at: string;
  changed_at: string;             // alias
  changed_by?: { id: number; name: string; role: UserRole } | null;
}

export interface SubmissionDocument {
  id: number;
  file_name: string;
  original_name: string;
  url: string;
  size_human: string;
  mime_type: string;
  source: 'student' | 'admin';
  document_type: 'student_upload' | 'admin_upload' | 'generated_output';
  description: string | null;
  is_public: boolean;
  created_at: string;
  uploaded_by?: { id: number; name: string; role: UserRole } | null;
}

export interface SubmissionComment {
  id: number;
  body: string;
  is_internal: boolean;
  is_system: boolean;
  created_at: string;
  user?: { id: number; name: string; role: UserRole } | null;
  replies?: SubmissionComment[];
}

// Legacy alias
export type Comment = SubmissionComment;

export interface ApprovalRecord {
  id: number;
  step_order: number | null;  // from appended accessor via WorkflowStep.step_number
  action: 'pending' | 'approved' | 'rejected' | 'skipped';
  comment: string | null;
  created_at: string;
  approver?: { id: number; name: string } | null;
}

export interface Notification {
  id: number;
  type: string;
  title: string;
  body: string;
  is_read: boolean;
  created_at: string;
  submission_id: number | null;
}

export interface Department {
  id: number;
  name: string;
  slug: string;
  code: string | null;
  email: string | null;
  phone: string | null;
  sla_hours: number | null;
  is_active: boolean;
  submissions_count?: number;
}

export interface AuditLog {
  id: number;
  action: string;
  entity_type: string | null;
  entity_id: number | null;
  description: string | null;
  changes: Record<string, unknown> | null;
  created_at: string;
  user?: { id: number; name: string } | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface DashboardStats {
  // Period meta
  period: 'week' | 'month' | 'semester';
  period_label: string;
  period_start: string;
  period_end:   string;

  // 6 stat cards
  total_submitted:           number;
  total_submitted_delta_pct: number | null;
  completed:                 number;
  completed_delta_pct:       number | null;
  pending_review:            number;
  overdue:                   number;
  escalated:                 number;
  avg_resolution_days:       number | null;

  // Leadership KPIs
  sla_compliance_pct:     number | null;
  anonymous_in_period:    number;
  new_students_in_period: number;

  // Status breakdown
  status_counts:  Partial<Record<SubmissionStatus, number>>;
  total_active:   number;
  total_snapshot: number;

  // Submission volume (line chart)
  submission_volume: { label: string; count: number; date: string }[];
  volume_peak:       number;

  // Department performance (null for dept-scoped admins)
  departments: {
    id: number; name: string;
    period_total: number; open_count: number; breached_count: number;
  }[] | null;

  // Recent activity
  recent_activity: {
    id: number;
    reference_no: string | null;
    form_type:    string | null;
    department:   string | null;
    from_status:  SubmissionStatus | null;
    to_status:    SubmissionStatus;
    comment:      string | null;
    changed_at:   string;
    changed_by:   string | null;
  }[];

  // Needs attention — escalated + most overdue, deep-link list
  needs_attention: {
    reference_no:  string;
    form_type:     string | null;
    department:    string | null;
    status:        SubmissionStatus;
    assigned_to:   string | null;
    sla_deadline:  string | null;
    hours_overdue: number | null;
  }[];

  // Legacy fields (still returned for backwards compat)
  sla_breached:   number;
  unassigned:     number;
  total_open:     number;
  recent_by_day:  { date: string; count: number }[];
  top_form_types: { name: string; count: number }[];
}

export interface SystemSettings {
  semester: {
    label:      string;
    start_date: string;
    end_date:   string;
  };
}

// Category display labels
export const CATEGORY_LABELS: Record<FormCategory, string> = {
  academic_certification: 'Academic & Certification',
  complaint:              'Complaints',
  career_counseling:      'Career Counseling',
  club_cocurricular:      'Club & Co-curricular',
  profile_portfolio:      'Profile & Portfolio',
  finance:                'Finance',
  it_support:             'IT Support',
  other:                  'Other',
};

// Status display config
export const STATUS_CONFIG: Record<SubmissionStatus, { label: string; color: string }> = {
  draft:           { label: 'Draft',           color: 'bg-gray-100 text-gray-600' },
  submitted:       { label: 'Submitted',       color: 'bg-blue-100 text-blue-700' },
  routed:          { label: 'Routed',          color: 'bg-indigo-100 text-indigo-700' },
  in_review:       { label: 'In Review',       color: 'bg-yellow-100 text-yellow-700' },
  action_required: { label: 'Action Required', color: 'bg-orange-100 text-orange-700' },
  escalated:       { label: 'Escalated',       color: 'bg-red-100 text-red-700' },
  approved:        { label: 'Approved',        color: 'bg-green-100 text-green-700' },
  rejected:        { label: 'Rejected',        color: 'bg-red-100 text-red-700' },
  returned:        { label: 'Returned',        color: 'bg-purple-100 text-purple-700' },
  completed:       { label: 'Completed',       color: 'bg-green-100 text-green-800' },
  cancelled:       { label: 'Cancelled',       color: 'bg-gray-100 text-gray-500' },
};
