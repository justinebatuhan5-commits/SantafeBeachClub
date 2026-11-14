<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Tasks
|--------------------------------------------------------------------------
*/

// 1. Pre-arrival check-in reminders (runs daily at 09:00 AM)
Schedule::command('reservations:send-reminders --days=2')
    ->dailyAt('09:00')
    ->withoutOverlapping();

// 2. Staff check-in notifications (runs daily at 07:00 AM)
Schedule::command('reservations:notify-checkins')
    ->dailyAt('07:00')
    ->withoutOverlapping();

// 3. Staff check-out & overdue alerts (runs daily at 10:00 AM & 12:30 PM)
Schedule::command('reservations:notify-checkouts')
    ->dailyAt('10:00')
    ->withoutOverlapping();

Schedule::command('reservations:notify-checkouts')
    ->dailyAt('12:30')
    ->withoutOverlapping();

// 4. Stale pending booking expiration & alerts (runs hourly)
Schedule::command('reservations:process-pending --expire-hours=24')
    ->hourly()
    ->withoutOverlapping();

// 5. Daily executive operations & revenue report (runs daily at 23:30)
Schedule::command('reports:generate-daily')
    ->dailyAt('23:30')
    ->withoutOverlapping();
