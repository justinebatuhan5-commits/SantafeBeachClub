<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ReservationController extends Controller
{
    protected ReservationService $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    /**
     * List recent reservations with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::with(['room', 'payments']);

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'LIKE', "%{$search}%")
                  ->orWhere('guest_email', 'LIKE', "%{$search}%")
                  ->orWhere('guest_phone', 'LIKE', "%{$search}%");
            });
        }

        $limit = min(50, max(5, (int) $request->get('limit', 20)));
        $bookings = $query->orderBy('id', 'desc')->paginate($limit);

        return response()->json([
            'success' => true,
            'data'    => $bookings,
        ]);
    }

    /**
     * Calculate cost breakdown before creating reservation.
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_type_id'   => 'required|integer',
            'check_in'       => 'required|date_format:Y-m-d',
            'check_out'      => 'required|date_format:Y-m-d|after:check_in',
            'extra_adults'   => 'nullable|integer|min:0',
            'extra_children' => 'nullable|integer|min:0',
            'promo_code'     => 'nullable|string|max:50',
        ]);

        try {
            $breakdown = $this->reservationService->calculateStayCost(
                $validated['room_type_id'],
                $validated['check_in'],
                $validated['check_out'],
                (int) ($validated['extra_adults'] ?? 0),
                (int) ($validated['extra_children'] ?? 0),
                $validated['promo_code'] ?? null
            );

            return response()->json([
                'success' => true,
                'data'    => $breakdown,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a new reservation via transaction-safe service.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_type_id'           => 'required|integer',
            'check_in'               => 'required|date_format:Y-m-d',
            'check_out'              => 'required|date_format:Y-m-d|after:check_in',
            'guest_name'             => 'required|string|max:100',
            'guest_email'            => 'nullable|email|max:150',
            'guest_phone'            => 'nullable|string|max:25',
            'guest_country'          => 'nullable|string|max:50',
            'guests_count'           => 'nullable|integer|min:1',
            'extra_adults'           => 'nullable|integer|min:0',
            'extra_children'         => 'nullable|integer|min:0',
            'payment_method'         => 'nullable|string|max:50',
            'payment_amount'         => 'nullable|numeric|min:0',
            'transaction_id'         => 'nullable|string|max:100',
            'payment_status'         => 'nullable|string|max:50',
            'receipt_url'            => 'nullable|string|max:255',
            'promo_code'             => 'nullable|string|max:50',
            'guest_special_requests' => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->reservationService->createReservation($validated);

            if (!$result['success']) {
                return response()->json($result, 422);
            }

            return response()->json($result, 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm a pending reservation with inventory recheck.
     */
    public function confirm(int $id): JsonResponse
    {
        try {
            $result = $this->reservationService->confirmReservation($id);
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a reservation and release room inventory.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $reason = $request->input('reason', 'Cancelled by guest or admin');
        try {
            $result = $this->reservationService->cancelReservation($id, $reason);
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Process check-in for a booking.
     */
    public function checkIn(int $id): JsonResponse
    {
        try {
            $result = $this->reservationService->checkIn($id);
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Process check-out for a booking.
     */
    public function checkOut(int $id): JsonResponse
    {
        try {
            $result = $this->reservationService->checkOut($id);
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
