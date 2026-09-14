<?php
require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/helpers/room_status_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

const CHATBOT_REQUIRED_FIELDS = ['service', 'date', 'time', 'name'];

function chatbot_reply(string $reply, array $extra = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge(['reply' => $reply], $extra));
    exit;
}

function chatbot_init_session(): void
{
    if (!isset($_SESSION['chatbot']) || !is_array($_SESSION['chatbot'])) {
        $_SESSION['chatbot'] = [
            'current_step' => 'INTENT',
            'slots' => [
                'service' => '',
                'date' => '',
                'time' => '',
                'name' => '',
            ],
            'finder' => [],
        ];
    }
}

function chatbot_reset_session(): void
{
    $_SESSION['chatbot'] = [
        'current_step' => 'INTENT',
        'slots' => [
            'service' => '',
            'date' => '',
            'time' => '',
            'name' => '',
        ],
        'finder' => [],
    ];
}

function chatbot_valid_date(string $value): bool
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        return false;
    }
    return $value >= date('Y-m-d');
}

function chatbot_valid_time(string $value): bool
{
    if (preg_match('/^\d{2}:\d{2}$/', $value) !== 1) {
        return false;
    }
    $dt = DateTime::createFromFormat('H:i', $value);
    return $dt && $dt->format('H:i') === $value;
}

function chatbot_service_label(string $service): string
{
    $clean = trim($service);
    if ($clean === '') {
        return '';
    }

    $clean = str_replace(['-', '_'], ' ', $clean);
    $clean = preg_replace('/\s+/', ' ', $clean);
    return ucwords(strtolower($clean));
}

function chatbot_booking_conflict(mysqli $conn, string $service, string $date, string $time): bool
{
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM bookings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    $hasLegacy = in_array('service', $columns, true)
        && in_array('date', $columns, true)
        && in_array('time', $columns, true);

    if ($hasLegacy) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE service = ? AND date = ? AND time = ?");
        $stmt->bind_param("sss", $service, $date, $time);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        return $count > 0;
    }

    $hasProjectColumns = in_array('accommodation_name', $columns, true)
        && in_array('check_in', $columns, true)
        && in_array('eta', $columns, true);

    if ($hasProjectColumns) {
        if (in_array('status', $columns, true)) {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE accommodation_name = ? AND check_in = ? AND eta = ? AND status <> 'Cancelled'");
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE accommodation_name = ? AND check_in = ? AND eta = ?");
        }
        $serviceLabel = chatbot_service_label($service);
        $stmt->bind_param("sss", $serviceLabel, $date, $time);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        return $count > 0;
    }

    return false;
}

