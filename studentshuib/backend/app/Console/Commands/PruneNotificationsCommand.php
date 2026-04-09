<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class PruneNotificationsCommand extends Command
{
    protected $signature   = 'notifications:prune {--days=90 : Delete read notifications older than this many days}';
    protected $description = 'Remove old read notifications to keep the table lean';

    public function handle(): void
    {
        $days    = (int) $this->option('days');
        $cutoff  = now()->subDays($days);

        $deleted = Notification::where('is_read', true)
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} notifications older than {$days} days.");
    }
}
