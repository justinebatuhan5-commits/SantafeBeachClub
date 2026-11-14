<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class AutomationController extends Controller
{
    /**
     * Get automation overview, task schedules, and recent execution history.
     */
    public function index(): JsonResponse
    {
        $tasks = [
            [
                'id'          => 'reservations:send-reminders',
                'name'        => 'Pre-Arrival Guest Reminders',
                'description' => 'Scans upcoming arrivals 2 days ahead and dispatches check-in notifications.',
                'schedule'    => 'Daily at 09:00 AM',
                'enabled'     => (bool) Setting::get('automation_reminders_enabled', true),
            ],
            [
                'id'          => 'reservations:notify-checkins',
                'name'        => 'Front Desk Check-in Briefing',
                'description' => 'Prepares front desk staff with expected arrivals list for the day.',
                'schedule'    => 'Daily at 07:00 AM',
                'enabled'     => (bool) Setting::get('automation_checkins_enabled', true),
            ],
            [
                'id'          => 'reservations:notify-checkouts',
                'name'        => 'Departure & Overdue Alerts',
                'description' => 'Monitors 12:00 PM check-outs and triggers urgent alerts for overdue stays.',
                'schedule'    => 'Daily at 10:00 AM & 12:30 PM',
                'enabled'     => (bool) Setting::get('automation_checkouts_enabled', true),
            ],
            [
                'id'          => 'reservations:process-pending',
                'name'        => 'Pending Booking Expiration',
                'description' => 'Automatically releases inventory for unpaid pending bookings older than 24 hours.',
                'schedule'    => 'Hourly',
                'enabled'     => (bool) Setting::get('automation_pending_enabled', true),
            ],
            [
                'id'          => 'reports:generate-daily',
                'name'        => 'Daily Operations & Revenue Summary',
                'description' => 'Generates nightly operational audit report on occupancy, sales, and movements.',
                'schedule'    => 'Daily at 11:30 PM',
                'enabled'     => (bool) Setting::get('automation_reports_enabled', true),
            ],
        ];

        // Recent automated logs
        $logs = Notification::orderBy('id', 'desc')->take(20)->get();

        return response()->json([
            'success'   => true,
            'tasks'     => $tasks,
            'recent_logs' => $logs,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Manually trigger an automation task on-demand from the dashboard.
     */
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => 'required|string',
        ]);

        $allowedTasks = [
            'reservations:send-reminders',
            'reservations:notify-checkins',
            'reservations:notify-checkouts',
            'reservations:process-pending',
            'reports:generate-daily',
        ];

        if (!in_array($validated['task_id'], $allowedTasks, true)) {
            return response()->json(['success' => false, 'error' => 'Unknown automation task.'], 404);
        }

        try {
            Artisan::call($validated['task_id']);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'task_id' => $validated['task_id'],
                'message' => 'Task executed successfully.',
                'output'  => trim($output),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
