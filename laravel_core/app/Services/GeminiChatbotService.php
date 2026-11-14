<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiChatbotService
{
    protected string $apiKey;
    protected string $model;
    protected AccommodationService $accommodationService;
    protected ReservationService $reservationService;
    protected ChatbotAssistantService $assistant;

    private const FALLBACK_MODELS = [
        'gemini-3.6-flash',
        'gemini-3.5-flash-lite',
        'gemini-3.8-flash',
    ];

    public function __construct(
        ?ChatbotAssistantService $assistant = null,
        ?ReservationService $reservationService = null,
        ?AccommodationService $accommodationService = null
    ) {
        $this->apiKey = trim((string) (config('services.gemini.key') ?? env('GEMINI_API_KEY', '')));
        $rawModel = trim((string) (config('services.gemini.model') ?? env('GEMINI_MODEL', 'gemini-3.8-flash')));
        $this->model = $this->normalizeModelName($rawModel);

        $this->accommodationService = $accommodationService ?? new AccommodationService();
        $this->reservationService = $reservationService ?? new ReservationService($this->accommodationService);
        $this->assistant = $assistant ?? new ChatbotAssistantService($this->accommodationService, $this->reservationService);
    }

    /**
     * Upgrade retired Gemini model IDs to supported equivalents.
     */
    private function normalizeModelName(string $model): string
    {
        $clean = trim($model);
        if ($clean === '' || str_starts_with($clean, 'gemini-1.5') || $clean === 'gemini-2.5-flash') {
            return 'gemini-3.6-flash';
        }
        return $clean;
    }

    /**
     * Build direct, concise, DB-grounded system instructions for the resort receptionist AI.
     */
    protected function buildSystemContext(string $language, array $facts, array $context, array $guidance): string
    {
        $roomLines = [];
        foreach ($facts['accommodations'] as $slug => $room) {
            $specs = $room['specs'] ?? [];
            $acStr = !empty($specs['aircon']) ? 'Aircon' : 'Fan';
            $bfStr = !empty($specs['breakfast_included']) ? 'Breakfast included' : 'Room only';
            $petStr = !empty($specs['pets_allowed']) ? 'Pets allowed (with notice)' : 'No pets';
            $roomLines[] = sprintf(
                "- %s (%s): ₱%s/night base for %d guests | Max adults: %d (+%d extra @ ₱%s once/stay) | Max kids (10 & below free): %d | %s, %s, Wi-Fi, Private bath, %s",
                $room['display_name'],
                $slug,
                number_format($room['base_price_per_night'], 0),
                $room['base_capacity'],
                $room['max_adults_capacity'],
                $room['max_extra_adults'],
                number_format($room['extra_rate_per_adult_once'], 0),
                $room['max_extra_children'],
                $acStr,
                $bfStr,
                $petStr
            );
        }

        $promoSummary = 'No active seasonal promos right now.';
        if (!empty($facts['promotions'])) {
            $pList = array_map(fn ($p) => ($p->code ?: $p->title) . " ({$p->discount_value})", $facts['promotions']);
            $promoSummary = implode('; ', $pList);
        }

        $contextJson = json_encode([
            'remembered_room'     => $context['room_slug'],
            'remembered_guests'   => $context['guests'],
            'remembered_nights'   => $context['nights'],
            'remembered_dates'    => ($context['check_in'] && $context['check_out']) ? "{$context['check_in']} to {$context['check_out']}" : null,
            'detected_language'   => $language,
            'asked_topics'        => $guidance['topics'],
        ], JSON_UNESCAPED_UNICODE);

        $backendJson = json_encode($guidance['backend_facts'], JSON_UNESCAPED_UNICODE);
        $roomsText = implode("\n", $roomLines);
        $poolNote = 'Does the resort have a swimming pool? NO — do NOT say there is a swimming pool; Santa Fe Beach Club is a direct beachfront property along calm sea waters.';
        $cancelDirective = !empty($guidance['backend_facts']['cancellation_summary_only'])
            ? "\n- CANCELLATION RULE FOR THIS TURN: Do NOT list 48 hours / 5 days / 7 days tiers! ONLY say cancellation is subject to our reservation policy and ask if they would like you to explain the cancellation and refund conditions."
            : '';

        return <<<PROMPT
You are the friendly, professional front desk receptionist of {$facts['property_name']} ({$facts['property_address']}, Phone: {$facts['property_phone']}).

STRICT DIRECT-ANSWERING RULES:
1. Answer the guest's exact question IMMEDIATELY in the first sentence.
2. Length: Simple yes/no/time question = 1 sentence. Normal question = 1 to 3 sentences. Only give multi-item lists when asked to compare or when quoting matching options.
3. NEVER dump all room types, full policies, or unrelated amenities when the guest asks something specific.
4. NEVER repeat details already stated earlier in the conversation, and NEVER ask again for guest count, dates, or room type if already known in Context Memory.
5. Simple factual questions (check-in/out times, Wi-Fi, swimming pool, GCash, deposit rules, cancellation overview) need NO unrequested booking follow-up question unless asking to proceed or missing needed stay details; omit greetings ("Hello! Welcome...") on follow-up turns.
6. Reply naturally in the guest's language ({$language}, including Taglish or Bislish if they used mixed phrasing).
7. STRICT ANTI-HALLUCINATION: Rely ONLY on the Live MySQL Resort Facts & Backend Calculation below. Do NOT invent prices, do NOT do your own fee multiplication (extra-guest fees are ONCE PER STAY, NOT per night), and {$poolNote}{$cancelDirective}

LIVE RESORT FACTS FROM MYSQL:
- Check-in: {$facts['checkin_display']} ({$facts['checkin_raw']}) | Check-out: {$facts['checkout_display']} ({$facts['checkout_raw']})
- Payment Methods Accepted: GCash ({$facts['gcash_number']}), Maya, PayMongo Online Checkout, Bank Deposit (BDO/BPI), and Cash at reception for remaining balance
- Reservation & Balance Policy: {$facts['deposit_pct']}% deposit confirms booking; remaining balance due {$facts['balance_due_hrs']} hours before arrival ({$facts['grace_hrs']}h grace period)
- Cancellation Policy: Available upon request (for simple "Can I cancel?" questions, state that cancellation is subject to our reservation policy and offer to explain conditions WITHOUT dumping all hour/day tiers unless asked). Full schedule when requested: 1–3 nights = 48h before 2:00 PM check-in, 4–6 nights = 5 days, 7+ nights = 7 days for 100% refund; non-refundable after deadline.
- Active Promos: {$promoSummary}
- Shared Amenities: Air conditioning, private bathroom, free Wi-Fi, direct beachfront access, beachfront restaurant & bar, motorcycle/scooter rental, island hopping assistance, luggage storage. NO swimming pool.
- Accommodations:
{$roomsText}

ACTIVE CONVERSATION CONTEXT MEMORY:
{$contextJson}

VERIFIED BACKEND CALCULATION & ANSWER FACTS FOR THIS TURN (MUST USE THESE EXACT NUMBERS):
{$backendJson}
Suggested direct phrasing reference: "{$guidance['direct_reply']}"
PROMPT;
    }

    /**
     * Send message with conversation memory, backend calculations, Gemini inference, and zero-hallucination fallback.
     */
    public function sendMessage(string $userMessage, array $conversationHistory = [], ?string $preferredLanguage = null): array
    {
        $facts = $this->assistant->getLiveResortSnapshot();
        $context = $this->assistant->extractContext($conversationHistory, $userMessage);
        $language = $this->assistant->detectLanguage($userMessage, $preferredLanguage, $context['language']);
        $guidance = $this->assistant->prepareTurnGuidance($userMessage, $context, $facts, $language);

        // Simple greetings can return immediately without burning external quota
        if ($guidance['intent'] === 'greeting') {
            return [
                'reply'      => $guidance['direct_reply'],
                'provider'   => 'local_direct',
                'language'   => $language,
                'intent'     => $guidance['intent'],
                'context'    => $context,
                'backend'    => $guidance['backend_facts'],
            ];
        }

        if ($this->apiKey !== '' && $this->apiKey !== 'YOUR_GEMINI_API_KEY_HERE') {
            $systemInstruction = $this->buildSystemContext($language, $facts, $context, $guidance);

            $contents = [];
            foreach (array_slice($conversationHistory, -8) as $turn) {
                $role = strtolower((string) ($turn['role'] ?? 'user'));
                $text = isset($turn['content']) ? (string) $turn['content'] : (string) ($turn['parts'][0]['text'] ?? '');
                if (trim($text) === '') {
                    continue;
                }
                $contents[] = [
                    'role'  => ($role === 'assistant' || $role === 'model') ? 'model' : 'user',
                    'parts' => [['text' => $text]],
                ];
            }

            $contents[] = [
                'role'  => 'user',
                'parts' => [['text' => $userMessage]],
            ];

            $payload = [
                'system_instruction' => [
                    'parts' => [['text' => $systemInstruction]],
                ],
                'contents'         => $contents,
                'generationConfig' => [
                    'temperature'     => 0.2,
                    'maxOutputTokens' => 1500,
                ],
            ];

            $geminiReply = $this->callGeminiWithFallback($payload);
            if ($geminiReply !== null) {
                return [
                    'reply'    => $geminiReply,
                    'provider' => 'gemini',
                    'language' => $language,
                    'intent'   => $guidance['intent'],
                    'context'  => $context,
                    'backend'  => $guidance['backend_facts'],
                ];
            }
        }

        return [
            'reply'    => $guidance['direct_reply'],
            'provider' => 'backend_grounded_fallback',
            'language' => $language,
            'intent'   => $guidance['intent'],
            'context'  => $context,
            'backend'  => $guidance['backend_facts'],
        ];
    }

    /**
     * Call Google Gemini endpoint via x-goog-api-key header with automatic model failover.
     */
    protected function callGeminiWithFallback(array $payload): ?string
    {
        $models = array_values(array_unique(array_merge([$this->model], self::FALLBACK_MODELS)));

        $httpOptions = [];
        $curlCa = (string) ini_get('curl.cainfo');
        $sslCa = (string) ini_get('openssl.cafile');
        if ($curlCa === '' && $sslCa === '') {
            $localCa = 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt';
            if (file_exists($localCa)) {
                $httpOptions['verify'] = $localCa;
            }
        }

        foreach ($models as $modelName) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent";
            try {
                $response = Http::timeout(10)
                    ->withOptions($httpOptions)
                    ->withHeaders([
                        'Content-Type'   => 'application/json',
                        'x-goog-api-key' => $this->apiKey,
                    ])
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $text = trim((string) $response->json('candidates.0.content.parts.0.text', ''));
                    if ($text !== '') {
                        return $text;
                    }
                }

                // Retry next model only on retired/missing (404) or transient overload (429/503)
                if (!in_array($response->status(), [404, 429, 503], true)) {
                    break;
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini chatbot request failed for {$modelName}: " . $e->getMessage());
            }
        }

        return null;
    }
}