function chatbot_insert_booking(mysqli $conn, array $slots): int
{
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM bookings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    $hasLegacy = in_array('service', $columns, true)
        && in_array('date', $columns, true)
        && in_array('time', $columns, true)
        && in_array('name', $columns, true);

    if ($hasLegacy) {
        if (in_array('contact', $columns, true)) {
            $contact = '';
            $stmt = $conn->prepare("INSERT INTO bookings (`service`, `date`, `time`, `name`, `contact`) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $slots['service'], $slots['date'], $slots['time'], $slots['name'], $contact);
        } else {
            $stmt = $conn->prepare("INSERT INTO bookings (`service`, `date`, `time`, `name`) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $slots['service'], $slots['date'], $slots['time'], $slots['name']);
        }
        $stmt->execute();
        $bookingId = (int)$stmt->insert_id;
        $stmt->close();
        return $bookingId;
    }

    $serviceLabel = chatbot_service_label($slots['service']);
    $checkOut = date('Y-m-d', strtotime($slots['date'] . ' +1 day'));

    // Auto-detect guest tier by checking prior bookings with same name
    $guestPhone = $slots['contact'] ?? '';
    $guestName  = $slots['name'] ?? '';
    $prevStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM bookings WHERE guest_name = ? AND status NOT IN ('Cancelled','Pending Payment')");
    $prevStmt->bind_param("s", $guestName);
    $prevStmt->execute();
    $prevCount = (int)$prevStmt->get_result()->fetch_assoc()['cnt'];
    $prevStmt->close();
    if ($prevCount === 0)     $guestType = 'First Visit';
    elseif ($prevCount === 1) $guestType = 'Returning Guest';
    else                      $guestType = 'VIP Member';

    $stmt = $conn->prepare("
        INSERT INTO bookings
        (guest_name, guest_type, check_in, check_out, guests_count, accommodation_name, eta, payment_method, guest_phone)
        VALUES (?, ?, ?, ?, 1, ?, ?, 'Pay at Check-in', '')
    ");
    $stmt->bind_param("ssssss", $slots['name'], $guestType, $slots['date'], $checkOut, $serviceLabel, $slots['time']);
    $stmt->execute();
    $bookingId = (int)$stmt->insert_id;
    $stmt->close();

    return $bookingId;
}

function chatbot_parse_json_from_text(string $text): ?array
{
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false || $end <= $start) {
        return null;
    }

    $json = substr($text, $start, $end - $start + 1);
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return null;
    }
    return $decoded;
}

function chatbot_extract_slots_locally(mysqli $conn, string $message): array
{
    $clean = ['service' => '', 'date' => '', 'time' => '', 'name' => ''];
    $normalized = trim($message);
    $lower = strtolower($normalized);

    if (preg_match('/\b(20\d{2}-\d{2}-\d{2})\b/', $normalized, $m) === 1) {
        $clean['date'] = $m[1];
    }
    if (preg_match('/\b([01]\d|2[0-3]):([0-5]\d)\b/', $normalized, $m) === 1) {
        $clean['time'] = $m[0];
    }

    $serviceCandidates = [];
    $serviceResult = $conn->query("SELECT name FROM room_types ORDER BY name ASC");
    if ($serviceResult) {
        while ($row = $serviceResult->fetch_assoc()) {
            $serviceCandidates[] = (string)$row['name'];
        }
    }
    foreach (['beachview_duplex', 'seaview_duplex', 'beach_villa', 'standard_king', 'standard_room'] as $fallbackService) {
        if (!in_array($fallbackService, $serviceCandidates, true)) {
            $serviceCandidates[] = $fallbackService;
        }
    }

    foreach ($serviceCandidates as $service) {
        $slug = strtolower(str_replace('_', ' ', $service));
        if (strpos($lower, $slug) !== false) {
            $clean['service'] = $service;
            break;
        }
    }

    if (preg_match('/(?:name\s*(?:is|:)?\s*)([a-z][a-z .\'-]{1,80})/i', $normalized, $m) === 1) {
        $clean['name'] = trim($m[1]);
    } elseif (preg_match('/^(?:i am|im|this is)\s+([a-z][a-z .\'-]{1,80})$/i', $normalized, $m) === 1) {
        $clean['name'] = trim($m[1]);
    } elseif (
        preg_match('/^[a-z][a-z .\'-]{2,80}$/i', $normalized) === 1 &&
        $clean['date'] === '' &&
        $clean['time'] === '' &&
        $clean['service'] === ''
    ) {
        $clean['name'] = $normalized;
    }

    return $clean;
}

function chatbot_extract_slots_with_llm(mysqli $conn, string $message, array $currentSlots): array
{
    $apiKey = getenv('LLM_API_KEY');
    if (!$apiKey) {
        return ['ok' => true, 'slots' => chatbot_extract_slots_locally($conn, $message), 'source' => 'fallback'];
    }

    $apiUrl = getenv('LLM_API_URL') ?: 'https://api.openai.com/v1/chat/completions';
    $model = getenv('LLM_MODEL') ?: 'gpt-4o-mini';

    $systemPrompt = "Extract booking fields from user text.\n"
        . "Return JSON only with keys: service, date, time, name.\n"
        . "Rules:\n"
        . "- Use null for unknown fields.\n"
        . "- date must be YYYY-MM-DD when known.\n"
        . "- time must be HH:MM 24-hour when known.\n"
        . "- Do not include extra keys.";

    $userPrompt = "Current slots JSON:\n" . json_encode($currentSlots, JSON_UNESCAPED_SLASHES) . "\n\n"
        . "User message:\n" . $message . "\n\n"
        . "Return JSON only.";

    $body = json_encode([
        'model' => $model,
        'temperature' => 0,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ],
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => 'LLM request failed: ' . $err];
    }

    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($statusCode < 200 || $statusCode >= 300) {
        return ['ok' => false, 'error' => 'LLM request returned HTTP ' . $statusCode . '.'];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Invalid JSON from LLM API.'];
    }

    $content = null;
    if (isset($decoded['choices'][0]['message']['content']) && is_string($decoded['choices'][0]['message']['content'])) {
        $content = $decoded['choices'][0]['message']['content'];
    } elseif (
        isset($decoded['service']) ||
        isset($decoded['date']) ||
        isset($decoded['time']) ||
        isset($decoded['name'])
    ) {
        $content = json_encode($decoded);
    }

    if (!is_string($content) || trim($content) === '') {
        return ['ok' => false, 'error' => 'LLM response did not contain extractable content.'];
    }

    $slots = chatbot_parse_json_from_text($content);
    if (!is_array($slots)) {
        return ['ok' => false, 'error' => 'Could not parse extracted JSON from LLM response.'];
    }

    $clean = ['service' => '', 'date' => '', 'time' => '', 'name' => ''];
    foreach ($clean as $key => $_) {
        if (isset($slots[$key]) && $slots[$key] !== null) {
            $clean[$key] = trim((string)$slots[$key]);
        }
    }

    return ['ok' => true, 'slots' => $clean, 'source' => 'llm'];
}

