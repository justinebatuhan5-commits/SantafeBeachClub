<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Support\Carbon;

class NotifyStaffCheckouts extends Command
{
    protected $signature = 'reservations:notify-checkouts';
    protected $description = 'Notify staff of expected departures and overdue check-outs';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $this->info("Scanning expected check-outs for {$today}...");

        // 1. Expected check-outs today
        $dueToday = Booking::where('check_out', $today)
            ->where('status', 'Checked In')
            ->get();

        if ($dueToday->isNotEmpty()) {
            Notification::log(
                "Departures Due Today ({$dueToday->count()} Stays)",
                "The following rooms have check-outs scheduled by 12:00 PM: " . $dueToday->pluck('accommodation_name')->implode(', ') . ".",
                'info'
            );
            $this->line("Logged departures alert for {$dueToday->count()} room(s).");
        }

        // 2. Overdue check-outs (check-out date is in the past, but still Checked In)
        $overdue = Booking::where('check_out', '<', $today)
            ->where('status', 'Checked In')
            ->get();

        if ($overdue->isNotEmpty()) {
            foreach ($overdue as $b) {
                Notification::log(
                    "OVERDUE Check-out Alert",
                    "URGENT: Booking #REF-" . str_pad($b->id, 3, '0', STR_PAD_LEFT) . " ({$b->guest_name} in {$b->accommodation_name}) was scheduled to depart on {$b->check_out->toDateString()} and is currently overdue.",
                    'warning'
                );
            }
            $this->warn("Found {$overdue->count()} overdue stay(s)!");
        }

        return 0;
    }
}
