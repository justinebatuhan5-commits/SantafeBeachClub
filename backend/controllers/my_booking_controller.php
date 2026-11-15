<?php

/**
 * Resolve guest portal state for my_booking.php (handles logout, discount totals,
 * QR voucher image generation, cancellation policy window, and payment hold timer).
 *
 * @return array<string, mixed>
 */
function sfbc_handle_my_booking_page(mysqli $conn): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Handle logout action
    if (isset($_GET['logout'])) {
        clear_guest_booking();
        header('Location: my_booking');
        exit;
    }

    $guest_data = get_guest_booking();
    $booking    = $guest_data['booking'] ?? null;
    $discountBooking = null;
    $verifiedPaid = 0.0;

    // Keep Senior/PWD guests inside their normal account. Load the authoritative
    // discount totals here instead of redirecting to the private payment workflow.
    if ($booking && !empty($booking['id'])) {
        $discountBooking = pm_query($conn, '
            SELECT category, state, original_centavos, total_centavos,
                   deposit_centavos, hold_until, note, token
            FROM booking_discounts
            WHERE booking_id=?
            LIMIT 1
        ', [(int)$booking['id']])->get_result()->fetch_assoc();

        $verifiedPaid = (float)(pm_query($conn, "
            SELECT COALESCE(SUM(amount), 0)
            FROM payments
            WHERE booking_id=? AND status='verified'
        ", [(int)$booking['id']])->get_result()->fetch_row()[0] ?? 0);

        if ($discountBooking) {
            $_SESSION['discount_tokens'][(int)$booking['id']] = $discountBooking['token'];
        }
    }
    $payment = $guest_data['payment'] ?? null;

    $nights = 1;
    $original_amount = 0.0;
    $total_amount = 0.0;
    $required_deposit = 0.0;
    $discount_amount = 0.0;
    $deposit_paid = 0.0;
    $remaining = 0.0;
    $checkin_fmt = '';
    $checkout_fmt = '';
    $bk_status = 'Pending';
    $status_style = [];
    $pay_status = 'pending';
    $pay_style = [];
    $checkin_url = '';
    $cancel_url = '';
    $qr_data_uri = '';
    $cancel_policy = [];
    $cancellation_deadline_hours = 0;
    $deadline_formatted = '';
    $is_cancellation_expired = false;
    $hours_left_to_cancel = 0;
    $days_left_to_cancel = 0;
    $cancel_policy_name = '';
    $cancel_window_label = '';
    $can_cancel = false;
    $is_pending_payment = false;
    $seconds_remaining = 0;
    $is_payment_expired = false;

    // If a verified session exists, compute extra display values
    if ($booking) {
        $nights = max(1, (int)($booking['nights'] ?? 1));
        if ($discountBooking) {
            $original_amount = (int)$discountBooking['original_centavos'] / 100;
            $total_amount = (int)$discountBooking['total_centavos'] / 100;
            $required_deposit = (int)$discountBooking['deposit_centavos'] / 100;
            $discount_amount = max(0, $original_amount - $total_amount);
            $deposit_paid = $verifiedPaid;
        } else {
            $original_amount = $payment ? (float)$payment['amount'] * 2 : 0;
            $total_amount = $original_amount;
            $required_deposit = $total_amount / 2;
            $discount_amount = 0.0;
            $deposit_paid = $verifiedPaid > 0 ? $verifiedPaid : ($payment ? (float)$payment['amount'] : 0);
        }
        $remaining = max(0, $total_amount - $deposit_paid);
        $checkin_fmt   = date('D, d M Y', strtotime($booking['check_in']));
        $checkout_fmt  = date('D, d M Y', strtotime($booking['check_out']));

        $status_colors = [
            'Pending Payment' => ['bg' => '#FFF3CD', 'color' => '#856404', 'icon' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'],
            'Pending'         => ['bg' => '#FFF3CD', 'color' => '#856404', 'icon' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'],
            'Confirmed'       => ['bg' => '#D1FAE5', 'color' => '#065F46', 'icon' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'],
            'Checked In'      => ['bg' => '#DBEAFE', 'color' => '#1E40AF', 'icon' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>'],
            'Checked Out'     => ['bg' => '#F3F4F6', 'color' => '#374151', 'icon' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>'],
            'Cancelled'       => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'icon' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>'],
        ];
        $bk_status    = $booking['status'] ?? 'Pending';
        $status_style = $status_colors[$bk_status] ?? $status_colors['Pending'];

        $pay_status_colors = [
            'pending'  => ['bg' => '#FFF3CD', 'color' => '#856404', 'label' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Pending Verification'],
            'verified' => ['bg' => '#D1FAE5', 'color' => '#065F46', 'label' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><polyline points="20 6 9 17 4 12"></polyline></svg> Verified'],
            'rejected' => ['bg' => '#FEE2E2', 'color' => '#991B1B', 'label' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> Rejected'],
            'refunded' => ['bg' => '#EDE9FE', 'color' => '#5B21B6', 'label' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px; margin-right:4px;"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg> Refunded'],
        ];
        $pay_status = $verifiedPaid > 0 ? 'verified' : strtolower($payment['payment_status'] ?? 'pending');
        $pay_style  = $pay_status_colors[$pay_status] ?? $pay_status_colors['pending'];

        // QR Code — reuse saved QR file if it exists, otherwise generate
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                  . '://' . $_SERVER['HTTP_HOST']
                  . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $checkin_url = $base_url . '/checkin?ref=' . urlencode($booking['booking_ref']) . '&token=' . urlencode($booking['checkin_token'] ?? '');
        $cancel_url  = $booking['cancel_url'] ?? ($base_url . '/cancel_booking?token=' . urlencode($booking['cancellation_token'] ?? ''));

        $qr_dir  = dirname(__DIR__, 2) . '/frontend/assets/qrcodes/';
        $qr_file = $qr_dir . 'qr_booking_' . $booking['id'] . '.png';
        if (!file_exists($qr_file)) {
            if (!is_dir($qr_dir)) {
                mkdir($qr_dir, 0777, true);
            }
            QRcode::png($checkin_url, $qr_file, QR_ECLEVEL_H, 6, 4);
        }
        $qr_data_uri = file_exists($qr_file)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($qr_file))
            : '';

        // Dynamic Cancellation Policy (based on nights booked)
        $cancel_policy = sf_get_cancellation_policy($booking['check_in'] ?? '', $booking['check_out'] ?? '');
        $cancellation_deadline_hours = $cancel_policy['deadline_hours'];
        $deadline_formatted = $cancel_policy['deadline_formatted'];
        $is_cancellation_expired = $cancel_policy['is_expired'];
        $hours_left_to_cancel = $cancel_policy['hours_left'];
        $days_left_to_cancel = $cancel_policy['days_left'];
        $cancel_policy_name = $cancel_policy['policy_name'];
        $cancel_window_label = $cancel_policy['window_label'];

        $can_cancel = in_array($bk_status, ['Pending Payment', 'Pending', 'Confirmed']) && !$is_cancellation_expired;

        // Payment Expiry Countdown (30 minute hold window from created_at)
        $is_pending_payment = in_array($bk_status, ['Pending Payment', 'Pending']);
        $created_timestamp  = !empty($booking['created_at']) ? strtotime($booking['created_at']) : time();
        $discount_hold_timestamp = $discountBooking && !empty($discountBooking['hold_until'])
            ? strtotime($discountBooking['hold_until'])
            : false;
        $expiry_timestamp   = $discount_hold_timestamp ?: ($created_timestamp + (30 * 60));
        $seconds_remaining  = max(0, $expiry_timestamp - time());
        $is_payment_expired = ($seconds_remaining <= 0);
    }

    $csrf_token = get_csrf_token();

    return compact(
        'guest_data',
        'booking',
        'discountBooking',
        'verifiedPaid',
        'payment',
        'nights',
        'original_amount',
        'total_amount',
        'required_deposit',
        'discount_amount',
        'deposit_paid',
        'remaining',
        'checkin_fmt',
        'checkout_fmt',
        'bk_status',
        'status_style',
        'pay_status',
        'pay_style',
        'checkin_url',
        'cancel_url',
        'qr_data_uri',
        'cancel_policy',
        'cancellation_deadline_hours',
        'deadline_formatted',
        'is_cancellation_expired',
        'hours_left_to_cancel',
        'days_left_to_cancel',
        'cancel_policy_name',
        'cancel_window_label',
        'can_cancel',
        'is_pending_payment',
        'seconds_remaining',
        'is_payment_expired',
        'csrf_token'
    );
}
