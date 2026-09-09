<?php
/**
 * SMTP Email Test Script — DELETE AFTER USE
 * Access this file in a browser to test Gmail SMTP.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Simple admin-only guard (must be logged in as admin)
// Remove for quick local testing if needed.
$test_to = $_GET['to'] ?? '';
$test_to = filter_var($test_to, FILTER_VALIDATE_EMAIL) ? $test_to : '';

require_once __DIR__ . '/../backend/config/env_loader.php';
load_env(__DIR__ . '/../.env');

$gmail_user = getenv('GMAIL_USER') ?: 'NOT SET';
$gmail_pass = getenv('GMAIL_APP_PASSWORD') ?: 'NOT SET';
$from_name  = getenv('MAIL_FROM_NAME') ?: 'NOT SET';

// Sanitize password for display
$pass_display = $gmail_pass !== 'NOT SET' ? substr($gmail_pass, 0, 4) . '****' . substr($gmail_pass, -4) : 'NOT SET';

echo "<!DOCTYPE html><html><head><title>Mail Test</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #0f172a; color: #e2e8f0; }
  .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
  .ok { color: #4ade80; } .err { color: #f87171; } .warn { color: #fbbf24; }
  pre { background: #0f172a; padding: 16px; border-radius: 8px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; font-size: 13px; }
  input[type=email], input[type=submit] { padding: 10px 14px; border-radius: 8px; border: 1px solid #475569; background: #0f172a; color: #fff; font-size: 14px; }
  input[type=submit] { background: #d97706; border-color: #d97706; cursor: pointer; }
</style></head><body>";

echo "<h2>🔧 Santa Fe Beach Club — SMTP Mail Test</h2>";

echo "<div class='card'><h3>Environment Variables</h3>";
echo "<p><b>GMAIL_USER:</b> " . htmlspecialchars($gmail_user) . "</p>";
echo "<p><b>GMAIL_APP_PASSWORD:</b> " . htmlspecialchars($pass_display) . " (length: " . strlen($gmail_pass) . ")</p>";
echo "<p><b>MAIL_FROM_NAME:</b> " . htmlspecialchars($from_name) . "</p>";

// Check for spaces in password
if (strpos($gmail_pass, ' ') !== false) {
    echo "<p class='warn'>⚠️ App password contains spaces. PHPMailer will strip them — this is usually OK.</p>";
}
echo "</div>";

// Check PHPMailer exists
$phpmailer_ok = file_exists(__DIR__ . '/../backend/libs/PHPMailer/src/PHPMailer.php');
echo "<div class='card'><h3>PHPMailer Library</h3>";
echo $phpmailer_ok
    ? "<p class='ok'>✅ PHPMailer found.</p>"
    : "<p class='err'>❌ PHPMailer NOT found at backend/libs/PHPMailer/src/PHPMailer.php</p>";
echo "</div>";

// Send test form
echo "<div class='card'><h3>Send Test Email</h3>
<form method='get'>
  <label>Send to: <input type='email' name='to' value='" . htmlspecialchars($test_to) . "' placeholder='your@email.com'></label>
  <input type='submit' value='Send Test Email →'>
</form></div>";

if ($test_to && $phpmailer_ok) {
    require_once __DIR__ . '/../backend/libs/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../backend/libs/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../backend/libs/PHPMailer/src/SMTP.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    $clean_pass = str_replace(' ', '', $gmail_pass);

    echo "<div class='card'><h3>📤 Sending to: " . htmlspecialchars($test_to) . "</h3>";

    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 3; // Full debug
    $mail->Debugoutput = function($str, $level) {
        echo "<pre>" . htmlspecialchars($str) . "</pre>";
    };

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmail_user;
        $mail->Password   = $clean_pass; // spaces removed
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 15;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ];

        $mail->setFrom($gmail_user, $from_name);
        $mail->addAddress($test_to);
        $mail->isHTML(true);
        $mail->Subject = 'Santa Fe Beach Club — SMTP Test Email';
        $mail->Body    = "<h2>✅ SMTP is working!</h2><p>This is a test email from Santa Fe Beach Club booking system.</p><p>Sent at: " . date('Y-m-d H:i:s') . "</p>";
        $mail->AltBody = "SMTP is working! Test from Santa Fe Beach Club sent at: " . date('Y-m-d H:i:s');

        $mail->send();
        echo "<p class='ok' style='font-size:18px;'>✅ <strong>Email sent successfully!</strong> Check your inbox.</p>";
    } catch (Exception $e) {
        echo "<p class='err'>❌ <strong>Failed:</strong> " . htmlspecialchars($mail->ErrorInfo) . "</p>";
    }
    echo "</div>";
}

echo "</body></html>";
