<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/src/SMTP.php';

if (file_exists(__DIR__ . '/../config/env_loader.php') && !defined('_ENV_LOADED')) {
    require_once __DIR__ . '/../config/env_loader.php';
    load_env(__DIR__ . '/../../.env');
    define('_ENV_LOADED', true);
}

if (!function_exists('sf_mail_env')) {
    /**
     * Resolve a mail configuration value from constant or environment variables.
     */
    function sf_mail_env(string $key, string $default = ''): string
    {
        if (defined($key)) {
            $constVal = constant($key);
            if (is_string($constVal) && $constVal !== '') {
                return $constVal;
            }
        }
        foreach ([getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }
        return $default;
    }
}

if (!function_exists('sf_mail_log')) {
    /**
     * Write non-PII mail transport diagnostics to error_log and container stderr
     * so cloud hosts (e.g. Render Logs) surface delivery failures even when
     * app error_log() is routed to a local file.
     */
    function sf_mail_log(string $message): void
    {
        @error_log($message);
        if (PHP_SAPI !== 'cli') {
            @file_put_contents('php://stderr', '[' . gmdate('Y-m-d\TH:i:s\Z') . '] ' . $message . PHP_EOL);
        }
    }
}

if (!class_exists('SantafeMailer')) {
    /**
     * PHPMailer subclass that dispatches over an HTTPS webhook relay when
     * MAIL_WEBHOOK_URL is configured (e.g. on hosts that block SMTP ports 25/465/587),
     * falling back to standard PHPMailer SMTP otherwise.
     */
    class SantafeMailer extends \PHPMailer\PHPMailer\PHPMailer
    {
        public function send(): bool
        {
            $webhookUrl = trim(sf_mail_env('MAIL_WEBHOOK_URL'), " \t\n\r\0\x0B\"'");
            if ($webhookUrl !== '') {
                return $this->sendViaWebhook($webhookUrl);
            }
            sf_mail_log('[MailWebhook] MAIL_WEBHOOK_URL is not set; attempting SMTP ' . $this->Host . ':' . $this->Port);
            return parent::send();
        }

        private function sendViaWebhook(string $webhookUrl): bool
        {
            try {
                if (strtolower((string) parse_url($webhookUrl, PHP_URL_SCHEME)) !== 'https') {
                    throw new \PHPMailer\PHPMailer\Exception('MAIL_WEBHOOK_URL must use https://.');
                }

                $to = $this->getToAddresses();
                if (empty($to)) {
                    throw new \PHPMailer\PHPMailer\Exception('No recipient email address provided.');
                }

                $mapAddresses = static function (array $list): array {
                    $out = [];
                    foreach ($list as $addr) {
                        $email = trim((string) ($addr[0] ?? ''));
                        if ($email !== '') {
                            $out[] = ['email' => $email, 'name' => trim((string) ($addr[1] ?? ''))];
                        }
                    }
                    return $out;
                };

                $inlineImages = [];
                $attachments = [];
                foreach ($this->getAttachments() as $att) {
                    $isString = !empty($att[5]);
                    $rawBytes = '';
                    if ($isString) {
                        $rawBytes = (string) ($att[0] ?? '');
                    } elseif (!empty($att[0]) && is_file((string) $att[0])) {
                        $read = @file_get_contents((string) $att[0]);
                        $rawBytes = $read === false ? '' : $read;
                    }
                    if ($rawBytes === '') {
                        continue;
                    }

                    $entry = [
                        'name'     => (string) ($att[2] ?: ($att[1] ?: 'attachment')),
                        'mimeType' => (string) ($att[4] ?: 'application/octet-stream'),
                        'base64'   => base64_encode($rawBytes),
                    ];
                    if (($att[6] ?? '') === 'inline' && !empty($att[7])) {
                        $entry['cid'] = (string) $att[7];
                        $inlineImages[] = $entry;
                    } else {
                        $attachments[] = $entry;
                    }
                }

                $payload = [
                    'secret'       => trim(sf_mail_env('MAIL_WEBHOOK_SECRET')),
                    'to'           => $mapAddresses($to),
                    'cc'           => $mapAddresses($this->getCcAddresses()),
                    'bcc'          => $mapAddresses($this->getBccAddresses()),
                    'replyTo'      => $mapAddresses($this->getReplyToAddresses()),
                    'fromName'     => (string) ($this->FromName ?: sf_mail_env('MAIL_FROM_NAME', 'Santa Fe Beach Club')),
                    'subject'      => (string) $this->Subject,
                    'htmlBody'     => (string) $this->Body,
                    'textBody'     => (string) ($this->AltBody ?: trim(strip_tags((string) $this->Body))),
                    'inlineImages' => $inlineImages,
                    'attachments'  => $attachments,
                ];

                $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
                if ($jsonBody === false) {
                    throw new \PHPMailer\PHPMailer\Exception('Failed to encode mail webhook payload.');
                }

                $timeoutSec = max(20, (int) $this->Timeout ?: 20);
                $rawResp = false;
                $httpCode = 0;

                if (function_exists('curl_init')) {
                    $ch = curl_init($webhookUrl);
                    curl_setopt_array($ch, [
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => $jsonBody,
                        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_MAXREDIRS      => 5,
                        CURLOPT_TIMEOUT        => $timeoutSec,
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                    ]);
                    $rawResp = curl_exec($ch);
                    $curlErr = curl_error($ch);
                    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($rawResp === false) {
                        throw new \PHPMailer\PHPMailer\Exception('Mail webhook request failed: ' . $curlErr);
                    }
                } else {
                    $ctx = stream_context_create([
                        'http' => [
                            'method'          => 'POST',
                            'header'          => "Content-Type: application/json\r\nContent-Length: " . strlen($jsonBody) . "\r\n",
                            'content'         => $jsonBody,
                            'timeout'         => $timeoutSec,
                            'follow_location' => 1,
                            'max_redirects'   => 5,
                            'ignore_errors'   => true,
                        ],
                        'ssl' => [
                            'verify_peer'      => true,
                            'verify_peer_name' => true,
                        ],
                    ]);
                    $rawResp = @file_get_contents($webhookUrl, false, $ctx);
                    if (isset($http_response_header) && is_array($http_response_header)) {
                        foreach ($http_response_header as $hdr) {
                            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $hdr, $m)) {
                                $httpCode = (int) $m[1];
                            }
                        }
                    }
                    if ($rawResp === false) {
                        throw new \PHPMailer\PHPMailer\Exception('Mail webhook stream request failed.');
                    }
                }

                $decoded = json_decode((string) $rawResp, true);
                $ok = is_array($decoded) && (($decoded['ok'] ?? $decoded['success'] ?? false) === true);
                if ($httpCode >= 400 || !$ok) {
                    $detail = is_array($decoded) && !empty($decoded['error'])
                        ? (string) $decoded['error']
                        : 'HTTP ' . $httpCode . ' ' . substr(trim(strip_tags((string) $rawResp)), 0, 140);
                    throw new \PHPMailer\PHPMailer\Exception('Mail webhook rejected delivery: ' . $detail);
                }

                sf_mail_log('[MailWebhook] Delivery accepted via HTTPS relay (HTTP ' . $httpCode . ').');
                return true;
            } catch (\PHPMailer\PHPMailer\Exception $exc) {
                $this->setError($exc->getMessage());
                sf_mail_log('[MailWebhook] ' . $exc->getMessage());
                if ($this->exceptions) {
                    throw $exc;
                }
                return false;
            }
        }
    }
}

if (!function_exists('sf_create_mailer')) {
    /**
     * Build a preconfigured SantafeMailer instance for SMTP or HTTPS relay delivery.
     */
    function sf_create_mailer(int $timeout = 8, ?string $fromName = null, ?string $fromEmail = null): SantafeMailer
    {
        $resolvedUser = $fromEmail ?: sf_mail_env('GMAIL_USER', 'Justinebatuhan017@gmail.com');
        $resolvedPass = str_replace(' ', '', sf_mail_env('GMAIL_APP_PASSWORD'));
        $resolvedName = $fromName ?: sf_mail_env('MAIL_FROM_NAME', 'Santa Fe Beach Club');

        $mail = new SantafeMailer(true);
        $mail->CharSet     = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->Encoding    = \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64;
        $mail->isSMTP();
        $mail->Host        = 'smtp.gmail.com';
        $mail->SMTPAuth    = true;
        $mail->Username    = $resolvedUser;
        $mail->Password    = $resolvedPass;
        $mail->SMTPSecure  = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = 587;
        $mail->Timeout     = $timeout;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        $mail->setFrom($resolvedUser, $resolvedName);
        return $mail;
    }
}
