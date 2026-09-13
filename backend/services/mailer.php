<?php
/**
 * mailer.php
 * Sends booking-related emails via Gmail SMTP using PHPMailer.
 *
 * SETUP REQUIRED:
 * 1. This file expects PHPMailer's source files at: libs/PHPMailer/src/
 *    (PHPMailer.php, SMTP.php, Exception.php)
 * 2. Copy .env.example to .env at the project root and fill in your credentials.
 *    GMAIL_APP_PASSWORD must be a Gmail "App Password" (16 chars, no spaces),
 *    NOT your normal Gmail login password. Generate one at:
 *    https://myaccount.google.com/apppasswords
 *    (Requires 2-Step Verification to be enabled on the Gmail account.)
 */

require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---- Load Gmail credentials from .env (never hardcode secrets in source) ----
require_once __DIR__ . '/../config/env_loader.php';
if (!defined('_ENV_LOADED')) {
    load_env(__DIR__ . '/../../.env');
    define('_ENV_LOADED', true);
}

if (!defined('GMAIL_USER'))         define('GMAIL_USER',         getenv('GMAIL_USER')         ?: 'Justinebatuhan017@gmail.com');
// Strip spaces: Google shows App Passwords with spaces in their UI (e.g. "xxxx xxxx xxxx xxxx") but SMTP needs the 16-char version without spaces.
if (!defined('GMAIL_APP_PASSWORD')) define('GMAIL_APP_PASSWORD', str_replace(' ', '', getenv('GMAIL_APP_PASSWORD') ?: 'zsuqeivwkfmwxxsv'));
if (!defined('MAIL_FROM_NAME'))     define('MAIL_FROM_NAME',     getenv('MAIL_FROM_NAME')     ?: 'Santa Fe Beach Club');

