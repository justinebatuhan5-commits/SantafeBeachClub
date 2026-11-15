<?php
/**
 * Controller for frontend/contact.php
 */

function inquiry_value(string $field): string
{
    return htmlspecialchars(is_string($_POST[$field] ?? null) ? $_POST[$field] : '', ENT_QUOTES, 'UTF-8');
}

function sfbc_handle_contact_page(mysqli $conn): array
{
    header('Cache-Control: no-store');

    $message_sent = false;
    $error = '';
    $_SESSION['inquiry_tokens'] = array_filter($_SESSION['inquiry_tokens'] ?? [], static function ($issued) {
        return time() - $issued <= 7200;
    });

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inquiry'])) {
        require_csrf_token();
        $error = inquiry_validate($_POST, $_SESSION['inquiry_tokens'], time());
        if ($error === '') {
            $guest_name = trim($_POST['guest_name']);
            $guest_email = trim($_POST['guest_email']);
            $subject = trim($_POST['subject']);
            $message = trim($_POST['message']);
            unset($_SESSION['inquiry_tokens'][$_POST['inquiry_token']]);
            try {
                $token = $_POST['g-recaptcha-response'] ?? '';
                $verification = recaptcha_verify(is_string($token) ? $token : '', 'guest_inquiry', true);
                if (!$verification['success']) {
                    $error = 'Security verification failed. Please try again shortly or contact us by phone.';
                } elseif (!inquiry_reserve($conn, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $guest_email, $subject, $message)) {
                    http_response_code(429);
                    $error = 'You have sent too many messages or already sent this message. Please try again later.';
                }
            } catch (Throwable $e) {
                error_log('[Inquiry protection] ' . $e->getMessage());
                $error = 'Unable to send your message right now. Please try again later.';
            }
        }
        if ($error === '') {
            try {
                $stmt = $conn->prepare("INSERT INTO inquiries (guest_name, guest_email, subject, message) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $guest_name, $guest_email, $subject, $message);
                if ($stmt->execute()) {
                    $message_sent = true;
                } else {
                    $error = 'An error occurred while sending your message. Please try again.';
                }
                $stmt->close();
            } catch (Throwable $e) {
                error_log('[Inquiry submission] ' . $e->getMessage());
                $error = 'Unable to send your message right now. Please try again later.';
            }
        }
    }

    $inquiry_token = bin2hex(random_bytes(32));
    $_SESSION['inquiry_tokens'][$inquiry_token] = time();
    $_SESSION['inquiry_tokens'] = array_slice($_SESSION['inquiry_tokens'], -10, null, true);

    return compact('message_sent', 'error', 'inquiry_token');
}
