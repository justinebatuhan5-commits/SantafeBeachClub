<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\Booking;
use Illuminate\Support\Carbon;

class AccommodationService
{
    /**
     * Determine usable physical room capacity for a room type.
     */
    public function getUsableCapacity(string $roomTypeName): int
    {
        return Room::where('type', $roomTypeName)
            ->where('status', '<>', 'maintenance')
            ->count();
    }

    /**
     * Calculate available inventory for a specific room type and date range.
     * Prevents overlapping bookings and considers physical room maintenance status.
     */
    public function getAvailableRooms(int $roomTypeId, string $checkIn, string $checkOut): int
    {
        $startDate = Carbon::parse($checkIn);
        $endDate = Carbon::parse($checkOut);

        if ($startDate->gte($endDate)) {
            return 0;
        }

        $roomType = RoomType::find($roomTypeId);
        if (!$roomType) {
            return 0;
        }

        $usableCapacity = $this->getUsableCapacity($roomType->name);

        // Overlap formula:
        // existing check_in < requested check_out AND existing check_out > requested check_in
        $overlapCount = Booking::where(function ($query) use ($roomType, $roomTypeId) {
            $query->where(function ($sub) use ($roomType) {
                $sub->whereNotNull('room_id')
                    ->whereHas('room', function ($r) use ($roomType) {
                        $r->where('type', $roomType->name)
                          ->where('status', '<>', 'maintenance');
                    });
            })->orWhere(function ($sub) use ($roomType, $roomTypeId) {
                $sub->whereNull('room_id')
                    ->where(function ($w) use ($roomType, $roomTypeId) {
                        $w->where('room_type_id', $roomTypeId)
                          ->orWhere('accommodation_name', 'LIKE', '%' . $roomType->name . '%');
                    });
            });
        })
        ->whereNotIn('status', ['Cancelled', 'Cancelled / Refunded', 'Checked Out'])
        ->whereIn('status', ['Confirmed', 'Checked In', 'Pending Payment', 'Pending'])
        ->where('check_in', '<', $endDate->toDateString())
        ->where('check_out', '>', $startDate->toDateString())
        ->count();

        return max(0, $usableCapacity - $overlapCount);
    }

    /**
     * Find an available physical room for assignment.
     */
    public function findAvailableRoom(string $roomTypeName, string $checkIn, string $checkOut): ?Room
    {
        $startDate = Carbon::parse($checkIn)->toDateString();
        $endDate = Carbon::parse($checkOut)->toDateString();

        return Room::where('type', $roomTypeName)
            ->where('status', '<>', 'maintenance')
            ->whereDoesntHave('bookings', function ($query) use ($startDate, $endDate) {
                $query->whereIn('status', ['Confirmed', 'Checked In', 'Pending Payment', 'Pending'])
                      ->where('check_in', '<', $endDate)
                      ->where('check_out', '>', $startDate);
            })
            ->orderByRaw("CASE WHEN status = 'ready' THEN 0 ELSE 1 END")
            ->orderBy('room_number', 'asc')
            ->first();
    }
}