/**
 * Sends a booking confirmation email to the guest.
 *
 * @param string $to_email    Guest email address
 * @param string $guest_name  Guest full name
 * @param string $booking_ref Booking reference (e.g. REF-001)
 * @param string $room_name   Accommodation name
 * @param string $check_in    Check-in date (Y-m-d)
 * @param string $check_out   Check-out date (Y-m-d)
 * @param float  $total_amount Total booking amount
 * @param string|null $cancellation_url Optional self-service cancellation link
 * @param string|null $checkin_url      Optional Check-in URL to generate QR code
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendBookingConfirmationEmail(
    ?string $to_email,
    string $guest_name,
    string $booking_ref,
    string $room_name,
    string $check_in,
    string $check_out,
    float $total_amount,
    ?string $cancellation_url = null,
    ?string $checkin_url = null
): array {
    if (empty($to_email)) {
        return ['success' => false, 'error' => 'No email address provided for this guest.'];
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8; // 8 seconds max timeout so page doesn't hang forever
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        // Recipients
        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $guest_name);

        // Generate QR code if URL provided
        $qr_html = '';
        if ($checkin_url) {
            require_once __DIR__ . '/../libs/phpqrcode/phpqrcode.php';
            // phpqrcode requires a real file path — write to temp, read bytes, delete
            $qr_tmp = tempnam(sys_get_temp_dir(), 'sfbc_qr_') . '.png';
            QRcode::png($checkin_url, $qr_tmp, QR_ECLEVEL_H, 6, 4);
            $qr_image_data = file_get_contents($qr_tmp);
            @unlink($qr_tmp);

            if ($qr_image_data) {
                $mail->addStringEmbeddedImage($qr_image_data, 'qrcode_cid', 'Checkin_QR.png', 'base64', 'image/png');
                $qr_html = "
                <div style='text-align: center; margin: 30px 0; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1;'>
                    <h3 style='margin-top: 0; color: #1e293b;'>Your Check-in Pass</h3>
                    <img src='cid:qrcode_cid' alt='QR Code' style='max-width: 200px; height: auto;' />
                    <p style='margin-bottom: 0; font-size: 13px; color: #64748b;'>Present this QR code at the front desk for a seamless check-in.</p>
                </div>";
            }
        }

        // Generate invoice URL from checkin URL
        $invoice_url = $checkin_url ? str_replace('/checkin?', '/invoice?', $checkin_url) : '';

        // Content
        $checkin_fmt  = date('D, M j, Y', strtotime($check_in));
        $checkout_fmt = date('D, M j, Y', strtotime($check_out));
        $amount_fmt   = number_format($total_amount, 2);
        
        $cancel_section = '';
        if ($cancellation_url) {
            $cancel_section = "
            <div style='margin-top:24px; padding:16px; background:#F8FAFC; border-radius:12px; border:1px solid #E2E8F0; text-align:center;'>
                <p style='margin:0 0 10px; color:#475569; font-size:13px;'>Need to make changes or cancel?</p>
                <a href='" . htmlspecialchars($cancellation_url) . "' style='color:#7C533C; font-weight:600; text-decoration:underline; font-size:13px;'>Manage your reservation securely</a>
            </div>";
        }

        $mail->isHTML(true);
        $mail->Subject = "Booking Confirmed – {$booking_ref} – Santa Fe Beach Club";
        $mail->Body    = "
            <div style='font-family: \"Plus Jakarta Sans\", Helvetica, Arial, sans-serif; max-width: 580px; margin: 0 auto; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);'>
                
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #FAF6F0 0%, #F5EBE0 100%); padding: 32px; border-bottom: 2px solid #EADBCC; text-align: center;'>
                    <h2 style='color:#5A3E2B; margin: 0 0 4px; font-size: 24px; font-weight: 800; letter-spacing: -0.5px;'>Booking Confirmed!</h2>
                    <p style='color:#84563C; margin: 0; font-size: 14px;'>Santa Fe Beach Club, Bantayan Island</p>
                </div>

                <!-- Body -->
                <div style='padding: 32px;'>
                    <p style='color:#1E293B; font-size: 15px; margin: 0 0 20px; line-height: 1.6;'>Hi <strong>" . htmlspecialchars($guest_name) . "</strong>,</p>
                    <p style='color:#475569; font-size: 14.5px; margin: 0 0 24px; line-height: 1.6;'>Great news — your reservation at Santa Fe Beach Club has been officially confirmed! We are thrilled to welcome you to the island.</p>
                    
                    <!-- Details Card -->
                    <div style='background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; padding: 20px; margin-bottom: 24px;'>
                        <h3 style='color:#0F172A; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 16px; border-bottom: 1px solid #E2E8F0; padding-bottom: 8px;'>Reservation Details</h3>
                        <table style='width: 100%; border-collapse: collapse; font-size: 14px;'>
                            <tr>
                                <td style='padding: 6px 0; color: #64748B;'>Reference</td>
                                <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #1E293B;'>" . htmlspecialchars($booking_ref) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748B;'>Accommodation</td>
                                <td style='padding: 6px 0; text-align: right; color: #1E293B; font-weight: 600;'>" . htmlspecialchars($room_name) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748B;'>Check-in</td>
                                <td style='padding: 6px 0; text-align: right; color: #1E293B;'>" . $checkin_fmt . " <span style='color:#94A3B8; font-size:12px;'>(13:30)</span></td>
                            </tr>
                            <tr>
                                <td style='padding: 6px 0; color: #64748B;'>Check-out</td>
                                <td style='padding: 6px 0; text-align: right; color: #1E293B;'>" . $checkout_fmt . " <span style='color:#94A3B8; font-size:12px;'>(11:00)</span></td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0 4px; color: #64748B; border-top: 1px dashed #CBD5E1; margin-top: 6px;'>Total Stay Cost</td>
                                <td style='padding: 10px 0 4px; text-align: right; font-weight: 800; color: #0F172A; font-size: 16px; border-top: 1px dashed #CBD5E1; margin-top: 6px;'>₱ " . $amount_fmt . "</td>
                            </tr>
                        </table>
                        " . ($invoice_url ? "<div style='text-align:center; margin-top:16px;'><a href='" . htmlspecialchars($invoice_url) . "' style='display:inline-block; padding:8px 16px; background:#7C533C; color:#FFF; text-decoration:none; font-weight:600; font-size:13px; border-radius:6px;'>View / Print Official PDF Invoice</a></div>" : "") . "
                    </div>

                    <!-- QR Pass Section -->
                    {$qr_html}

                    {$cancel_section}

                </div>

                <!-- Footer -->
                <div style='background: #1E293B; padding: 24px; text-align: center;'>
                    <p style='color: #94A3B8; font-size: 12px; margin: 0;'>Santa Fe Beach Club</p>
                    <p style='color: #64748B; font-size: 11px; margin: 4px 0 0;'>Bantayan Island, Cebu, Philippines</p>
                </div>

            </div>
        ";
        $mail->AltBody = "Hi {$guest_name}, your booking {$booking_ref} at Santa Fe Beach Club is confirmed. "
            . "Room: {$room_name}. Check-in: {$checkin_fmt}. Check-out: {$checkout_fmt}. Total: PHP {$amount_fmt}. "
            . ($invoice_url ? "View official invoice: {$invoice_url}. " : "")
            . ($cancellation_url ? "Cancel here: {$cancellation_url}." : "");

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Sends a booking cancellation email to the guest.
 */
