<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Payment;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

class ReservationService
{
    protected AccommodationService $accommodationService;

    public function __construct(AccommodationService $accommodationService)
    {
        $this->accommodationService = $accommodationService;
    }

    /**
     * Compute stay pricing based on base rate, nights, extra persons, and seasonal/weekend rules.
     */
    public function calculateStayCost(int $roomTypeId, string $checkIn, string $checkOut, int $extraAdults = 0, int $extraChildren = 0, ?string $promoCode = null): array
    {
        $startDate = Carbon::parse($checkIn);
        $endDate   = Carbon::parse($checkOut);

        if ($startDate->gte($endDate)) {
            throw new InvalidArgumentException('Check-out date must be later than check-in date.');
        }

        $nights = $startDate->diffInDays($endDate);

        $roomType = RoomType::findOrFail($roomTypeId);
        $baseRate = (float) $roomType->price;
        $baseTotal = $baseRate * $nights;

        // Extra person surcharge: ₱500 per extra adult per night
        $extraAdultRate = 500.00;
        $extraAdultTotal = $extraAdults * $extraAdultRate * $nights;

        $subtotal = $baseTotal + $extraAdultTotal;

        // Promo discount
        $discountAmount = 0.00;
        if ($promoCode && strtoupper(trim($promoCode)) === 'WELCOME10') {
            $discountAmount = round($subtotal * 0.10, 2);
        }

        $totalAmount = max(0, $subtotal - $discountAmount);

        // Deposit percentage from settings (default 50%)
        $depositPct = (float) Setting::get('deposit_percentage', 50);
        $depositDue = round($totalAmount * ($depositPct / 100), 2);

        return [
            'room_type_id'     => $roomTypeId,
            'room_type_name'   => $roomType->name,
            'base_rate'        => $baseRate,
            'nights'           => $nights,
            'base_total'       => $baseTotal,
            'extra_adults'     => $extraAdults,
            'extra_adult_fee'  => $extraAdultTotal,
            'subtotal'         => $subtotal,
            'discount_amount'  => $discountAmount,
            'total_amount'     => $totalAmount,
            'deposit_percent'  => $depositPct,
            'deposit_amount'   => $depositDue,
        ];
    }

