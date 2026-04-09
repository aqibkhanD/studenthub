<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway — SSL Wireless (Bangladesh)
    |--------------------------------------------------------------------------
    | Sign up at https://www.sslwireless.com/
    | Your Sender ID must be approved by BTRC before use.
    */
    'sms' => [
        'endpoint'  => env('SMS_ENDPOINT',  'https://sms.sslwireless.com/pushapi/dynamic/server.php'),
        'api_token' => env('SMS_API_TOKEN', ''),
        'sid'       => env('SMS_SID',       'DIU-SVC'),
        'enabled'   => env('SMS_ENABLED',   true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Spam Prevention
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_emails_per_hour'    => env('NOTIF_MAX_EMAILS_HOUR', 10),
        'max_sms_per_day'        => env('NOTIF_MAX_SMS_DAY',     5),
        'duplicate_window_min'   => env('NOTIF_DUPE_WINDOW',     30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quiet Hours (Bangladesh Standard Time, UTC+6)
    |--------------------------------------------------------------------------
    | SMS and non-urgent emails are suppressed between quiet_start and quiet_end.
    | Escalations and SLA breaches bypass quiet hours.
    */
    'quiet_hours' => [
        'enabled'     => env('NOTIF_QUIET_ENABLED', true),
        'start_hour'  => 22,
        'end_hour'    => 7,
        'end_minute'  => 30,
        'bypass_events' => ['sla_breach', 'escalation', 'action_required'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Digest Schedule
    |--------------------------------------------------------------------------
    */
    'digest' => [
        'hourly_at_minute' => env('DIGEST_HOURLY_MINUTE', 30),   // e.g. 30 = :30 past each hour
        'daily_at'         => env('DIGEST_DAILY_AT',      '08:00'),
        'min_events'       => env('DIGEST_MIN_EVENTS',    2),     // suppress digest if fewer than N events
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Delivery Modes
    | Fallback values used when no notification_preferences row exists.
    | Keys: "{role}_{event_type}_{channel}"
    |--------------------------------------------------------------------------
    | Roles: student | admin | super_admin
    | Channels: email | sms | inapp
    | Delivery: immediate | digest_hourly | digest_daily | never
    */
    'defaults' => [
        // Students
        'student_submission_confirmed_email'  => 'immediate',
        'student_submission_confirmed_sms'    => 'immediate',
        'student_submission_confirmed_inapp'  => 'immediate',
        'student_submission_in_review_email'  => 'never',
        'student_submission_in_review_sms'    => 'never',
        'student_submission_in_review_inapp'  => 'immediate',
        'student_action_required_email'       => 'immediate',
        'student_action_required_sms'         => 'immediate',
        'student_action_required_inapp'       => 'immediate',
        'student_submission_returned_email'   => 'immediate',
        'student_submission_returned_sms'     => 'immediate',
        'student_submission_returned_inapp'   => 'immediate',
        'student_submission_approved_email'   => 'immediate',
        'student_submission_approved_sms'     => 'immediate',
        'student_submission_approved_inapp'   => 'immediate',
        'student_submission_rejected_email'   => 'immediate',
        'student_submission_rejected_sms'     => 'immediate',
        'student_submission_rejected_inapp'   => 'immediate',
        'student_certificate_ready_email'     => 'immediate',
        'student_certificate_ready_sms'       => 'immediate',
        'student_certificate_ready_inapp'     => 'immediate',
        'student_admin_comment_email'         => 'immediate',
        'student_admin_comment_sms'           => 'never',
        'student_admin_comment_inapp'         => 'immediate',

        // Admins (regular)
        'admin_new_submission_email'          => 'digest_hourly',
        'admin_new_submission_sms'            => 'never',
        'admin_new_submission_inapp'          => 'immediate',
        'admin_submission_resubmit_email'     => 'digest_hourly',
        'admin_submission_resubmit_inapp'     => 'immediate',
        'admin_sla_warning_email'             => 'immediate',
        'admin_sla_warning_sms'               => 'immediate',
        'admin_sla_warning_inapp'             => 'immediate',
        'admin_sla_breach_email'              => 'immediate',
        'admin_sla_breach_sms'                => 'immediate',
        'admin_sla_breach_inapp'              => 'immediate',
        'admin_escalation_email'              => 'immediate',
        'admin_escalation_sms'                => 'immediate',
        'admin_escalation_inapp'              => 'immediate',
        'admin_role_change_email'             => 'immediate',
        'admin_role_change_inapp'             => 'immediate',
        'admin_new_admin_email'               => 'immediate',
        'admin_new_admin_inapp'               => 'immediate',
        'admin_setting_change_email'          => 'digest_daily',
        'admin_setting_change_inapp'          => 'immediate',

        // Super admins — individual emails off by default; receives daily digests
        'super_admin_new_submission_email'    => 'digest_daily',
        'super_admin_new_submission_inapp'    => 'immediate',
        'super_admin_sla_breach_email'        => 'immediate',
        'super_admin_sla_breach_sms'          => 'immediate',
        'super_admin_sla_breach_inapp'        => 'immediate',
    ],

];
