<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Payment;
use App\Models\Notification;
use Illuminate\Support\Carbon;

class GenerateDailyReport extends Command
{
    protected $signature = 'reports:generate-daily';
    protected $description = 'Generate and log scheduled daily accommodation and revenue summary report';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $this->info("Generating Daily Executive Summary for {$today}...");

        $totalUnits = Room::count();
        $occupiedUnits = Room::where('status', 'occupied')->count();
        $maintenanceUnits = Room::where('status', 'maintenance')->count();
        $availableUnits = Room::where('status', 'ready')->count();
        $occupancyPct = $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0;

        $checkinsToday = Booking::where('check_in', $today)->whereIn('status', ['Confirmed', 'Checked In'])->count();
        $checkoutsToday = Booking::where('check_out', $today)->where('status', 'Checked Out')->count();

        $activePending = Booking::whereIn('status', ['Pending', 'Pending Payment'])->count();
        $cancelledToday = Booking::whereDate('cancelled_at', $today)->count();

        $dailyRevenue = (float) Payment::whereDate('paid_at', $today)->where('status', 'verified')->sum('amount');

        $summary = "Summary for {$today}: Occupancy {$occupancyPct}% ({$occupiedUnits}/{$totalUnits} units). Check-ins: {$checkinsToday}, Check-outs: {$checkoutsToday}. Pending: {$activePending}. Today's Verified Revenue: ₱" . number_format($dailyRevenue, 2) . ".";

        Notification::log(
            "Daily Operations Report ({$today})",
            $summary,
            'info'
        );

        $this->info($summary);
        return 0;
    }
}
