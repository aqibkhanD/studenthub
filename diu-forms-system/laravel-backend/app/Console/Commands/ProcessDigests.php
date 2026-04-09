<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDigestJob;
use Illuminate\Console\Command;

/**
 * Artisan command that dispatches ProcessDigestJob to the queue.
 *
 * Register in App\Console\Kernel::schedule():
 *
 *   $schedule->command('notifications:process-digests --delivery=digest_hourly')
 *            ->hourlyAt(30)          // runs at :30 of every hour
 *            ->withoutOverlapping()
 *            ->runInBackground();
 *
 *   $schedule->command('notifications:process-digests --delivery=digest_daily')
 *            ->dailyAt('08:00')      // 08:00 BST daily
 *            ->withoutOverlapping()
 *            ->runInBackground();
 *
 * Manual run:
 *   php artisan notifications:process-digests --delivery=digest_hourly
 *   php artisan notifications:process-digests --delivery=digest_daily
 */
class ProcessDigests extends Command
{
    protected $signature   = 'notifications:process-digests {--delivery=digest_hourly : digest_hourly or digest_daily}';
    protected $description = 'Dispatch the digest email job for the given delivery window';

    public function handle(): int
    {
        $delivery = $this->option('delivery');

        if (!in_array($delivery, ['digest_hourly', 'digest_daily'])) {
            $this->error("Invalid --delivery value. Use 'digest_hourly' or 'digest_daily'.");
            return self::FAILURE;
        }

        ProcessDigestJob::dispatch($delivery)->onQueue('notifications');

        $this->info("[{$delivery}] Digest job dispatched to queue.");
        return self::SUCCESS;
    }
}
