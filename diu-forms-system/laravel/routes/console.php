<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled Tasks — DIU Student Services
|--------------------------------------------------------------------------
*/

// Check SLA breaches every 30 minutes
Schedule::command('sla:check')->everyThirtyMinutes()->withoutOverlapping();

// Retry failed SMS notifications every hour
Schedule::command('queue:retry-failed-sms')->hourly();
