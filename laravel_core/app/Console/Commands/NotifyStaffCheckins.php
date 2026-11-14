<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Support\Carbon;

class NotifyStaffCheckins extends Command
{
    protected $signature = 'reservations:notify-checkins';
    protected $description = 'Notify front desk staff about today\'s upcoming guest arrivals';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $this->info("Checking expected guest arrivals for today ({$today})...");

        $upcoming = Booking::where('check_in', $today)
            ->whereIn('status', ['Confirmed', 'Pending Payment', 'Pending'])
            ->get();

        if ($upcoming->isEmpty()) {
            $this->info("No upcoming arrivals scheduled for today.");
            return 0;
        }

        $count = $upcoming->count();
        $names = $upcoming->take(3)->pluck('guest_name')->implode(', ');
        if ($count > 3) {
            $names .= " and " . ($count - 3) . " more";
        }

        Notification::log(
            "Today's Arrivals: {$count} Guest(s)",
            "Expected check-ins today ({$today}): {$names}. Please ensure rooms are inspected and keycards prepared.",
            'info'
        );

        $this->info("Alert logged for {$count} arrival(s).");
        return 0;
    }
}