function chatbot_missing_fields(array $slots): array
{
    $missing = [];
    foreach (CHATBOT_REQUIRED_FIELDS as $field) {
        if (!isset($slots[$field]) || trim((string)$slots[$field]) === '') {
            $missing[] = $field;
        }
    }
    return $missing;
}

function chatbot_missing_prompt(array $missing): string
{
    if (empty($missing)) {
        return '';
    }

    $labels = [
        'service' => 'service',
        'date' => 'date (YYYY-MM-DD)',
        'time' => 'time (HH:MM)',
        'name' => 'full name',
    ];

    $parts = [];
    foreach ($missing as $field) {
        $parts[] = $labels[$field] ?? $field;
    }

    return 'Please provide your ' . implode(', ', $parts) . '.';
}

function chatbot_quick_menu_start(): array
{
    return ['Find a room', 'Book a room', 'Ask a question'];
}

function chatbot_valid_stay_dates(string $checkIn, string $checkOut): bool
{
    $in = DateTimeImmutable::createFromFormat('!Y-m-d', $checkIn);
    $out = DateTimeImmutable::createFromFormat('!Y-m-d', $checkOut);
    return $in !== false && $out !== false && $in->format('Y-m-d') === $checkIn
        && $out->format('Y-m-d') === $checkOut && $in >= new DateTimeImmutable('today') && $out > $in;
}

function chatbot_budget_from_message(string $message): ?float
{
    $clean = preg_replace('/[^0-9.]/', '', $message);
    if ($clean === '' || !is_numeric($clean)) {
        return null;
    }
    $budget = (float)$clean;
    return $budget > 0 && $budget <= 1000000 ? $budget : null;
}

function chatbot_room_finder_results(mysqli $conn, string $checkIn, string $checkOut, int $guests, ?float $budget): array
{
    $sql = "SELECT r.type, MAX(r.capacity) AS capacity, COUNT(r.id) AS available_rooms
            FROM rooms r
            WHERE LOWER(COALESCE(r.status, '')) <> 'maintenance'
              AND NOT EXISTS (
                  SELECT 1 FROM bookings b
                  WHERE b.room_id = r.id
                    AND LOWER(COALESCE(b.status, '')) IN ('confirmed', 'checked in', 'pending', 'pending payment')
                    AND b.check_in < ? AND b.check_out > ?
              )
            GROUP BY r.type
            HAVING MAX(r.capacity) >= ?
            ORDER BY r.type ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ssi', $checkOut, $checkIn, $guests);
    $stmt->execute();
    $result = $stmt->get_result();
    $matches = [];

    while ($result && ($room = $result->fetch_assoc())) {
        $type = (string)$room['type'];
        $pricing = calculateStayPricing($conn, $type, $checkIn, $checkOut, null, $guests);
        if ($budget !== null && $pricing['total_amount'] > $budget) {
            continue;
        }
        $matches[] = [
            'type' => $type,
            'label' => chatbot_service_label($type),
            'capacity' => (int)$room['capacity'],
            'available_rooms' => (int)$room['available_rooms'],
            'nights' => (int)$pricing['nights'],
            'total' => (float)$pricing['total_amount'],
        ];
    }
    $stmt->close();

    usort($matches, static function (array $a, array $b): int {
        return $a['total'] <=> $b['total'];
    });
    return $matches;
}

