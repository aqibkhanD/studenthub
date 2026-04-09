<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| StudentsHub — Scheduled Commands
|--------------------------------------------------------------------------
*/

// SLA monitor: check every 15 minutes for breaches and trigger escalations
Schedule::command('sla:monitor')->everyFifteenMinutes()->withoutOverlapping();

// Clean up old read notifications older than 90 days
Schedule::command('notifications:prune --days=90')->daily()->at('02:00');

// Weekly management digest — every Monday at 08:00 local time
// Can also be triggered manually: php artisan digest:management
Schedule::command('digest:management')->weekly()->mondays()->at('08:00');
