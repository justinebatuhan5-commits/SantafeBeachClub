<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class SendReservationReminders extends Command
{
    protected $signature = 'reservations:send-reminders {--days=2 : Number of days before check-in}';
    protected $description = 'Send automated pre-arrival check-in reminders to guests';

    public function handle(): int
    {
        $daysAhead = (int) $this->option('days');
        $targetDate = Carbon::today()->addDays($daysAhead)->toDateString();

        $this->info("Scanning reservations for pre-arrival reminders ({$daysAhead} days ahead: {$targetDate})...");

        // Find confirmed bookings for the target date where reminder has not yet been sent
        $bookings = Booking::where('check_in', $targetDate)
            ->whereIn('status', ['Confirmed', 'Pending Payment', 'Pending'])
            ->whereNull('prearrival_reminder_sent_at')
            ->whereNotNull('guest_email')
            ->get();

        $sentCount = 0;
        foreach ($bookings as $b) {
            // Avoid duplicate sending
            $b->prearrival_reminder_sent_at = now();
            $b->save();

            Notification::log(
                'Pre-Arrival Reminder Sent',
                "Pre-arrival reminder sent to {$b->guest_name} ({$b->guest_email}) for stay beginning {$targetDate} (#REF-" . str_pad($b->id, 3, '0', STR_PAD_LEFT) . ").",
                'info'
            );

            $this->line(" - Reminder logged for Booking #{$b->id} ({$b->guest_name})");
            $sentCount++;
        }

        $this->info("Completed: {$sentCount} reminder(s) processed.");
        return 0;
    }
}