function chatbot_room_finder_reply(array $finder, array $matches): string
{
    $nights = (int)$matches[0]['nights'];
    $lines = ['I found these rooms for ' . $finder['guests'] . ' guest' . ($finder['guests'] === 1 ? '' : 's')
        . ', ' . $nights . ' night' . ($nights === 1 ? '' : 's') . ':'];
    foreach ($matches as $match) {
        $lines[] = '• ' . $match['label'] . ' — fits up to ' . $match['capacity'] . ', ₱'
            . number_format($match['total'], 2) . ' total (' . $match['available_rooms'] . ' available)';
    }
    $lines[] = "\nChoose a room below to continue to the secure booking page.";
    return implode("\n", $lines);
}

function chatbot_detect_intent(string $message): string
{
    $text = strtolower($message);
    $bookingKeywords = ['book', 'reserve'];
    foreach ($bookingKeywords as $kw) {
        if (strpos($text, $kw) !== false) {
            return 'booking';
        }
    }

    $faqKeywords = ['available', 'room', 'price', 'how much'];
    foreach ($faqKeywords as $kw) {
        if (strpos($text, $kw) !== false) {
            return 'faq';
        }
    }

    return 'unknown';
}

function chatbot_step_from_slots(array $slots): string
{
    if (trim((string)$slots['service']) === '') {
        return 'ASK_SERVICE';
    }
    if (trim((string)$slots['date']) === '') {
        return 'ASK_DATE';
    }
    if (trim((string)$slots['time']) === '') {
        return 'ASK_TIME';
    }
    if (trim((string)$slots['name']) === '') {
        return 'ASK_NAME';
    }
    return 'CONFIRM';
}

function chatbot_step_prompt(string $step): string
{
    switch ($step) {
        case 'ASK_SERVICE':
            return 'What room/service would you like to book?';
        case 'ASK_DATE':
            return 'Please provide your date in YYYY-MM-DD.';
        case 'ASK_TIME':
            return 'Please provide your preferred time in HH:MM.';
        case 'ASK_NAME':
            return 'Please provide your full name.';
        default:
            return 'Please provide the missing booking details.';
    }
}

function chatbot_is_booking_step(string $step): bool
{
    return in_array($step, ['ASK_SERVICE', 'ASK_DATE', 'ASK_TIME', 'ASK_NAME'], true);
}

function chatbot_get_setting(mysqli $conn, string $key, string $fallback): string
{
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    if (!$stmt) {
        return $fallback;
    }
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $value = $fallback;
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $value = (string)$row['setting_value'];
    }
    $stmt->close();
    return $value;
}

