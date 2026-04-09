<?php

namespace App\Jobs;

use App\Mail\AdminDigest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Picks up all pending digest rows for a given delivery window,
 * groups them by recipient, assembles one email per recipient, sends,
 * marks rows dispatched, and writes to notification_log.
 *
 * Dispatched by the Artisan command ProcessDigests (see Console/Commands/).
 * Scheduled in App\Console\Kernel:
 *   $schedule->command('notifications:process-digests --delivery=digest_hourly')->hourly();
 *   $schedule->command('notifications:process-digests --delivery=digest_daily')->dailyAt('08:00');
 */
class ProcessDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(
        private readonly string $delivery  // 'digest_hourly' | 'digest_daily'
    ) {}

    public function handle(): void
    {
        // 1. Pull all undispatched pending digest rows for this delivery type
        $rows = DB::table('pending_digests')
            ->where('delivery',    $this->delivery)
            ->where('dispatched',  false)
            ->where('channel',     'email')
            ->orderBy('created_at')
            ->get();

        if ($rows->isEmpty()) {
            Log::channel('notifications')->info("[ProcessDigest] No pending rows for {$this->delivery}.");
            return;
        }

        // 2. Group by (notifiable_type, notifiable_id)
        $grouped = $rows->groupBy(fn ($r) => $r->notifiable_type . ':' . $r->notifiable_id);

        $sent = 0;
        $skipped = 0;

        foreach ($grouped as $key => $recipientRows) {
            [$modelClass, $recipientId] = explode(':', $key, 2);

            $recipient = $modelClass::find($recipientId);
            if (!$recipient || !$recipient->email) {
                $skipped++;
                continue;
            }

            // 3. Check minimum-events threshold (configurable, default 2)
            $minEvents = config('notifications.digest.min_events', 2);
            if ($recipientRows->count() < $minEvents) {
                // Hold until more events accumulate — do NOT mark dispatched yet
                continue;
            }

            // 4. Build the payload items array
            $items = $recipientRows->map(fn ($r) => array_merge(
                json_decode($r->payload, true) ?? [],
                [
                    'event_type' => $r->event_type,
                    'ref'        => $r->reference,
                    'created_at' => \Carbon\Carbon::parse($r->created_at)->timezone('Asia/Dhaka')->format('d M H:i'),
                ]
            ))->values()->toArray();

            $dashboardUrl = config('app.admin_dashboard_url', config('app.url') . '/admin');

            // 5. Send the digest email
            try {
                Mail::to($recipient->email)->send(
                    new AdminDigest(
                        adminName:    $recipient->name,
                        adminEmail:   $recipient->email,
                        delivery:     $this->delivery,
                        items:        $items,
                        dashboardUrl: $dashboardUrl
                    )
                );

                // 6. Mark all rows for this recipient as dispatched
                $rowIds = $recipientRows->pluck('id')->toArray();
                DB::table('pending_digests')
                    ->whereIn('id', $rowIds)
                    ->update(['dispatched' => true, 'dispatched_at' => now(), 'updated_at' => now()]);

                // 7. Write to notification_log
                $this->logDigest($recipient, $recipientRows, 'sent');

                $sent++;
            } catch (\Throwable $e) {
                Log::error('[ProcessDigest] Mail send failed', [
                    'recipient' => $recipientId,
                    'error'     => $e->getMessage(),
                ]);
                $this->logDigest($recipient, $recipientRows, 'failed');
            }
        }

        Log::channel('notifications')->info("[ProcessDigest] {$this->delivery} complete", [
            'sent'    => $sent,
            'skipped' => $skipped,
            'total'   => $rows->count(),
        ]);
    }

    private function logDigest(object $recipient, Collection $rows, string $status): void
    {
        $inserts = $rows->map(fn ($r) => [
            'notifiable_id'   => $r->notifiable_id,
            'notifiable_type' => $r->notifiable_type,
            'event_type'      => $r->event_type,
            'channel'         => 'email',
            'delivery'        => $this->delivery,
            'status'          => $status,
            'reference'       => $r->reference,
            'payload'         => $r->payload,
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->toArray();

        DB::table('notification_log')->insert($inserts);
    }
}
