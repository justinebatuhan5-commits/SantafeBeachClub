<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Notification;
use App\Services\ReservationService;

class ProcessPendingReservations extends Command
{
    protected $signature = 'reservations:process-pending {--expire-hours=24 : Cancel unpaid pending bookings older than this}';
    protected $description = 'Alert on pending reservations and automatically expire abandoned bookings';

    public function handle(ReservationService $reservationService): int
    {
        $expireHours = (int) $this->option('expire-hours');
        $cutoff = now()->subHours($expireHours);

        $this->info("Scanning pending bookings older than {$expireHours} hour(s)...");

        // Stale unpaid pending bookings
        $stale = Booking::whereIn('status', ['Pending Payment', 'Pending'])
            ->where('created_at', '<', $cutoff)
            ->whereNull('cancelled_at')
            ->get();

        $cancelledCount = 0;
        foreach ($stale as $b) {
            $reservationService->cancelReservation($b->id, "Auto-cancelled: Payment not received within {$expireHours} hours of booking.");
            $this->warn(" - Cancelled abandoned Booking #{$b->id} ({$b->guest_name})");
            $cancelledCount++;
        }

        // Active pending alerts
        $activePending = Booking::whereIn('status', ['Pending Payment', 'Pending'])->count();
        if ($activePending > 0) {
            Notification::log(
                "Pending Reservations Pending Review",
                "There are currently {$activePending} reservation(s) awaiting verification or deposit payment.",
                'warning'
            );
        }

        $this->info("Processed: {$cancelledCount} stale reservation(s) cancelled, {$activePending} active pending.");
        return 0;
    }
}