function sendBookingCancellationEmail(
    string $to_email,
    string $guest_name,
    string $booking_ref,
    string $room_name,
    string $check_in,
    string $check_out,
    ?string $reason = null
): array {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $guest_name);

        $checkin_fmt  = date('F j, Y', strtotime($check_in));
        $checkout_fmt = date('F j, Y', strtotime($check_out));
        $reason_html  = (!empty($reason))
            ? "<li><strong>Reason Provided:</strong> " . htmlspecialchars($reason) . "</li>"
            : "";

        $mail->isHTML(true);
        $mail->Subject = "Booking Cancelled – {$booking_ref} – Santa Fe Beach Club";
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto;'>
                <div style='background:#dc2626; padding:24px 28px; border-radius:12px 12px 0 0;'>
                    <h2 style='color:#fff; margin:0; font-size:20px;'>Booking Cancellation Confirmation</h2>
                    <p style='color:rgba(255,255,255,0.85); margin:6px 0 0; font-size:13px;'>Santa Fe Beach Club</p>
                </div>
                <div style='background:#fff; padding:24px 28px; border:1px solid #f3f4f6; border-top:none; border-radius:0 0 12px 12px;'>
                    <p>Hi " . htmlspecialchars($guest_name) . ",</p>
                    <p>Your reservation at <strong>Santa Fe Beach Club</strong> has been cancelled successfully.</p>
                    <table style='width:100%; border-collapse:collapse; margin:18px 0; font-size:14px;'>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:6px 0; color:#6b7280;'>Booking Reference</td>
                            <td style='padding:6px 0; text-align:right; font-weight:700;'>" . htmlspecialchars($booking_ref) . "</td>
                        </tr>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:6px 0; color:#6b7280;'>Room / Accommodation</td>
                            <td style='padding:6px 0; text-align:right;'>" . htmlspecialchars($room_name) . "</td>
                        </tr>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:6px 0; color:#6b7280;'>Check-in</td>
                            <td style='padding:6px 0; text-align:right;'>{$checkin_fmt}</td>
                        </tr>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:6px 0; color:#6b7280;'>Check-out</td>
                            <td style='padding:6px 0; text-align:right;'>{$checkout_fmt}</td>
                        </tr>
                        " . (!empty($reason) ? "<tr><td style='padding:6px 0; color:#6b7280;'>Reason</td><td style='padding:6px 0; text-align:right; color:#dc2626; font-weight:600;'>" . htmlspecialchars($reason) . "</td></tr>" : "") . "
                    </table>
                    <p>If this was a mistake or your plans change in the future, we would love to welcome you back anytime.</p>
                    <p style='color:#999; font-size:12px; margin-top:24px;'>Santa Fe Beach Club Front Desk</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hi {$guest_name}, your booking {$booking_ref} at Santa Fe Beach Club has been cancelled. Room: {$room_name}. Check-in: {$checkin_fmt}. Check-out: {$checkout_fmt}."
            . (!empty($reason) ? " Reason: {$reason}." : "");

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Sends a payment-rejected / booking-cancelled email to the guest.
 * Called when an admin rejects a payment submission.
 *
 * @param string $to_email    Guest email address
 * @param string $guest_name  Guest full name
 * @param string $booking_ref Booking reference (e.g. REF-001)
 * @param string $room_name   Accommodation name
 * @param string $check_in    Check-in date (Y-m-d)
 * @param string $check_out   Check-out date (Y-m-d)
 * @param string $reason      Optional reason provided by admin
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendPaymentRejectedEmail(
    string $to_email,
    string $guest_name,
    string $booking_ref,
    string $room_name,
    string $check_in,
    string $check_out,
    string $reason = ''
): array {
    if (empty($to_email)) {
        return ['success' => false, 'error' => 'No email address provided for this guest.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $guest_name);

        $checkin_fmt  = date('F j, Y', strtotime($check_in));
        $checkout_fmt = date('F j, Y', strtotime($check_out));
        $reason_html  = $reason !== ''
            ? "<p><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>"
            : '';

        $mail->isHTML(true);
        $mail->Subject = "Payment Not Accepted \u2013 {$booking_ref} \u2013 Santa Fe Beach Club";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
                <div style='background:#dc2626;padding:28px 32px;border-radius:12px 12px 0 0;'>
                    <h2 style='color:#fff;margin:0;font-size:22px;'>\u26a0\ufe0f Payment Not Accepted</h2>
                    <p style='color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;'>Santa Fe Beach Club \u2014 Reservation Update</p>
                </div>
                <div style='background:#fff;padding:28px 32px;border:1px solid #f3f4f6;border-top:none;border-radius:0 0 12px 12px;'>
                    <p>Hi " . htmlspecialchars($guest_name) . ",</p>
                    <p>We regret to inform you that your payment submission for the following reservation could <strong>not be accepted</strong>, and the booking has been cancelled.</p>
                    <table style='width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;'>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:8px 0;color:#6b7280;'>Booking Reference</td>
                            <td style='padding:8px 0;text-align:right;font-weight:700;'>" . htmlspecialchars($booking_ref) . "</td>
                        </tr>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:8px 0;color:#6b7280;'>Room / Accommodation</td>
                            <td style='padding:8px 0;text-align:right;'>" . htmlspecialchars($room_name) . "</td>
                        </tr>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:8px 0;color:#6b7280;'>Check-in</td>
                            <td style='padding:8px 0;text-align:right;'>{$checkin_fmt}</td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;color:#6b7280;'>Check-out</td>
                            <td style='padding:8px 0;text-align:right;'>{$checkout_fmt}</td>
                        </tr>
                    </table>
                    {$reason_html}
                    <p>If you believe this is a mistake or would like to make a new reservation, please contact our front desk:</p>
                    <p style='margin:0;'>\ud83d\udcde <strong>Front Desk:</strong> Contact us directly</p>
                    <p style='margin:6px 0 0;'>\ud83d\udce7 <strong>Email:</strong> " . GMAIL_USER . "</p>
                    <p style='color:#9ca3af;font-size:12px;margin-top:28px;'>We apologise for the inconvenience. We hope to welcome you at Santa Fe Beach Club soon.</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hi {$guest_name}, your payment for booking {$booking_ref} at Santa Fe Beach Club was not accepted and the booking has been cancelled."
            . " Room: {$room_name}. Check-in: {$checkin_fmt}. Check-out: {$checkout_fmt}."
            . ($reason !== '' ? " Reason: {$reason}." : '')
            . " Please contact us if you have questions.";

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Sends a refund completion email to the guest.
 * Called when a refund has been processed to PayMongo.
 *
 * @param string $to_email      Guest email address
 * @param string $guest_name    Guest full name
 * @param string $booking_ref   Booking reference (e.g. REF-001)
 * @param float  $refund_amount Amount refunded
 * @param string $reason        Cancellation reason
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendRefundCompletedEmail(
    string $to_email,
    string $guest_name,
    string $booking_ref,
    float $refund_amount,
    string $reason = ''
): array {
    if (empty($to_email)) {
        return ['success' => false, 'error' => 'No email address provided for this guest.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $guest_name);

        $amount_fmt = number_format($refund_amount, 2);

        $mail->isHTML(true);
        $mail->Subject = "Refund Processed – {$booking_ref} – Santa Fe Beach Club";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
                <div style='background:linear-gradient(135deg, #10b981 0%, #059669 100%);padding:28px 32px;border-radius:12px 12px 0 0;'>
                    <h2 style='color:#fff;margin:0;font-size:22px;'>✓ Refund Processed</h2>
                    <p style='color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;'>Santa Fe Beach Club — Your money is on its way</p>
                </div>
                <div style='background:#fff;padding:28px 32px;border:1px solid #f3f4f6;border-top:none;border-radius:0 0 12px 12px;'>
                    <p>Hi " . htmlspecialchars($guest_name) . ",</p>
                    <p>Your refund has been successfully processed! The following amount has been credited back to your original payment method:</p>
                    
                    <div style='background:#ecfdf5;border-left:4px solid #10b981;padding:16px;margin:20px 0;border-radius:6px;'>
                        <p style='margin:0 0 8px;color:#047857;font-weight:600;'>Refund Amount</p>
                        <p style='margin:0;font-size:24px;font-weight:700;color:#065f46;'>₱ " . $amount_fmt . "</p>
                    </div>

                    <table style='width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;'>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:8px 0;color:#6b7280;'>Booking Reference</td>
                            <td style='padding:8px 0;text-align:right;font-weight:700;'>" . htmlspecialchars($booking_ref) . "</td>
                        </tr>
                        <tr>
                            <td style='padding:8px 0;color:#6b7280;'>Status</td>
                            <td style='padding:8px 0;text-align:right;'>
                                <span style='display:inline-block;background:#ecfdf5;color:#065f46;padding:4px 8px;border-radius:4px;font-weight:600;font-size:12px;'>Completed</span>
                            </td>
                        </tr>
                    </table>

                    <p style='color:#4b5563;font-size:13px;margin:20px 0 0;line-height:1.6;'>
                        <strong>Processing Time:</strong> Refunds typically appear in your account within 3-5 business days, depending on your bank or payment provider.
                    </p>

                    <div style='background:#fef3c7;border-left:4px solid #f59e0b;padding:16px;margin:20px 0;border-radius:6px;font-size:13px;'>
                        <p style='margin:0 0 8px;color:#92400e;'><strong>💡 Tip:</strong> If you don't see the refund within 5-7 business days, please contact your bank or payment provider first, as delays can occur on their end.</p>
                    </div>

                    <p style='color:#9ca3af;font-size:12px;margin-top:28px;'>We hope to welcome you back to Santa Fe Beach Club again in the future!</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hi {$guest_name}, your refund of PHP {$amount_fmt} for booking {$booking_ref} has been successfully processed. "
            . "The amount will be credited back to your original payment method within 3-5 business days. "
            . "Reference: {$booking_ref}. "
            . ($reason ? "Reason: {$reason}." : "")
            . " Thank you!";

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Sends a confirmation email when a refund request is submitted.
 *
 * @param string $to_email Guest email address
 * @param string $guest_name Guest full name
 * @param string $booking_ref Booking reference (e.g. REF-001)
 * @param float  $estimated_refund Estimated refund amount
 * @param string $reason Reason submitted by guest
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendRefundRequestConfirmationEmail(
    string $to_email,
    string $guest_name,
    string $booking_ref,
    float $estimated_refund,
    string $reason = ''
): array {
    if (empty($to_email)) {
        return ['success' => false, 'error' => 'No email address provided for this guest.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $guest_name);

        $amount_fmt = number_format($estimated_refund, 2);

        $mail->isHTML(true);
        $mail->Subject = "Refund Request Received – {$booking_ref} – Santa Fe Beach Club";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
                <div style='background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);padding:28px 32px;border-radius:12px 12px 0 0;'>
                    <h2 style='color:#fff;margin:0;font-size:22px;'>Refund Request Received</h2>
                    <p style='color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;'>Santa Fe Beach Club — We're reviewing your request</p>
                </div>
                <div style='background:#fff;padding:28px 32px;border:1px solid #f3f4f6;border-top:none;border-radius:0 0 12px 12px;'>
                    <p>Hi " . htmlspecialchars($guest_name) . ",</p>
                    <p>We have received your refund request for booking <strong>" . htmlspecialchars($booking_ref) . "</strong>. Our management team will review it according to our cancellation & refund policy.</p>

                    <div style='background:#fef3c7;border-left:4px solid #f59e0b;padding:16px;margin:20px 0;border-radius:6px;'>
                        <p style='margin:0 0 4px;color:#92400e;font-weight:600;'>Estimated Refund Amount</p>
                        <p style='margin:0;font-size:24px;font-weight:700;color:#78350f;'>₱ " . $amount_fmt . "</p>
                    </div>

                    <table style='width:100%;border-collapse:collapse;margin:20px 0;font-size:14px;'>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:8px 0;color:#6b7280;'>Booking Reference</td>
                            <td style='padding:8px 0;text-align:right;font-weight:700;'>" . htmlspecialchars($booking_ref) . "</td>
                        </tr>
                        <tr style='border-bottom:1px solid #f0f0f0;'>
                            <td style='padding:8px 0;color:#6b7280;'>Status</td>
                            <td style='padding:8px 0;text-align:right;'>
                                <span style='display:inline-block;background:#fef3c7;color:#92400e;padding:4px 8px;border-radius:4px;font-weight:600;font-size:12px;'>Under Review</span>
                            </td>
                        </tr>
                        " . ($reason ? "
                        <tr>
                            <td style='padding:8px 0;color:#6b7280;'>Reason</td>
                            <td style='padding:8px 0;text-align:right;color:#374151;'>" . htmlspecialchars($reason) . "</td>
                        </tr>" : "") . "
                    </table>

                    <p style='color:#4b5563;font-size:13px;line-height:1.6;'>
                        Our team typically reviews refund requests within <strong>24 to 48 hours</strong>. You will receive another notification once a decision is made.
                    </p>

                    <p style='color:#9ca3af;font-size:12px;margin-top:28px;'>Thank you for your patience.</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hi {$guest_name}, we received your refund request of PHP {$amount_fmt} for booking {$booking_ref}. "
            . "Our management team is reviewing it and will notify you within 24-48 hours. Thank you!";

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Sends an email when a refund request is approved by management.
 *
 * @param string $to_email Guest email address
 * @param string $guest_name Guest full name
 * @param string $booking_ref Booking reference (e.g. REF-001)
 * @param float  $approved_amount Approved net refund amount
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendRefundApprovedEmail(
    string $to_email,
    string $guest_name,
    string $booking_ref,
    float $approved_amount
): array {
    if (empty($to_email)) {
        return ['success' => false, 'error' => 'No email address provided for this guest.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $guest_name);

        $amount_fmt = number_format($approved_amount, 2);

        $mail->isHTML(true);
        $mail->Subject = "Refund Approved – {$booking_ref} – Santa Fe Beach Club";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
                <div style='background:linear-gradient(135deg, #10b981 0%, #059669 100%);padding:28px 32px;border-radius:12px 12px 0 0;'>
                    <h2 style='color:#fff;margin:0;font-size:22px;'>Refund Approved!</h2>
                    <p style='color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;'>Santa Fe Beach Club — Good news regarding your request</p>
                </div>
                <div style='background:#fff;padding:28px 32px;border:1px solid #f3f4f6;border-top:none;border-radius:0 0 12px 12px;'>
                    <p>Hi " . htmlspecialchars($guest_name) . ",</p>
                    <p>Great news! Your refund request for booking <strong>" . htmlspecialchars($booking_ref) . "</strong> has been approved by management.</p>

                    <div style='background:#ecfdf5;border-left:4px solid #10b981;padding:16px;margin:20px 0;border-radius:6px;'>
                        <p style='margin:0 0 4px;color:#047857;font-weight:600;'>Approved Refund</p>
                        <p style='margin:0;font-size:24px;font-weight:700;color:#065f46;'>₱ " . $amount_fmt . "</p>
                    </div>

                    <p style='color:#4b5563;font-size:13px;line-height:1.6;'>
                        The payment will be refunded to your original payment method. You will receive a final confirmation once the transaction is complete.
                    </p>

                    <p style='color:#9ca3af;font-size:12px;margin-top:28px;'>Thank you for choosing Santa Fe Beach Club.</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hi {$guest_name}, your refund request for booking {$booking_ref} has been approved for PHP {$amount_fmt}. "
            . "It will be processed to your original payment method shortly.";

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Sends an email when a refund request is rejected.
 *
 * @param string $to_email Guest email address
 * @param string $guest_name Guest full name
 * @param string $booking_ref Booking reference (e.g. REF-001)
 * @param string $reason Rejection reason
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendRefundRejectedEmail(
    string $to_email,
    string $guest_name,
    string $booking_ref,
    string $reason = ''
): array {
    if (empty($to_email)) {
        return ['success' => false, 'error' => 'No email address provided for this guest.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $guest_name);

        $mail->isHTML(true);
        $mail->Subject = "Update on Refund Request – {$booking_ref} – Santa Fe Beach Club";
        $mail->Body    = "
            <div style='font-family:Arial,sans-serif;max-width:520px;margin:0 auto;'>
                <div style='background:linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);padding:28px 32px;border-radius:12px 12px 0 0;'>
                    <h2 style='color:#fff;margin:0;font-size:22px;'>Refund Request Status</h2>
                    <p style='color:rgba(255,255,255,0.85);margin:8px 0 0;font-size:14px;'>Santa Fe Beach Club — Booking {$booking_ref}</p>
                </div>
                <div style='background:#fff;padding:28px 32px;border:1px solid #f3f4f6;border-top:none;border-radius:0 0 12px 12px;'>
                    <p>Hi " . htmlspecialchars($guest_name) . ",</p>
                    <p>We are writing to inform you that your refund request for booking <strong>" . htmlspecialchars($booking_ref) . "</strong> could not be approved at this time.</p>

                    " . ($reason ? "
                    <div style='background:#fef2f2;border-left:4px solid #ef4444;padding:16px;margin:20px 0;border-radius:6px;'>
                        <p style='margin:0 0 4px;color:#991b1b;font-weight:600;'>Reason for Decision</p>
                        <p style='margin:0;color:#7f1d1d;font-size:14px;'>" . htmlspecialchars($reason) . "</p>
                    </div>" : "") . "

                    <p style='color:#4b5563;font-size:13px;line-height:1.6;'>
                        If you believe this was in error or have further questions, please contact our Front Desk directly.
                    </p>

                    <p style='color:#9ca3af;font-size:12px;margin-top:28px;'>Thank you for your understanding.</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hi {$guest_name}, your refund request for booking {$booking_ref} could not be approved. "
            . ($reason ? "Reason: {$reason}. " : "")
            . "Please contact our Front Desk if you have questions.";

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Sends a Pre-Arrival Check-in Reminder email to the guest.
 * Includes booking details, check-in instructions, and a link to
 * cancel or send an explanation if the guest cannot attend.
 *
 * @param string $to_email        Guest email address
 * @param string $guest_name      Guest full name
 * @param string $booking_ref     Booking reference (e.g. REF-001)
 * @param string $room_name       Accommodation name
 * @param string $check_in        Check-in date (Y-m-d)
 * @param string $check_out       Check-out date (Y-m-d)
 * @param float  $balance_due     Remaining balance, if any
 * @param string $explanation_url Link to cancel / send explanation form
 * @param string $checkin_url     Link to the Check-in QR pass page
 * @return array ['success' => bool, 'error' => string|null]
 */
function sendPreArrivalReminderEmail(
    ?string $to_email,
    string $guest_name,
    string $booking_ref,
    string $room_name,
    string $check_in,
    string $check_out,
    float $balance_due,
    string $explanation_url,
    string $checkin_url
): array {
    if (empty($to_email)) {
        return ['success' => false, 'error' => 'No email address provided for this guest.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 8;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];

        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $guest_name);

        // Format dates nicely
        $check_in_fmt = date('F j, Y (l)', strtotime($check_in));
        $check_out_fmt = date('F j, Y (l)', strtotime($check_out));
        $nights = max(1, (int)((strtotime($check_out) - strtotime($check_in)) / 86400));

        $safe_name = htmlspecialchars($guest_name);
        $safe_ref = htmlspecialchars($booking_ref);
        $safe_room = htmlspecialchars($room_name);

        // Balance section
        $balance_section = '';
        if ($balance_due > 0) {
            $balance_section = "
                <div style='background:#fef3c7;border-left:4px solid #f59e0b;padding:14px 18px;margin:18px 0;border-radius:6px;'>
                    <p style='margin:0;color:#92400e;font-weight:600;font-size:14px;'>💰 Balance Due: ₱" . number_format($balance_due, 2) . "</p>
                    <p style='margin:4px 0 0;color:#a16207;font-size:13px;'>Please settle the remaining balance upon check-in at the Front Desk.</p>
                </div>";
        }

        $mail->isHTML(true);
        $mail->Subject = "🏖️ Your Check-in is Coming Up! – {$booking_ref} – Santa Fe Beach Club";
        $mail->Body = "
            <div style='font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;background:#f9fafb;border-radius:14px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);'>
                <!-- Header -->
                <div style='background:linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%);padding:32px;text-align:center;'>
                    <p style='font-size:40px;margin:0;'>🏖️</p>
                    <h2 style='color:#fff;margin:10px 0 4px;font-size:22px;'>Your Check-in is Almost Here!</h2>
                    <p style='color:rgba(255,255,255,0.9);margin:0;font-size:14px;'>Santa Fe Beach Club — Booking {$safe_ref}</p>
                </div>

                <!-- Body -->
                <div style='padding:28px 32px;background:#fff;'>
                    <p style='color:#1f2937;font-size:15px;line-height:1.6;'>
                        Hi <strong>{$safe_name}</strong>,
                    </p>
                    <p style='color:#4b5563;font-size:14px;line-height:1.6;'>
                        We're excited to welcome you! This is a friendly reminder that your stay at <strong>Santa Fe Beach Club</strong> is coming up. Here are your booking details:
                    </p>

                    <!-- Booking Details Card -->
                    <div style='background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:20px;margin:20px 0;'>
                        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#374151;'>
                            <tr>
                                <td style='padding:6px 0;font-weight:600;width:130px;color:#0369a1;'>📋 Reference</td>
                                <td style='padding:6px 0;'>{$safe_ref}</td>
                            </tr>
                            <tr>
                                <td style='padding:6px 0;font-weight:600;color:#0369a1;'>🏨 Accommodation</td>
                                <td style='padding:6px 0;'>{$safe_room}</td>
                            </tr>
                            <tr>
                                <td style='padding:6px 0;font-weight:600;color:#0369a1;'>📅 Check-in</td>
                                <td style='padding:6px 0;'>{$check_in_fmt} — <em>2:00 PM</em></td>
                            </tr>
                            <tr>
                                <td style='padding:6px 0;font-weight:600;color:#0369a1;'>📅 Check-out</td>
                                <td style='padding:6px 0;'>{$check_out_fmt} — <em>12:00 PM</em></td>
                            </tr>
                            <tr>
                                <td style='padding:6px 0;font-weight:600;color:#0369a1;'>🌙 Duration</td>
                                <td style='padding:6px 0;'>{$nights} night" . ($nights > 1 ? 's' : '') . "</td>
                            </tr>
                        </table>
                    </div>

                    {$balance_section}

                    <!-- CTA Buttons -->
                    <div style='text-align:center;margin:28px 0 12px;'>
                        <a href='{$checkin_url}' style='display:inline-block;background:linear-gradient(135deg,#0ea5e9,#0369a1);color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-weight:700;font-size:15px;box-shadow:0 4px 14px rgba(14,165,233,0.35);'>
                            ✅ View My Check-in Pass
                        </a>
                    </div>

                    <p style='text-align:center;color:#6b7280;font-size:13px;margin:16px 0 6px;'>
                        Show your check-in pass or QR code at the Front Desk for quick check-in.
                    </p>

                    <!-- Divider -->
                    <hr style='border:none;border-top:1px solid #e5e7eb;margin:24px 0;' />

                    <!-- Can't make it section -->
                    <div style='background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:20px;text-align:center;'>
                        <p style='margin:0 0 8px;color:#991b1b;font-weight:600;font-size:15px;'>
                            ❌ Can't make it?
                        </p>
                        <p style='margin:0 0 16px;color:#7f1d1d;font-size:13px;line-height:1.5;'>
                            If you won't be able to come on your scheduled date, please let us know as soon as possible. You can request a reschedule, cancellation, or send us an explanation.
                        </p>
                        <a href='{$explanation_url}' style='display:inline-block;background:linear-gradient(135deg,#ef4444,#b91c1c);color:#fff;padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;box-shadow:0 4px 14px rgba(239,68,68,0.3);'>
                            📝 Send Explanation / Cancel Booking
                        </a>
                    </div>

                    <!-- Footer -->
                    <div style='margin-top:28px;text-align:center;'>
                        <p style='color:#9ca3af;font-size:12px;line-height:1.5;margin:0;'>
                            This is an automated reminder from Santa Fe Beach Club.<br>
                            If you have questions, please contact our Front Desk.<br>
                            📞 Phone: (032) 401-xxxx &nbsp;|&nbsp; 📧 Email: info@santafebeachclub.com
                        </p>
                    </div>
                </div>
            </div>
        ";

        $mail->AltBody = "Hi {$guest_name}, your check-in at Santa Fe Beach Club is coming up on {$check_in}! "
            . "Booking: {$booking_ref}, Room: {$room_name}, Check-out: {$check_out}. "
            . ($balance_due > 0 ? "Balance due: P" . number_format($balance_due, 2) . ". " : "")
            . "View your check-in pass: {$checkin_url} . "
            . "Can't make it? Send an explanation: {$explanation_url}";

        $mail->send();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}
?>
