<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\AccommodationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccommodationController extends Controller
{
    protected AccommodationService $accommodationService;

    public function __construct(AccommodationService $accommodationService)
    {
        $this->accommodationService = $accommodationService;
    }

    /**
     * List all room types and real-time availability.
     */
    public function index(): JsonResponse
    {
        $types = RoomType::all();
        $rooms = Room::all();

        return response()->json([
            'success' => true,
            'data' => [
                'room_types' => $types,
                'rooms' => $rooms,
            ],
        ]);
    }

    /**
     * Check availability for a room type within date range.
     */
    public function checkAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_type_id' => 'required|integer',
            'check_in'     => 'required|date_format:Y-m-d',
            'check_out'    => 'required|date_format:Y-m-d|after:check_in',
        ]);

        $available = $this->accommodationService->getAvailableRooms(
            $validated['room_type_id'],
            $validated['check_in'],
            $validated['check_out']
        );

        return response()->json([
            'success'      => true,
            'room_type_id' => $validated['room_type_id'],
            'check_in'     => $validated['check_in'],
            'check_out'    => $validated['check_out'],
            'available'    => $available,
            'is_sold_out'  => ($available <= 0),
        ]);
    }

    /**
     * Update maintenance override for a room.
     */
    public function setMaintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id'     => 'required|integer',
            'maintenance' => 'required|boolean',
        ]);

        $room = Room::findOrFail($validated['room_id']);

        if ($validated['maintenance']) {
            $room->status = 'maintenance';
        } else {
            // Cannot clear maintenance if active booking is checked in
            $hasOccupant = $room->bookings()->where('status', 'Checked In')->exists();
            if ($hasOccupant) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Cannot clear maintenance while room has an active checked-in guest.',
                ], 422);
            }
            $room->status = 'ready';
        }

        $room->save();

        return response()->json([
            'success' => true,
            'room'    => $room,
            'message' => 'Room maintenance status updated successfully.',
        ]);
    }
}
