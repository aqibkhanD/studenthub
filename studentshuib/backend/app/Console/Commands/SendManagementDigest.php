<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendManagementDigest extends Command
{
    protected $signature   = 'digest:management {--days=7 : Period in days to summarise}';
    protected $description = 'Send weekly analytics digest to management and super-admin users';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $from = now()->subDays($days);

        // Fetch recipients — all active management and super-admin accounts
        $recipients = User::whereIn('role', ['management', 'super_admin'])
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get();

        if ($recipients->isEmpty()) {
            $this->info('No management/super-admin users found — no digest sent.');
            return self::SUCCESS;
        }

        $data = $this->buildData($days, $from);

        $sent   = 0;
        $failed = 0;

        foreach ($recipients as $user) {
            try {
                Mail::send(
                    'emails.management_digest',
                    array_merge($data, ['recipientName' => $user->name]),
                    function ($message) use ($user, $data) {
                        $message
                            ->to($user->email, $user->name)
                            ->subject("StudentsHub Weekly Digest — {$data['dateRange']}");
                    }
                );
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("Failed to send to {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info("Digest sent to {$sent}/{$recipients->count()} recipients. Failed: {$failed}.");
        return self::SUCCESS;
    }

    private function buildData(int $days, \Carbon\Carbon $from): array
    {
        $submissionsInPeriod = Submission::where('submitted_at', '>=', $from)->count();
        $resolvedInPeriod    = Submission::whereIn('status', ['approved', 'completed', 'rejected'])
            ->where('updated_at', '>=', $from)
            ->count();
        $slaBreachedCount    = Submission::where('sla_deadline', '<', now())
            ->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled'])
            ->count();
        $pendingCount        = Submission::whereIn('status', ['submitted', 'routed', 'in_review', 'action_required', 'escalated'])
            ->count();
        $totalStudents       = User::where('role', 'student')->where('is_active', true)->count();

        // Avg resolution hours for the period
        $avgResolution = Submission::whereIn('status', ['approved', 'completed'])
            ->where('updated_at', '>=', $from)
            ->whereNotNull('submitted_at')
            ->whereNotNull('resolved_at')
            ->selectRaw("AVG(EXTRACT(EPOCH FROM (resolved_at - submitted_at)) / 3600) as avg_hours")
            ->value('avg_hours');

        // Top 5 departments by volume this period
        $departments = Department::withCount([
            'submissions as week_submissions' => fn($q) => $q->where('submitted_at', '>=', $from),
            'submissions as open_submissions'  => fn($q) => $q->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled']),
            'submissions as sla_breached'      => fn($q) => $q
                ->where('sla_deadline', '<', now())
                ->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled']),
        ])
        ->orderByDesc('week_submissions')
        ->limit(5)
        ->get();

        // Most overdue (top 5)
        $overdue = Submission::with(['formType:id,name', 'department:id,name'])
            ->where('sla_deadline', '<', now())
            ->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled'])
            ->orderBy('sla_deadline')
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'reference_no'  => $s->reference_no,
                'form_type'     => $s->formType?->name ?? '—',
                'department'    => $s->department?->name ?? '—',
                'hours_overdue' => (int) now()->diffInHours($s->sla_deadline),
            ]);

        return [
            'days'                => $days,
            'dateRange'           => $from->format('d M') . ' – ' . now()->format('d M Y'),
            'generatedAt'         => now()->format('d M Y, H:i'),
            'submissionsInPeriod' => $submissionsInPeriod,
            'resolvedInPeriod'    => $resolvedInPeriod,
            'slaBreachedCount'    => $slaBreachedCount,
            'pendingCount'        => $pendingCount,
            'totalStudents'       => $totalStudents,
            'avgResolution'       => $avgResolution !== null ? round($avgResolution) : null,
            'departments'         => $departments,
            'overdue'             => $overdue,
        ];
    }
}