function chatbot_available_rooms_summary(mysqli $conn): string
{
    $columns = [];
    $colResult = $conn->query("SHOW COLUMNS FROM rooms");
    if ($colResult) {
        while ($row = $colResult->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    $hasRequestedSchema = in_array('room_type', $columns, true)
        && in_array('price', $columns, true)
        && in_array('status', $columns, true);

    if ($hasRequestedSchema) {
        $sql = "SELECT id, room_type, price, status FROM rooms WHERE status = 'available' ORDER BY room_type ASC, price ASC";
    } else {
        $sql = "SELECT id, type AS room_type, price_per_night AS price, status FROM rooms WHERE status IN ('available', 'ready') ORDER BY type ASC, price_per_night ASC";
    }

    $result = $conn->query($sql);
    if (!$result || $result->num_rows === 0) {
        return 'Currently, no rooms are available right now. Please try other dates or check back later.';
    }

    $lines = ['Here are the rooms currently available:'];
    while ($row = $result->fetch_assoc()) {
        $label = chatbot_service_label((string)$row['room_type']);
        $price = isset($row['price']) ? (float)$row['price'] : 0.0;
        $lines[] = '- #' . (int)$row['id'] . ' ' . $label . ' — ₱' . number_format($price, 2);
    }
    return implode("\n", $lines);
}

function chatbot_service_available_count(mysqli $conn, string $service): int
{
    $serviceLabel = chatbot_service_label($service);
    $serviceKey = strtolower(trim(str_replace(' ', '_', $service)));

    $columns = [];
    $colResult = $conn->query("SHOW COLUMNS FROM rooms");
    if ($colResult) {
        while ($row = $colResult->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    $hasRequestedSchema = in_array('room_type', $columns, true)
        && in_array('status', $columns, true);

    if ($hasRequestedSchema) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS c
            FROM rooms
            WHERE room_type = ?
              AND status = 'available'
        ");
        $stmt->bind_param("s", $serviceLabel);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        return $count;
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS c
        FROM rooms
        WHERE type = ?
          AND status IN ('available', 'ready')
          AND NOT EXISTS (
              SELECT 1 FROM bookings b
              WHERE b.room_id = rooms.id
                AND b.status = 'Checked In'
          )
    ");
    $stmt->bind_param("s", $serviceKey);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $count;
}

function chatbot_basic_question_reply(mysqli $conn, string $message): ?string
{
    $text = strtolower(trim($message));
    if ($text === '') {
        return null;
    }

    $isAvailabilityQuestion = strpos($text, 'available room') !== false
        || strpos($text, 'rooms available') !== false
        || strpos($text, 'availability') !== false
        || strpos($text, 'what rooms') !== false;
    if ($isAvailabilityQuestion) {
        return chatbot_available_rooms_summary($conn);
    }

    if (strpos($text, 'check in') !== false || strpos($text, 'check-in') !== false) {
        $checkin = chatbot_get_setting($conn, 'checkin_time', '14:00');
        return 'Our check-in time is ' . $checkin . '.';
    }

    if (strpos($text, 'check out') !== false || strpos($text, 'check-out') !== false) {
        $checkout = chatbot_get_setting($conn, 'checkout_time', '12:00');
        return 'Our check-out time is ' . $checkout . '.';
    }

    if (strpos($text, 'where') !== false || strpos($text, 'location') !== false || strpos($text, 'address') !== false) {
        $address = chatbot_get_setting($conn, 'property_address', 'Barangay Poblacion, Santa Fe, Cebu');
        return 'Our location is: ' . $address . '.';
    }

    if (strpos($text, 'contact') !== false || strpos($text, 'phone') !== false || strpos($text, 'number') !== false || strpos($text, 'email') !== false) {
        $phone = chatbot_get_setting($conn, 'property_phone', '+63 32 123 4567');
        $email = chatbot_get_setting($conn, 'property_email', 'info@santafebeachclub.com');
        return 'You can contact us at ' . $phone . ' or ' . $email . '.';
    }

    if (strpos($text, 'payment') !== false || strpos($text, 'gcash') !== false || strpos($text, 'bank') !== false) {
        return 'We accept Pay at Check-in, GCash, and Bank Deposit. Exact options depend on your booking step.';
    }

    if (strpos($text, 'cancel') !== false || strpos($text, 'cancellation') !== false || strpos($text, 'refund') !== false) {
        return 'You can request cancellation before check-in. Refund eligibility depends on booking terms and confirmation status.';
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    chatbot_reply('Use POST for chatbot messages.', [], 405);
}

chatbot_init_session();

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = [];
}

$message = isset($data['message']) ? trim((string)$data['message']) : '';
if ($message === '') {
    chatbot_reply('Please enter a message.', [], 422);
}

$state = &$_SESSION['chatbot'];
$currentStep = isset($state['current_step']) ? (string)$state['current_step'] : 'INTENT';
$slots = &$state['slots'];
$lowerMessage = strtolower($message);

if ($lowerMessage === 'restart') {
    chatbot_reset_session();
    chatbot_reply('Hi! Welcome to Santa Fe Beach Club. Tell me your travel dates and number of guests, and I will help you find a room.', [
        'current_step' => 'INTENT',
        'slots' => $_SESSION['chatbot']['slots'],
        'quick_menu' => chatbot_quick_menu_start(),
    ]);
}

// Smart room finder: collect a stay range, party size, and optional total-stay budget.
if ($currentStep === 'FINDER_CHECKIN') {
    if (!chatbot_valid_date($message)) {
        chatbot_reply('Please enter a valid check-in date in YYYY-MM-DD.', ['current_step' => 'FINDER_CHECKIN'], 422);
    }
    $state['finder'] = ['checkin' => $message];
    $state['current_step'] = 'FINDER_CHECKOUT';
    chatbot_reply('What is your check-out date? (YYYY-MM-DD)', ['current_step' => 'FINDER_CHECKOUT']);
}

if ($currentStep === 'FINDER_CHECKOUT') {
    $finder = $state['finder'] ?? [];
    if (empty($finder['checkin']) || !chatbot_valid_stay_dates((string)$finder['checkin'], $message)) {
        chatbot_reply('Your check-out date must be after check-in. Please enter it in YYYY-MM-DD.', ['current_step' => 'FINDER_CHECKOUT'], 422);
    }
    $state['finder']['checkout'] = $message;
    $state['current_step'] = 'FINDER_GUESTS';
    chatbot_reply('How many guests will be staying?', ['current_step' => 'FINDER_GUESTS']);
}

if ($currentStep === 'FINDER_GUESTS') {
    $guests = filter_var($message, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
    if ($guests === false) {
        chatbot_reply('Please enter the number of guests, from 1 to 20.', ['current_step' => 'FINDER_GUESTS'], 422);
    }
    $state['finder']['guests'] = (int)$guests;
    $state['current_step'] = 'FINDER_BUDGET';
    chatbot_reply('Do you have a total-stay budget in pesos? Enter an amount, or type "skip".', [
        'current_step' => 'FINDER_BUDGET',
        'quick_menu' => ['Skip budget'],
    ]);
}

if ($lowerMessage === 'find a room') {
    $state['finder'] = [];
    $state['current_step'] = 'FINDER_CHECKIN';
    chatbot_reply('What is your check-in date? Please use YYYY-MM-DD.', ['current_step' => 'FINDER_CHECKIN']);
}

if ($currentStep === 'FINDER_BUDGET') {
    $finder = $state['finder'] ?? [];
    $budget = in_array($lowerMessage, ['skip', 'skip budget', 'no'], true) ? null : chatbot_budget_from_message($message);
    if (!in_array($lowerMessage, ['skip', 'skip budget', 'no'], true) && $budget === null) {
        chatbot_reply('Please enter a budget such as ₱8,000, or type "skip".', ['current_step' => 'FINDER_BUDGET'], 422);
    }
    $finder['budget'] = $budget;
    $matches = chatbot_room_finder_results($conn, (string)$finder['checkin'], (string)$finder['checkout'], (int)$finder['guests'], $budget);
    $state['finder'] = $finder;
    $state['current_step'] = 'FINDER_RESULTS';
    if (empty($matches)) {
        chatbot_reply('I could not find a room that matches those dates, guests, and budget. Try a larger budget or different dates.', [
            'current_step' => 'FINDER_BUDGET',
            'quick_menu' => ['Skip budget', 'Find a room'],
        ]);
    }
    $buttons = [];
    foreach ($matches as $match) {
        $buttons[] = 'Choose ' . $match['label'];
    }
    $buttons[] = 'Find a room';
    chatbot_reply(chatbot_room_finder_reply($finder, $matches), [
        'current_step' => 'FINDER_RESULTS',
        'quick_menu' => $buttons,
    ]);
}

if ($currentStep === 'FINDER_RESULTS' && preg_match('/^choose\s+(.+)$/i', $message, $choice)) {
    $selectedLabel = trim($choice[1]);
    $finder = $state['finder'] ?? [];
    $typeStmt = $conn->prepare("SELECT name FROM room_types WHERE REPLACE(LOWER(name), '_', ' ') = LOWER(?) LIMIT 1");
    $typeStmt->bind_param('s', $selectedLabel);
    $typeStmt->execute();
    $typeResult = $typeStmt->get_result();
    $selectedType = $typeResult && $typeResult->num_rows ? (string)$typeResult->fetch_assoc()['name'] : '';
    $typeStmt->close();
    if ($selectedType === '' || empty($finder['checkin']) || empty($finder['checkout']) || empty($finder['guests'])) {
        chatbot_reply('That room selection has expired. Please search again.', ['current_step' => 'INTENT'], 409);
    }
    $query = http_build_query([
        'checkin' => $finder['checkin'],
        'checkout' => $finder['checkout'],
        'guests' => $finder['guests'],
        'room_type' => $selectedType,
    ]);
    chatbot_reply('Opening your booking form for ' . chatbot_service_label($selectedType) . '.', [
        'redirect_url' => 'book?' . $query,
        'current_step' => 'FINDER_RESULTS',
    ]);
}

if ($currentStep === 'INTENT') {
    if ($lowerMessage === '1' || $lowerMessage === 'book a room') {
        $state['current_step'] = 'ASK_SERVICE';
        chatbot_reply('Great! Let us book your room. ' . chatbot_step_prompt('ASK_SERVICE'), [
            'current_step' => 'ASK_SERVICE',
            'slots' => $slots,
        ]);
    }

    if ($lowerMessage === '2' || $lowerMessage === 'show available rooms' || $lowerMessage === 'see available rooms') {
        chatbot_reply(chatbot_available_rooms_summary($conn) . "\n\nDo you want to book a room? (yes/no)", [
            'current_step' => 'INTENT',
            'slots' => $slots,
            'quick_menu' => ['Book a room', 'Ask something else'],
        ]);
    }

    if ($lowerMessage === '3' || $lowerMessage === 'ask something else' || $lowerMessage === 'ask a question') {
        chatbot_reply('Sure. Ask me your question, or type "book" if you want to reserve.', [
            'current_step' => 'INTENT',
            'slots' => $slots,
        ]);
    }

    if ($lowerMessage === 'yes') {
        $state['current_step'] = 'ASK_SERVICE';
        chatbot_reply('Great! Let us book your room. ' . chatbot_step_prompt('ASK_SERVICE'), [
            'current_step' => 'ASK_SERVICE',
            'slots' => $slots,
        ]);
    }

    $intent = chatbot_detect_intent($message);
    if ($intent === 'booking') {
        $state['current_step'] = 'ASK_SERVICE';
        chatbot_reply('Great! Let us book your room. ' . chatbot_step_prompt('ASK_SERVICE'), [
            'current_step' => 'ASK_SERVICE',
            'slots' => $slots,
        ]);
    }

    if ($intent === 'faq') {
        chatbot_reply(chatbot_available_rooms_summary($conn) . "\n\nDo you want to book a room? (yes/no)", [
            'current_step' => 'INTENT',
            'slots' => $slots,
            'quick_menu' => ['Book a room', 'Ask something else'],
        ]);
    }

    $faqReply = chatbot_basic_question_reply($conn, $message);
    if ($faqReply !== null) {
        chatbot_reply($faqReply . "\n\nDo you want to book a room? (yes/no)", [
            'current_step' => 'INTENT',
            'slots' => $slots,
            'mode' => 'faq',
            'quick_menu' => ['Book a room', 'Ask something else'],
        ]);
    }

    chatbot_reply('I can help you find a room, make a booking, or answer a question. What would you like to do?', [
        'current_step' => 'INTENT',
        'slots' => $slots,
        'quick_menu' => chatbot_quick_menu_start(),
    ]);
}

if (chatbot_is_booking_step($currentStep)) {
    $inFlowIntent = chatbot_detect_intent($message);
    if ($inFlowIntent === 'faq') {
        $faqReply = chatbot_basic_question_reply($conn, $message);
        if ($faqReply === null) {
            $faqReply = chatbot_available_rooms_summary($conn);
        }

        chatbot_reply($faqReply . "\n\n" . chatbot_step_prompt($currentStep), [
            'current_step' => $currentStep,
            'slots' => $slots,
        ]);
    }
}

if ($currentStep === 'CONFIRM') {
    if ($lowerMessage === 'no') {
        chatbot_reset_session();
        chatbot_reply('Okay, booking cancelled. I can help you: 1) Book a room 2) Show available rooms 3) Answer questions. What would you like to do?', [
            'current_step' => 'INTENT',
            'slots' => $_SESSION['chatbot']['slots'],
            'quick_menu' => chatbot_quick_menu_start(),
        ]);
    }

    if ($lowerMessage !== 'yes') {
        chatbot_reply('Please reply "yes" to save or "no" to cancel.', [
            'current_step' => 'CONFIRM',
            'slots' => $slots,
        ], 422);
    }

    if (chatbot_service_available_count($conn, $slots['service']) <= 0) {
        $slots['service'] = '';
        $state['current_step'] = 'ASK_SERVICE';
        chatbot_reply('That room just became unavailable. Please choose another room type.', [
            'current_step' => 'ASK_SERVICE',
            'slots' => $slots,
            'quick_menu' => ['See available rooms'],
        ], 409);
    }

    $bookingId = chatbot_insert_booking($conn, $slots);
    chatbot_reset_session();
    chatbot_reply('Booking saved successfully! Reference #' . $bookingId . '. I can help you: 1) Book a room 2) Show available rooms 3) Answer questions. What would you like to do?', [
        'current_step' => 'INTENT',
        'slots' => $_SESSION['chatbot']['slots'],
        'booking_id' => $bookingId,
        'quick_menu' => chatbot_quick_menu_start(),
    ]);
}

$extraction = chatbot_extract_slots_with_llm($conn, $message, $slots);
if (!$extraction['ok']) {
    chatbot_reply($extraction['error'], [
        'current_step' => $currentStep,
        'slots' => $slots,
    ], 502);
}

$newSlots = $extraction['slots'];
foreach (CHATBOT_REQUIRED_FIELDS as $field) {
    if ($newSlots[$field] !== '') {
        $slots[$field] = $newSlots[$field];
    }
}

if ($slots['date'] !== '' && !chatbot_valid_date($slots['date'])) {
    $slots['date'] = '';
    $state['current_step'] = 'ASK_DATE';
    chatbot_reply('Date looks invalid. Please provide a valid future date in YYYY-MM-DD.', [
        'current_step' => 'ASK_DATE',
        'slots' => $slots,
    ], 422);
}

if ($slots['time'] !== '' && !chatbot_valid_time($slots['time'])) {
    $slots['time'] = '';
    $state['current_step'] = 'ASK_TIME';
    chatbot_reply('Time looks invalid. Please provide time in HH:MM (24-hour).', [
        'current_step' => 'ASK_TIME',
        'slots' => $slots,
    ], 422);
}

if ($slots['service'] !== '') {
    $availableCount = chatbot_service_available_count($conn, $slots['service']);
    if ($availableCount <= 0) {
        $slots['service'] = '';
        $state['current_step'] = 'ASK_SERVICE';
        chatbot_reply('That room is not available right now. Please choose another room type.', [
            'current_step' => 'ASK_SERVICE',
            'slots' => $slots,
            'quick_menu' => ['See available rooms'],
        ], 409);
    }
}

$missing = chatbot_missing_fields($slots);
if (!empty($missing)) {
    $nextAskStep = chatbot_step_from_slots($slots);
    $state['current_step'] = $nextAskStep;
    chatbot_reply(chatbot_step_prompt($nextAskStep), [
        'current_step' => $nextAskStep,
        'slots' => $slots,
    ]);
}

if (chatbot_booking_conflict($conn, $slots['service'], $slots['date'], $slots['time'])) {
    $slots['time'] = '';
    $state['current_step'] = 'ASK_TIME';
    chatbot_reply('That slot is already booked for the same service/date/time. Please provide a different time (HH:MM).', [
        'current_step' => 'ASK_TIME',
        'slots' => $slots,
    ], 409);
}

$state['current_step'] = 'CONFIRM';
$summary = "Please confirm your booking details:\n"
    . "Service: " . $slots['service'] . "\n"
    . "Date: " . $slots['date'] . "\n"
    . "Time: " . $slots['time'] . "\n"
    . "Name: " . $slots['name'] . "\n\n"
    . "Reply \"yes\" to save or \"no\" to cancel.";

chatbot_reply($summary, [
    'current_step' => 'CONFIRM',
    'slots' => $slots,
]);