    /**
     * Create a verified reservation wrapped inside a database transaction.
     * Prevents double-booking and rechecks availability under row locks.
     */
    public function createReservation(array $data): array
    {
        $roomTypeId = (int) ($data['room_type_id'] ?? 0);
        $checkIn    = $data['check_in'] ?? '';
        $checkOut   = $data['check_out'] ?? '';
        $guestName  = trim((string) ($data['guest_name'] ?? ''));
        $guestEmail = trim((string) ($data['guest_email'] ?? ''));

        if ($roomTypeId <= 0 || empty($checkIn) || empty($checkOut) || empty($guestName)) {
            throw new InvalidArgumentException('Missing required booking parameters.');
        }

        $startDate = Carbon::parse($checkIn);
        $endDate   = Carbon::parse($checkOut);
        if ($startDate->gte($endDate)) {
            throw new InvalidArgumentException('Check-out date must be later than check-in date.');
        }

        return DB::transaction(function () use ($roomTypeId, $startDate, $endDate, $guestName, $guestEmail, $data) {
            $roomType = RoomType::lockForUpdate()->find($roomTypeId);
            if (!$roomType) {
                throw new RuntimeException('Selected accommodation type not found.');
            }

            // Lock rooms of this type
            Room::where('type', $roomType->name)
                ->where('status', '<>', 'maintenance')
                ->lockForUpdate()
                ->get();

            $availableCount = $this->accommodationService->getAvailableRooms(
                $roomTypeId,
                $startDate->toDateString(),
                $endDate->toDateString()
            );

            if ($availableCount <= 0) {
                return [
                    'success'   => false,
                    'message'   => 'Selected accommodation is sold out for these dates.',
                    'available' => 0,
                ];
            }

            // Calculate official cost breakdown
            $pricing = $this->calculateStayCost(
                $roomTypeId,
                $startDate->toDateString(),
                $endDate->toDateString(),
                (int) ($data['extra_adults'] ?? 0),
                (int) ($data['extra_children'] ?? 0),
                $data['promo_code'] ?? null
            );

            // Locate and assign physical room
            $assignedRoom = $this->accommodationService->findAvailableRoom(
                $roomType->name,
                $startDate->toDateString(),
                $endDate->toDateString()
            );

            $roomId    = $assignedRoom ? $assignedRoom->id : null;
            $roomLabel = $assignedRoom ? $assignedRoom->name : $roomType->name;

            // Detect guest loyalty tier
            $priorStays = Booking::where(function ($q) use ($guestEmail, $guestName) {
                if ($guestEmail) {
                    $q->where('guest_email', $guestEmail);
                } else {
                    $q->where('guest_name', $guestName);
                }
            })->whereNotIn('status', ['Cancelled', 'Pending Payment'])->count();

            $guestType = $priorStays === 0 ? 'First Visit' : ($priorStays === 1 ? 'Returning Guest' : 'VIP Member');

            $booking = Booking::create([
                'room_type_id'           => $roomTypeId,
                'room_id'                => $roomId,
                'accommodation_name'     => $roomLabel,
                'guest_name'             => $guestName,
                'guest_email'            => $guestEmail ?: null,
                'guest_phone'            => $data['guest_phone'] ?? null,
                'guest_country'          => $data['guest_country'] ?? null,
                'guest_type'             => $data['guest_type'] ?? $guestType,
                'check_in'               => $startDate->toDateString(),
                'check_out'              => $endDate->toDateString(),
                'guests_count'           => (int) ($data['guests_count'] ?? 1),
                'extra_adults'           => (int) ($data['extra_adults'] ?? 0),
                'extra_children'         => (int) ($data['extra_children'] ?? 0),
                'eta'                    => $data['eta'] ?? '14:00',
                'status'                 => $data['status'] ?? 'Pending',
                'checkin_token'          => bin2hex(random_bytes(16)),
                'cancellation_token'     => bin2hex(random_bytes(16)),
                'payment_method'         => $data['payment_method'] ?? 'Pay at Check-in',
                'payment_status'         => $data['payment_status'] ?? 'Pending',
                'promo_code'             => $data['promo_code'] ?? null,
                'discount_amount'        => $pricing['discount_amount'],
                'guest_special_requests' => $data['guest_special_requests'] ?? null,
            ]);

            // If initial payment details provided, record payment
            if (!empty($data['payment_amount']) && (float)$data['payment_amount'] > 0) {
                Payment::create([
                    'booking_id'     => $booking->id,
                    'guest_name'     => $guestName,
                    'guest_email'    => $guestEmail ?: null,
                    'amount'         => (float) $data['payment_amount'],
                    'payment_method' => $data['payment_method'] ?? 'Bank Deposit',
                    'transaction_id' => $data['transaction_id'] ?? ('TXN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8))),
                    'status'         => $data['payment_status'] ?? 'pending',
                    'receipt_url'    => $data['receipt_url'] ?? null,
                    'purpose'        => 'deposit',
                ]);
            }

            Notification::log(
                'New Reservation Created',
                "Booking #REF-" . str_pad($booking->id, 3, '0', STR_PAD_LEFT) . " received for {$guestName} ({$roomLabel}) · ₱" . number_format($pricing['total_amount'], 2),
                'info'
            );

            return [
                'success'     => true,
                'booking_id'  => $booking->id,
                'booking_ref' => 'REF-' . str_pad($booking->id, 3, '0', STR_PAD_LEFT),
                'room'        => $roomLabel,
                'pricing'     => $pricing,
                'available'   => $availableCount - 1,
            ];
        });
    }

    /**
     * Confirm a pending reservation with strict inventory re-check.
     */
    public function confirmReservation(int $bookingId): array
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::lockForUpdate()->find($bookingId);
            if (!$booking) {
                throw new RuntimeException('Reservation not found.');
            }

            if ($booking->status === 'Confirmed') {
                return ['success' => true, 'message' => 'Reservation is already confirmed.'];
            }

            if ($booking->status === 'Cancelled') {
                throw new RuntimeException('Cannot confirm a cancelled reservation.');
            }

            // Recheck availability if room not yet assigned
            if (!$booking->room_id && $booking->room_type_id) {
                $available = $this->accommodationService->getAvailableRooms(
                    $booking->room_type_id,
                    $booking->check_in->toDateString(),
                    $booking->check_out->toDateString()
                );
                if ($available <= 0) {
                    throw new RuntimeException('Cannot confirm: No rooms available for this date range.');
                }
                $assigned = $this->accommodationService->findAvailableRoom(
                    $booking->roomType->name,
                    $booking->check_in->toDateString(),
                    $booking->check_out->toDateString()
                );
                if ($assigned) {
                    $booking->room_id = $assigned->id;
                    $booking->accommodation_name = $assigned->name;
                }
            }

            $booking->status = 'Confirmed';
            $booking->save();

            Notification::log(
                'Reservation Confirmed',
                "Reservation #REF-" . str_pad($booking->id, 3, '0', STR_PAD_LEFT) . " for {$booking->guest_name} is confirmed.",
                'success'
            );

            return ['success' => true, 'message' => 'Reservation successfully confirmed.'];
        });
    }

    /**
     * Cancel a reservation and release room inventory.
     */
    public function cancelReservation(int $bookingId, string $reason = 'Cancelled by user'): array
    {
        return DB::transaction(function () use ($bookingId, $reason) {
            $booking = Booking::lockForUpdate()->find($bookingId);
            if (!$booking) {
                throw new RuntimeException('Reservation not found.');
            }

            if ($booking->status === 'Cancelled') {
                return ['success' => true, 'message' => 'Reservation is already cancelled.'];
            }

            $booking->status = 'Cancelled';
            $booking->cancelled_at = now();
            $booking->cancellation_reason = $reason;
            $booking->save();

            // Release physical room status if occupied
            if ($booking->room_id) {
                $room = Room::find($booking->room_id);
                if ($room && $room->status === 'occupied') {
                    $room->status = 'ready';
                    $room->save();
                }
            }

            Notification::log(
                'Reservation Cancelled',
                "Reservation #REF-" . str_pad($booking->id, 3, '0', STR_PAD_LEFT) . " for {$booking->guest_name} was cancelled. Reason: {$reason}",
                'warning'
            );

            return ['success' => true, 'message' => 'Reservation cancelled and inventory released.'];
        });
    }

    /**
     * Check in a guest and safely update accommodation occupancy.
     */
    public function checkIn(int $bookingId): array
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::lockForUpdate()->find($bookingId);
            if (!$booking) {
                throw new RuntimeException('Booking not found.');
            }

            if ($booking->status === 'Checked In') {
                return ['success' => true, 'message' => 'Guest is already checked in.'];
            }

            $booking->status = 'Checked In';
            $booking->checked_in_at = now();
            $booking->save();

            // Mark physical room as occupied
            if ($booking->room_id) {
                Room::where('id', $booking->room_id)->update(['status' => 'occupied']);
            }

            Notification::log(
                'Guest Checked In',
                "{$booking->guest_name} has checked in to {$booking->accommodation_name}.",
                'success'
            );

            return ['success' => true, 'message' => 'Check-in processed successfully.'];
        });
    }

    /**
     * Check out a guest and restore accommodation status.
     */
    public function checkOut(int $bookingId): array
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = Booking::lockForUpdate()->find($bookingId);
            if (!$booking) {
                throw new RuntimeException('Booking not found.');
            }

            $booking->status = 'Checked Out';
            $booking->checked_out_at = now();
            $booking->save();

            // Return room to ready status unless it was placed in maintenance
            if ($booking->room_id) {
                $room = Room::find($booking->room_id);
                if ($room && $room->status !== 'maintenance') {
                    $room->status = 'ready';
                    $room->save();
                }
            }

            Notification::log(
                'Guest Checked Out',
                "{$booking->guest_name} has checked out from {$booking->accommodation_name}.",
                'info'
            );

            return ['success' => true, 'message' => 'Check-out processed successfully.'];
        });
    }

    /**
     * Normalize informal accommodation names or typos to canonical room_types slugs.
     */
    public function normalizeAccommodationSlug(?string $roomNameOrSlug): ?string
    {
        if ($roomNameOrSlug === null || trim($roomNameOrSlug) === '') {
            return null;
        }

        $q = strtolower(trim($roomNameOrSlug));
        $q = preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $q)) ?? $q;

        if (preg_match('/\b(beach\s*view|beachview|duplx|duplex)\b/', $q) && !str_contains($q, 'seaview') && !str_contains($q, 'sea view')) {
            if (str_contains($q, 'beachview') || str_contains($q, 'beach view') || str_contains($q, 'duplx') || $q === 'duplex') {
                return 'beachview_duplex';
            }
        }
        if (preg_match('/\b(sea\s*view|seaview|seaveiw|ocean\s*view)\b/', $q)) {
            return 'seaview_duplex';
        }
        if (preg_match('/\b(beach\s*villa|vila|villa)\b/', $q)) {
            return 'beach_villa';
        }
        if (preg_match('/\b(standard\s*family|family\s*room|standard\s*king|king\s*room|king)\b/', $q)) {
            return 'standard_king';
        }
        if (preg_match('/\b(standard\s*room|standrd|standard)\b/', $q)) {
            return 'standard_room';
        }
        if (preg_match('/\b(beach\s*cottage|cottage)\b/', $q)) {
            return 'cottage';
        }

        return null;
    }

    /**
     * Calculate exact backend stay pricing for chatbot quotes using live MySQL tables.
     * Supports duration-only estimates (without calendar dates) as well as dated checks.
     */
    public function calculateGuestAccommodationEstimate(
        string $roomSlugOrName,
        int $guests = 2,
        int $nights = 1,
        ?string $checkIn = null,
        ?string $checkOut = null,
        ?string $promoCode = null
    ): array {
        $slug = $this->normalizeAccommodationSlug($roomSlugOrName) ?? trim($roomSlugOrName);
        $guests = max(1, $guests);
        $nights = max(1, $nights);

        $roomType = DB::table('room_types')->where('name', $slug)->first();
        if (!$roomType && $slug === 'villa') {
            $slug = 'beach_villa';
            $roomType = DB::table('room_types')->where('name', $slug)->first();
        }
        if (!$roomType) {
            return ['success' => false, 'error' => 'Accommodation type not found.'];
        }

        $stats = DB::table('rooms')
            ->where('type', $roomType->name)
            ->selectRaw('MIN(capacity) as min_capacity, MAX(capacity) as max_capacity, COUNT(*) as unit_count')
            ->first();

        $limits = DB::table('room_extra_person_limits')
            ->where('room_type', $roomType->name)
            ->first();

        $baseCapacity = (int) ($stats->min_capacity ?? 2);
        $maxExtraAdults = (int) ($limits->max_extra_adults ?? 0);
        $maxExtraChildren = (int) ($limits->max_extra_children ?? 0);
        $maxAdultsCapacity = $baseCapacity + $maxExtraAdults;
        $maxTotalCapacity = $maxAdultsCapacity + $maxExtraChildren;

        $displayNames = [
            'beachview_duplex' => 'Beachview Duplex',
            'seaview_duplex'   => 'Seaview Duplex',
            'beach_villa'      => 'Beach Villa',
            'standard_king'    => 'Standard Family Room',
            'standard_room'    => 'Standard Room',
            'cottage'          => 'Beach Cottage',
            'villa'            => 'Luxury Villa',
        ];
        $displayName = $displayNames[$roomType->name] ?? ucwords(str_replace('_', ' ', (string) $roomType->name));

        $basePricePerNight = (float) $roomType->price;
        $extraRatePerAdultOnce = in_array($roomType->name, ['beachview_duplex', 'seaview_duplex', 'beach_villa', 'villa'], true)
            ? 1000.0
            : ($maxExtraAdults > 0 ? 700.0 : 0.0);

        $fitsGuests = $guests <= $maxAdultsCapacity;
        $extraAdults = max(0, $guests - $baseCapacity);
        $extraPersonTotal = $fitsGuests ? ($extraAdults * $extraRatePerAdultOnce) : 0.0;

        $hasSpecificDates = false;
        $availableUnits = null;
        $weekendSurchargeTotal = 0.0;
        $seasonalAdjustmentTotal = 0.0;
        $roomSubtotal = 0.0;

        if ($checkIn !== null && $checkOut !== null && $checkIn !== '' && $checkOut !== '') {
            try {
                $start = \Carbon\Carbon::parse($checkIn)->startOfDay();
                $end = \Carbon\Carbon::parse($checkOut)->startOfDay();
                if ($end->greaterThan($start)) {
                    $hasSpecificDates = true;
                    $nights = (int) $start->diffInDays($end);
                    $availableUnits = $this->accommodationService->getAvailableRooms(
                        (int) $roomType->id,
                        $start->toDateString(),
                        $end->toDateString()
                    );

                    $activeRules = DB::table('pricing_rules')->where('is_active', 1)->get();
                    $cursor = $start->copy();
                    while ($cursor->lessThan($end)) {
                        $nightRate = $basePricePerNight;
                        $dateStr = $cursor->toDateString();
                        $isWeekend = in_array($cursor->dayOfWeekIso, [5, 6], true);

                        foreach ($activeRules as $rule) {
                            $appliesTo = (string) ($rule->room_type ?? 'all');
                            if ($appliesTo !== 'all' && $appliesTo !== $roomType->name) {
                                continue;
                            }
                            if (($rule->rule_type ?? '') === 'weekend' && $isWeekend) {
                                $adj = ($rule->modifier_type ?? '') === 'percentage'
                                    ? ($basePricePerNight * ((float) $rule->modifier_value / 100))
                                    : (float) $rule->modifier_value;
                                $weekendSurchargeTotal += $adj;
                                $nightRate += $adj;
                            } elseif (($rule->rule_type ?? '') === 'seasonal' && !empty($rule->start_date) && !empty($rule->end_date)) {
                                if ($dateStr >= $rule->start_date && $dateStr <= $rule->end_date) {
                                    $adj = ($rule->modifier_type ?? '') === 'percentage'
                                        ? ($basePricePerNight * ((float) $rule->modifier_value / 100))
                                        : (float) $rule->modifier_value;
                                    $seasonalAdjustmentTotal += $adj;
                                    $nightRate += $adj;
                                }
                            }
                        }

                        $roomSubtotal += $nightRate;
                        $cursor->addDay();
                    }
                }
            } catch (\Throwable $e) {
                $hasSpecificDates = false;
            }
        }

        if (!$hasSpecificDates) {
            $roomSubtotal = $basePricePerNight * $nights;
        }

        $subtotalBeforePromo = $roomSubtotal + $extraPersonTotal;
        $discountAmount = 0.0;
        $appliedPromo = null;

        if ($promoCode !== null && trim($promoCode) !== '') {
            $cleanPromo = strtoupper(trim($promoCode));
            $promo = DB::table('promotions')
                ->whereRaw('(UPPER(code) = ? OR UPPER(title) = ?)', [$cleanPromo, $cleanPromo])
                ->where('is_active', 1)
                ->where('valid_from', '<=', date('Y-m-d'))
                ->where('valid_until', '>=', date('Y-m-d'))
                ->first();
            if ($promo) {
                $discountAmount = ($promo->discount_type ?? '') === 'percent'
                    ? ($subtotalBeforePromo * ((float) $promo->discount_value / 100))
                    : min($subtotalBeforePromo, (float) $promo->discount_value);
                $appliedPromo = $promo->code ?: $promo->title;
            }
        }

        $totalAmount = max(0.0, $subtotalBeforePromo - $discountAmount);
        $depositPct = (float) (DB::table('settings')->where('setting_key', 'deposit_percentage')->value('setting_value') ?? 50);
        if ($depositPct <= 0 || $depositPct > 100) {
            $depositPct = 50.0;
        }
        $depositAmount = round($totalAmount * ($depositPct / 100), 2);
        $remainingBalance = round($totalAmount - $depositAmount, 2);

        return [
            'success'                   => true,
            'room_type_id'              => (int) $roomType->id,
            'slug'                      => (string) $roomType->name,
            'display_name'              => $displayName,
            'unit_count'                => (int) ($stats->unit_count ?? $roomType->total_rooms ?? 0),
            'base_price_per_night'      => $basePricePerNight,
            'base_capacity'             => $baseCapacity,
            'max_extra_adults'          => $maxExtraAdults,
            'max_extra_children'        => $maxExtraChildren,
            'max_adults_capacity'       => $maxAdultsCapacity,
            'max_total_capacity'        => $maxTotalCapacity,
            'fits_guests'               => $fitsGuests,
            'guests'                    => $guests,
            'nights'                    => $nights,
            'has_specific_dates'        => $hasSpecificDates,
            'check_in'                  => $hasSpecificDates ? $checkIn : null,
            'check_out'                 => $hasSpecificDates ? $checkOut : null,
            'available_units'           => $availableUnits,
            'extra_adults'              => $extraAdults,
            'extra_rate_per_adult_once' => $extraRatePerAdultOnce,
            'extra_person_total'        => $extraPersonTotal,
            'room_subtotal'             => $roomSubtotal,
            'weekend_surcharge_total'   => $weekendSurchargeTotal,
            'seasonal_adjustment_total' => $seasonalAdjustmentTotal,
            'promo_code'                => $appliedPromo,
            'discount_amount'           => $discountAmount,
            'total_amount'              => $totalAmount,
            'deposit_percentage'        => $depositPct,
            'deposit_amount'            => $depositAmount,
            'remaining_balance'         => $remainingBalance,
        ];
    }
}
