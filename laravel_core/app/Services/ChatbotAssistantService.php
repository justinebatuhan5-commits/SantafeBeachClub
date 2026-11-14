<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ChatbotAssistantService
{
    public function __construct(
        protected AccommodationService $accommodationService,
        protected ReservationService $reservationService
    ) {}

    /**
     * Canonical resort room order for concise customer recommendations & listings.
     * Excludes legacy duplicate seed aliases unless explicitly matched.
     */
    private const CANONICAL_TYPES = [
        'standard_room',
        'standard_king',
        'beachview_duplex',
        'seaview_duplex',
        'beach_villa',
    ];

    /**
     * Verified factual amenities & policies per accommodation type (grounded to website & DB).
     */
    private const UNIT_SPECS = [
        'standard_room' => [
            'display_name' => 'Standard Room',
            'aircon' => true,
            'wifi' => true,
            'private_bath' => true,
            'breakfast_included' => false,
            'pets_allowed' => false,
            'extra_bed_note' => 'extra adult allowed at ₱700 once per stay (up to 3 extra adults)',
        ],
        'standard_king' => [
            'display_name' => 'Standard Family Room',
            'aircon' => true,
            'wifi' => true,
            'private_bath' => true,
            'breakfast_included' => false,
            'pets_allowed' => false,
            'extra_bed_note' => '1 extra adult allowed at ₱700 once per stay',
        ],
        'beachview_duplex' => [
            'display_name' => 'Beachview Duplex',
            'aircon' => true,
            'wifi' => true,
            'private_bath' => true,
            'breakfast_included' => true,
            'pets_allowed' => false,
            'extra_bed_note' => '1 extra adult at ₱1,000 once per stay; up to 1 child (10 & below) free',
        ],
        'seaview_duplex' => [
            'display_name' => 'Seaview Duplex',
            'aircon' => true,
            'wifi' => true,
            'private_bath' => true,
            'breakfast_included' => true,
            'pets_allowed' => false,
            'extra_bed_note' => '1 extra adult at ₱1,000 once per stay; up to 1 child (10 & below) free',
        ],
        'beach_villa' => [
            'display_name' => 'Beach Villa',
            'aircon' => true,
            'wifi' => true,
            'private_bath' => true,
            'breakfast_included' => true,
            'pets_allowed' => true,
            'extra_bed_note' => 'up to 3 extra adults at ₱1,000 each once per stay; up to 2 kids (10 & below) free',
        ],
        'cottage' => [
            'display_name' => 'Beach Cottage',
            'aircon' => true,
            'wifi' => true,
            'private_bath' => true,
            'breakfast_included' => false,
            'pets_allowed' => false,
            'extra_bed_note' => 'base capacity 3 guests',
        ],
    ];

    /**
     * Detect guest language across English, Filipino/Tagalog, Cebuano/Bisaya, and mixed Taglish/Bislish.
     */
    public function detectLanguage(string $message, ?string $preferredLanguage = null, ?string $conversationLanguage = null): string
    {
        if ($preferredLanguage !== null && trim($preferredLanguage) !== '' && strtolower(trim($preferredLanguage)) !== 'auto') {
            $pref = trim($preferredLanguage);
            if (stripos($pref, 'ceb') !== false || stripos($pref, 'bisaya') !== false) {
                return 'Cebuano / Bisaya';
            }
            if (stripos($pref, 'tagalog') !== false || stripos($pref, 'fil') !== false) {
                return 'Tagalog';
            }
            return $pref;
        }

        $clean = preg_replace('/[^a-zA-Z\-\s]/', ' ', strtolower($message)) ?? strtolower($message);
        $lower = ' ' . (preg_replace('/\s+/', ' ', $clean) ?? $clean) . ' ';

        $bisayaMarkers = [
            ' pila ', ' tagpila ', ' unsa ', ' unsay ', ' unsaon ', ' naa ', ' naay ',
            ' moy ', ' inyo ', ' inyong ', ' bakante ', ' ugma ', ' tawo ', ' gabii ',
            ' palihug ', ' maayong ', ' dapit ', ' maigo ', ' asa ', ' karong ', ' nako ', ' namo ',
        ];
        $tagalogMarkers = [
            ' magkano ', ' paano ', ' kailan ', ' saan ', ' ano ', ' anong ', ' meron ',
            ' ilang ', ' gabi ', ' tao ', ' po ', ' opo ', ' namin ', ' natin ',
            ' bukas ', ' presyo ', ' kasya ', ' amin ', ' maaari ', ' bayad ', ' bayaran ',
        ];
        $sharedFil = [' pwede ', ' kwarto ', ' salamat ', ' magbook ', ' mag-book ', ' may '];

        $ceb = 0;
        foreach ($bisayaMarkers as $m) {
            if (str_contains($lower, $m)) {
                $ceb += 2;
            }
        }
        $tl = 0;
        foreach ($tagalogMarkers as $m) {
            if (str_contains($lower, $m)) {
                $tl += 2;
            }
        }
        foreach ($sharedFil as $m) {
            if (str_contains($lower, $m)) {
                if ($ceb > $tl) {
                    $ceb++;
                } else {
                    $tl++;
                }
            }
        }

        if ($ceb > 0 || $tl > 0) {
            return $ceb > $tl ? 'Cebuano / Bisaya' : 'Tagalog';
        }

        if ($conversationLanguage !== null && $conversationLanguage !== '' && $conversationLanguage !== 'English') {
            $englishAnchors = [' how ', ' what ', ' when ', ' where ', ' can ', ' do you ', ' is there ', ' are there '];
            $hasEnglish = false;
            foreach ($englishAnchors as $a) {
                if (str_contains($lower, $a)) {
                    $hasEnglish = true;
                    break;
                }
            }
            if (!$hasEnglish) {
                return $conversationLanguage;
            }
        }

        return 'English';
    }

    /**
     * Normalize SMS shorthand, informal expressions, misspellings, and Tagalog/Bisaya phrases.
     */
    public function normalizeInput(string $message): string
    {
        $normalized = strtolower(trim($message));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        $replacements = [
            '/\bhm\b/'                  => 'how much',
            '/\bnyt\b/'                 => 'night',
            '/\bnyts\b/'                => 'nights',
            '/\bgabii\b/'               => 'nights',
            '/\bgabi\b/'                => 'nights',
            '/\btmrrw\b|\btmrw\b|\btomorow\b|\btom\b|\bugma\b|\bbukas\b/' => 'tomorrow',
            '/\bavail\b|\bbakante\b/'   => 'available',
            '/\bppl\b|\bpax\b|\btao\b|\btawo\b|\bpersons?\b/' => 'persons',
            '/\baircon\b|\bair-con\b|\bac\b/' => 'air conditioning',
            '/\bwifi\b|\bwi-fi\b/'      => 'wifi',
            '/\bwalk in\b/'             => 'walk-in',
            '/\bvila\b/'                => 'villa',
            '/\bduplx\b/'               => 'duplex',
            '/\bseaveiw\b/'             => 'seaview',
            '/\bstandrd\b/'             => 'standard',
            '/\bur\b/'                  => 'your',
            '/\bu\b/'                   => 'you',
            '/\bpila\b|\btagpila\b|\bmagkano\b/' => 'how much',
            '/\bnaa\s+moy\b|\bnaay\b|\bmeron\s+ba\b|\bmayroon\s+ba\b/' => 'do you have',
        ];

        foreach ($replacements as $pattern => $target) {
            $normalized = preg_replace($pattern, $target, $normalized) ?? $normalized;
        }

        return trim($normalized);
    }

    /**
     * Retrieve live settings, accommodations, active promotions, and knowledge snippets from MySQL.
     */
    public function getLiveResortSnapshot(): array
    {
        $settingsRows = DB::table('settings')->pluck('setting_value', 'setting_key')->toArray();

        $checkInRaw = (string) ($settingsRows['checkin_time'] ?? '14:00');
        $checkOutRaw = (string) ($settingsRows['checkout_time'] ?? '12:00');

        $accommodations = [];
        foreach (self::CANONICAL_TYPES as $slug) {
            $est = $this->reservationService->calculateGuestAccommodationEstimate($slug, 1, 1);
            if (!empty($est['success'])) {
                $est['specs'] = self::UNIT_SPECS[$slug] ?? [];
                $accommodations[$slug] = $est;
            }
        }

        // Also include Cottage if present in DB so direct queries about cottages resolve accurately
        $cottageEst = $this->reservationService->calculateGuestAccommodationEstimate('cottage', 1, 1);
        if (!empty($cottageEst['success'])) {
            $cottageEst['specs'] = self::UNIT_SPECS['cottage'];
            $accommodations['cottage'] = $cottageEst;
        }

        $promos = DB::table('promotions')
            ->where('is_active', 1)
            ->where('valid_from', '<=', date('Y-m-d'))
            ->where('valid_until', '>=', date('Y-m-d'))
            ->select('code', 'title', 'discount_type', 'discount_value', 'valid_until')
            ->get()
            ->toArray();

        $knowledge = DB::table('chatbot_knowledge')
            ->where('is_active', 1)
            ->select('id', 'category', 'question', 'answer', 'language')
            ->get()
            ->toArray();

        return [
            'property_name'    => (string) ($settingsRows['property_name'] ?? 'Santa Fe Beach Club'),
            'property_address' => (string) ($settingsRows['property_address'] ?? 'Barangay Poblacion, Santa Fe, Cebu'),
            'property_phone'   => (string) ($settingsRows['property_phone'] ?? '09505223146'),
            'property_email'   => (string) ($settingsRows['property_email'] ?? ''),
            'checkin_raw'      => $checkInRaw,
            'checkout_raw'     => $checkOutRaw,
            'checkin_display'  => date('g:i A', strtotime($checkInRaw) ?: strtotime('14:00')),
            'checkout_display' => date('g:i A', strtotime($checkOutRaw) ?: strtotime('12:00')),
            'deposit_pct'      => (int) ($settingsRows['deposit_percentage'] ?? 50),
            'balance_due_hrs'  => (int) ($settingsRows['balance_due_hours_before_checkin'] ?? 24),
            'grace_hrs'        => (int) ($settingsRows['balance_grace_period_hours'] ?? 6),
            'gcash_number'     => (string) ($settingsRows['gcash_number'] ?? '0950 522 3146'),
            'gcash_name'       => (string) ($settingsRows['gcash_name'] ?? ''),
            'accommodations'   => $accommodations,
            'promotions'       => $promos,
            'knowledge'        => $knowledge,
            'has_swimming_pool' => false,
            'payment_methods'  => ['GCash', 'Maya', 'PayMongo Online (card/e-wallet)', 'Bank Deposit (BDO / BPI)', 'Cash at reception (for remaining balance)'],
        ];
    }

    /**
     * Extract multi-turn conversation memory (room of interest, guest count, nights, dates, already-answered points).
     */
    public function extractContext(array $history, string $currentMessage): array
    {
        $ctx = [
            'room_slug'         => null,
            'guests'            => null,
            'guests_in_current' => false,
            'nights'            => null,
            'nights_in_current' => false,
            'check_in'          => null,
            'check_out'         => null,
            'dates_in_current'  => false,
            'language'          => null,
            'prior_topics'      => [],
        ];

        $turns = [];
        foreach ($history as $item) {
            $role = strtolower((string) ($item['role'] ?? 'user'));
            $text = '';
            if (isset($item['content'])) {
                $text = (string) $item['content'];
            } elseif (isset($item['parts'][0]['text'])) {
                $text = (string) $item['parts'][0]['text'];
            }
            if (trim($text) !== '') {
                $turns[] = ['role' => ($role === 'assistant' || $role === 'model') ? 'model' : 'user', 'text' => $text];
            }
        }
        $turns[] = ['role' => 'user', 'text' => $currentMessage, 'is_current' => true];

        foreach ($turns as $turn) {
            $text = $turn['text'];
            $isCurrent = !empty($turn['is_current']);
            $norm = $this->normalizeInput($text);

            // Language from prior user turns
            if ($turn['role'] === 'user' && !$isCurrent) {
                $ctx['language'] = $this->detectLanguage($text, null, $ctx['language']);
            }

            // Room type mentioned (both user asks & single-room model answers keep topic sticky)
            $detectedRoom = $this->detectRoomMention($norm, $turn['role'] === 'model');
            if ($detectedRoom !== null) {
                $ctx['room_slug'] = $detectedRoom;
            }

            // User-provided guest count, nights, dates
            if ($turn['role'] === 'user') {
                $g = $this->extractGuestCount($norm);
                if ($g !== null) {
                    $ctx['guests'] = $g;
                    if ($isCurrent) {
                        $ctx['guests_in_current'] = true;
                    }
                }

                $n = $this->extractNightsCount($norm);
                if ($n !== null) {
                    $ctx['nights'] = $n;
                    if ($isCurrent) {
                        $ctx['nights_in_current'] = true;
                    }
                }

                $dates = $this->extractDates($norm);
                if ($dates !== null) {
                    $ctx['check_in'] = $dates[0];
                    $ctx['check_out'] = $dates[1];
                    $ctx['nights'] = max(1, (int) Carbon::parse($dates[0])->diffInDays(Carbon::parse($dates[1])));
                    if ($isCurrent) {
                        $ctx['dates_in_current'] = true;
                    }
                }
            } elseif (!$isCurrent) {
                if (stripos($norm, 'check-in') !== false && preg_match('/2:00\s*pm|14:00/i', $norm)) {
                    $ctx['prior_topics'][] = 'checkin_time';
                }
                if (stripos($norm, 'swimming pool') !== false || stripos($norm, 'beachfront') !== false) {
                    $ctx['prior_topics'][] = 'pool';
                }
                if (stripos($norm, 'gcash') !== false) {
                    $ctx['prior_topics'][] = 'gcash';
                }
            }
        }

        $ctx['language'] = $this->detectLanguage($currentMessage, null, $ctx['language']);
        $ctx['prior_topics'] = array_values(array_unique($ctx['prior_topics']));

        return $ctx;
    }

    /**
     * Identify what the guest is actually asking and run exact backend queries/calculations first.
     */
    public function prepareTurnGuidance(string $userMessage, array $context, array $facts, string $language): array
    {
        $norm = $this->normalizeInput($userMessage);
        $topics = $this->classifySpecificTopics($norm, $context);
        $backendData = [];
        $directParts = [];
        $followUpQuestion = null;

        // 1. Greeting only
        if ($topics === ['greeting']) {
            $reply = $this->tr($language, [
                'English' => 'Hello! Welcome to Santa Fe Beach Club. How can I help you plan your stay?',
                'Tagalog' => 'Magandang araw! Welcome sa Santa Fe Beach Club. Paano po ako makakatulong sa inyong stay?',
                'Cebuano / Bisaya' => 'Maayong adlaw! Welcome sa Santa Fe Beach Club. Unsaon nako pagtabang nimo karon?',
            ]);
            return [
                'intent' => 'greeting',
                'topics' => $topics,
                'backend_facts' => [],
                'direct_reply' => $reply,
            ];
        }

        // 2. Specific Amenity Qs (Pool, Aircon, Wi-Fi, Breakfast, Restaurant, Pets, Parking, Tours, Luggage)
        foreach ($topics as $t) {
            if (!str_starts_with($t, 'amenity:')) {
                continue;
            }
            $item = substr($t, 8);
            $ans = $this->answerSpecificAmenity($item, $context['room_slug'], $language, $facts);
            $directParts[] = $ans;
            $backendData["amenity_{$item}"] = $ans;
        }

        // 3. Check-in / Check-out times
        if (in_array('checkin_time', $topics, true) && in_array('checkout_time', $topics, true)) {
            $ans = $this->tr($language, [
                'English' => "Our check-in time is {$facts['checkin_display']} and check-out is at {$facts['checkout_display']}.",
                'Tagalog' => "Ang check-in time ay {$facts['checkin_display']} at ang check-out ay {$facts['checkout_display']}.",
                'Cebuano / Bisaya' => "Ang check-in time kay {$facts['checkin_display']} ug ang check-out kay {$facts['checkout_display']}.",
            ]);
            $directParts[] = $ans;
            $backendData['checkin_checkout'] = $ans;
        } elseif (in_array('checkin_time', $topics, true)) {
            $ans = $this->tr($language, [
                'English' => "Our check-in time is {$facts['checkin_display']}.",
                'Tagalog' => "Ang aming check-in time ay {$facts['checkin_display']}.",
                'Cebuano / Bisaya' => "Ang among check-in time kay {$facts['checkin_display']}.",
            ]);
            $directParts[] = $ans;
            $backendData['checkin_time'] = $ans;
        } elseif (in_array('checkout_time', $topics, true)) {
            $ans = $this->tr($language, [
                'English' => "Our check-out time is {$facts['checkout_display']}.",
                'Tagalog' => "Ang aming check-out time ay {$facts['checkout_display']}.",
                'Cebuano / Bisaya' => "Ang among check-out time kay {$facts['checkout_display']}.",
            ]);
            $directParts[] = $ans;
            $backendData['checkout_time'] = $ans;
        }

        // 4. Specific Payment / Deposit queries
        if (in_array('payment_gcash', $topics, true)) {
            $ans = $this->tr($language, [
                'English' => 'Yes, we accept GCash.',
                'Tagalog' => 'Opo, tumatanggap kami ng GCash.',
                'Cebuano / Bisaya' => 'Oo, modawat mi og GCash.',
            ]);
            $directParts[] = $ans;
            $backendData['gcash_accepted'] = true;
        } elseif (in_array('payment_methods', $topics, true)) {
            $ans = $this->tr($language, [
                'English' => "We accept GCash ({$facts['gcash_number']}), Maya, PayMongo online (card/e-wallet), Bank Deposit (BDO/BPI), and cash at reception for your remaining balance.",
                'Tagalog' => "Tumatanggap kami ng GCash ({$facts['gcash_number']}), Maya, PayMongo online (card/e-wallet), Bank Deposit (BDO/BPI), at cash sa reception para sa natitirang balanse.",
                'Cebuano / Bisaya' => "Modawat mi og GCash ({$facts['gcash_number']}), Maya, PayMongo online (card/e-wallet), Bank Deposit (BDO/BPI), ug cash sa reception para sa nahabiling balanse.",
            ]);
            $directParts[] = $ans;
            $backendData['payment_methods'] = $facts['payment_methods'];
        } elseif (in_array('payment_deposit', $topics, true)) {
            $ans = $this->tr($language, [
                'English' => "A {$facts['deposit_pct']}% deposit confirms your booking (via GCash, Maya, PayMongo, or Bank Deposit), and the remaining balance is due {$facts['balance_due_hrs']} hours before check-in.",
                'Tagalog' => "Kailangan ng {$facts['deposit_pct']}% deposit upang makumpirma ang booking (via GCash, Maya, PayMongo, o Bank Deposit), at ang natitirang balanse ay babayaran {$facts['balance_due_hrs']} oras bago ang check-in.",
                'Cebuano / Bisaya' => "Kinahanglan og {$facts['deposit_pct']}% nga deposit aron makompirmar ang imong booking (pinaagi sa GCash, Maya, PayMongo, o Bank Deposit), ug ang nahabiling balanse bayaran {$facts['balance_due_hrs']} ka oras sa dili pa ang check-in.",
            ]);
            $directParts[] = $ans;
            $backendData['deposit_policy'] = $ans;
        }

        // 5. Walk-in policy
        if (in_array('walkin', $topics, true)) {
            $ans = $this->tr($language, [
                'English' => 'Yes, we accept walk-in guests subject to same-day room availability at reception, though reserving ahead online with a 50% deposit is recommended to secure your room.',
                'Tagalog' => 'Opo, tumatanggap kami ng walk-in guests depende sa available na kwarto sa araw ng pagdating, pero inirerekomenda namin ang pag-reserve online gamit ang 50% deposit.',
                'Cebuano / Bisaya' => 'Oo, modawat mi og walk-in guests basta naay bakante nga kwarto sa maong adlaw, pero mas maayo nga mag-reserve daan online gamit ang 50% deposit.',
            ]);
            $directParts[] = $ans;
            $backendData['walkin'] = $ans;
        }

        // 6. Cancellation policy (distinguish yes/no question from explicit deadline/rules query)
        if (in_array('cancellation', $topics, true)) {
            $asksDetails = (bool) preg_match('/\b(how|when|deadline|policy|rules?|refund|percent|ilang|kanus-a|paano|unsaon|conditions?)\b/i', $norm)
                || $context['check_in'] !== null
                || $context['nights'] !== null;

            if (!$asksDetails) {
                $directParts[] = $this->tr($language, [
                    'English' => 'Yes, cancellation is subject to our reservation policy.',
                    'Tagalog' => 'Opo, pwede pong mag-cancel ng reservation alinsunod sa aming cancellation policy.',
                    'Cebuano / Bisaya' => 'Oo, pwede maka-cancel sa reservation sumala sa among reservation policy.',
                ]);
                $followUpQuestion = $followUpQuestion ?? $this->tr($language, [
                    'English' => 'Would you like me to explain the cancellation and refund conditions?',
                    'Tagalog' => 'Gusto po ba ninyong ipaliwanag ko ang mga deadline at kondisyon sa refund?',
                    'Cebuano / Bisaya' => 'Gusto ba nimong ipasabot nako ang cancellation deadlines ug refund conditions?',
                ]);
                $backendData['cancellation_summary_only'] = true;
            } else {
                $directParts[] = $this->buildCancellationDetailsText($context, $language);
                $backendData['cancellation_rules'] = '48h before 2:00 PM check-in (1-3 nights), 5 days (4-6 nights), 7 days (7+ nights) for 100% refund; non-refundable afterward.';
            }
        }

        // 7. Discounts / Directions
        if (in_array('discounts', $topics, true)) {
            $directParts[] = 'Yes, we offer Senior Citizen and PWD discounts upon submitting a valid government ID through our Discount Booking page for reception verification.';
        }
        if (in_array('directions', $topics, true)) {
            $directParts[] = "Santa Fe Beach Club is in {$facts['property_address']} along Pantalan shoreline, about 3 to 5 minutes from Santa Fe Port by tricycle (₱25–₱50 per person).";
        }

        // 8. Room Pricing / Group Sizing / Multi-Turn Follow-up Calculations
        if (in_array('pricing', $topics, true)) {
            $pricingResult = $this->buildPricingResponse($norm, $context, $facts, $language);
            $directParts[] = $pricingResult['text'];
            $backendData['pricing'] = $pricingResult['data'];
            if (!empty($pricingResult['follow_up']) && $followUpQuestion === null) {
                $followUpQuestion = $pricingResult['follow_up'];
            }
        }

        // 9. Availability Check
        if (in_array('availability', $topics, true)) {
            $availResult = $this->buildAvailabilityResponse($context, $facts, $language);
            $directParts[] = $availResult['text'];
            $backendData['availability'] = $availResult['data'];
            if (!empty($availResult['follow_up']) && $followUpQuestion === null) {
                $followUpQuestion = $availResult['follow_up'];
            }
        }

        // 10. Combine concisely (no redundant joiners if both rate + aircon were asked!)
        $combinedDirect = $this->combinePartsCleanly($topics, $directParts, $followUpQuestion, $language);

        return [
            'intent'       => $topics[0] ?? 'general',
            'topics'       => $topics,
            'backend_facts' => $backendData,
            'direct_reply' => $combinedDirect,
        ];
    }

    /**
     * Build backend-verified pricing answer (single room, multi-turn context, or group-size filter).
     */
    private function buildPricingResponse(string $norm, array $context, array $facts, string $language): array
    {
        $slug = $context['room_slug'];
        $guests = $context['guests'];
        $nights = $context['nights'] ?? 1;
        $checkIn = $context['check_in'];
        $checkOut = $context['check_out'];

        // Case A: Specific accommodation type is active in turn or conversation context
        if ($slug !== null) {
            $estimateGuests = $guests ?? 1;
            $calc = $this->reservationService->calculateGuestAccommodationEstimate($slug, $estimateGuests, $nights, $checkIn, $checkOut);
            if (!empty($calc['success'])) {
                $name = $calc['display_name'];
                $baseFmt = '₱' . number_format($calc['base_price_per_night'], 0);
                $baseCap = $calc['base_capacity'];

                // If guest didn't ask about extra guests or multi-night yet
                if ($guests === null && ($context['nights'] === null || $nights === 1)) {
                    $text = $this->tr($language, [
                        'English' => "Our {$name} costs {$baseFmt} per night for its base capacity of {$baseCap} guests.",
                        'Tagalog' => "Ang aming {$name} ay {$baseFmt} bawat gabi para sa base capacity na {$baseCap} guests.",
                        'Cebuano / Bisaya' => "Ang among {$name} kay {$baseFmt} matag gabii para sa base capacity nga {$baseCap} ka bisita.",
                    ]);
                    return [
                        'text' => $text,
                        'data' => $calc,
                        'follow_up' => null,
                    ];
                }

                // Guest count exceeds max adults capacity for that specific unit
                if ($guests !== null && !$calc['fits_guests']) {
                    $alternatives = $this->findFittingRooms($guests, $nights, $checkIn, $checkOut);
                    $altList = [];
                    foreach ($alternatives as $alt) {
                        $altList[] = "{$alt['display_name']} (₱" . number_format($alt['total_amount'], 0) . ($nights > 1 ? " for {$nights} nights" : " per night") . ")";
                    }
                    $altStr = !empty($altList) ? implode(' or ', array_slice($altList, 0, 2)) : 'multiple rooms';
                    $text = "Our {$name} can accommodate up to {$calc['max_adults_capacity']} adult guests. For {$guests} persons, we recommend our {$altStr}.";
                    return [
                        'text' => $text,
                        'data' => ['requested' => $calc, 'alternatives' => $alternatives],
                        'follow_up' => $checkIn === null ? 'Would you like me to check availability for your preferred dates?' : null,
                    ];
                }

                $totalFmt = '₱' . number_format($calc['total_amount'], 0);
                $depositFmt = '₱' . number_format($calc['deposit_amount'], 0);
                $extraNote = '';
                if ($calc['extra_adults'] > 0) {
                    $extraFmt = '₱' . number_format($calc['extra_person_total'], 0);
                    $extraNote = " (includes {$extraFmt} one-time additional guest fee for {$calc['extra_adults']} extra guest" . ($calc['extra_adults'] > 1 ? 's' : '') . ')';
                }

                if ($nights > 1) {
                    $text = $this->tr($language, [
                        'English' => "For {$estimateGuests} " . ($estimateGuests === 1 ? 'person' : 'persons') . " staying {$nights} nights in our {$name}, the estimated total is {$totalFmt}{$extraNote}, with a {$facts['deposit_pct']}% deposit of {$depositFmt}.",
                        'Tagalog' => "Para sa {$estimateGuests} katao sa loob ng {$nights} gabi sa {$name}, ang estimated total ay {$totalFmt}{$extraNote}, at ang {$facts['deposit_pct']}% deposit ay {$depositFmt}.",
                        'Cebuano / Bisaya' => "Para sa {$estimateGuests} ka tawo sulod sa {$nights} ka gabii sa {$name}, ang estimated total kay {$totalFmt}{$extraNote}, ug ang {$facts['deposit_pct']}% nga deposit kay {$depositFmt}.",
                    ]);
                    $followUp = $checkIn === null ? $this->tr($language, [
                        'English' => 'Would you like me to check availability for your preferred dates?',
                        'Tagalog' => 'Gusto po ba ninyong i-check ko ang availability para sa napili ninyong petsa?',
                        'Cebuano / Bisaya' => 'Gusto ba nimong i-check nako ang availability para sa imong napiling petsa?',
                    ]) : null;
                } else {
                    $text = $this->tr($language, [
                        'English' => "For {$estimateGuests} " . ($estimateGuests === 1 ? 'person' : 'persons') . ", our {$name} costs approximately {$totalFmt} for one night{$extraNote}.",
                        'Tagalog' => "Para sa {$estimateGuests} katao, ang {$name} ay humigit-kumulang {$totalFmt} para sa isang gabi{$extraNote}.",
                        'Cebuano / Bisaya' => "Para sa {$estimateGuests} ka tawo, ang {$name} kay mga {$totalFmt} para sa usa ka gabii{$extraNote}.",
                    ]);
                    $followUp = $context['nights'] === null ? $this->tr($language, [
                        'English' => 'How many nights will you be staying?',
                        'Tagalog' => 'Ilang gabi po kayo mananatili?',
                        'Cebuano / Bisaya' => 'Pila ka gabii mo magpuyo?',
                    ]) : null;
                }

                return ['text' => $text, 'data' => $calc, 'follow_up' => $followUp];
            }
        }

        // Case B: Guest specified a guest count (e.g., "How much for 5 persons?") without naming a specific room
        if ($guests !== null) {
            $fitting = $this->findFittingRooms($guests, $nights, $checkIn, $checkOut);
            if (empty($fitting)) {
                return [
                    'text' => "A single room accommodates up to 7 adult guests (Beach Villa), so a group of {$guests} persons will need 2 or more rooms.",
                    'data' => ['guests' => $guests, 'fitting' => []],
                    'follow_up' => 'Would you like me to recommend the best room combination for your dates?',
                ];
            }

            // Order prioritizing exact group matches first (e.g. Standard Family Room ₱5,000, Beach Villa ₱8,900)
            usort($fitting, function (array $a, array $b) {
                $aBaseMatch = ($a['base_capacity'] >= 4) ? 0 : 1;
                $bBaseMatch = ($b['base_capacity'] >= 4) ? 0 : 1;
                if ($aBaseMatch !== $bBaseMatch) {
                    return $aBaseMatch <=> $bBaseMatch;
                }
                return $a['total_amount'] <=> $b['total_amount'];
            });

            $sentences = [];
            $primary = $fitting[0];
            $primaryFmt = '₱' . number_format($primary['total_amount'], 0);
            $primaryExtra = $primary['extra_adults'] > 0 ? ', including the additional guest charge' : '';
            $durLabel = $nights > 1 ? "for {$nights} nights" : 'for one night';

            $sentences[] = "For {$guests} persons, our {$primary['display_name']} costs approximately {$primaryFmt} {$durLabel}{$primaryExtra}.";

            if (isset($fitting[1])) {
                $second = $fitting[1];
                $secondFmt = '₱' . number_format($second['total_amount'], 0);
                $sentences[] = "Our {$second['display_name']} is also available as an option at approximately {$secondFmt}" . ($nights > 1 ? " for {$nights} nights." : ' per night.');
            }

            return [
                'text' => implode(' ', $sentences),
                'data' => $fitting,
                'follow_up' => $checkIn === null ? 'Would you like me to check availability for your preferred dates?' : null,
            ];
        }

        // Case C: General room rate question
        $summary = [];
        foreach (self::CANONICAL_TYPES as $cSlug) {
            if (!empty($facts['accommodations'][$cSlug])) {
                $r = $facts['accommodations'][$cSlug];
                $summary[] = "{$r['display_name']} (₱" . number_format($r['base_price_per_night'], 0) . "/night, base {$r['base_capacity']})";
            }
        }
        return [
            'text' => 'Our nightly rates are: ' . implode(', ', $summary) . '.',
            'data' => $facts['accommodations'],
            'follow_up' => 'How many guests and which dates are you planning?',
        ];
    }

    /**
     * Build direct availability check answer without dumping unsolicited rooms or asking for already-provided info.
     */
    private function buildAvailabilityResponse(array $context, array $facts, string $language): array
    {
        $checkIn = $context['check_in'];
        $checkOut = $context['check_out'];
        $guests = $context['guests'];

        if ($checkIn === null && $guests === null) {
            return [
                'text' => $this->tr($language, [
                    'English' => 'I can check room availability for you! Which dates and how many guests will be staying?',
                    'Tagalog' => 'Maaari ko pong i-check ang availability para sa inyo! Anong petsa at ilang guests ang tutuloy?',
                    'Cebuano / Bisaya' => 'Pwede nako i-check ang availability para nimo! Unsang mga petsa ug pila ka bisita ang mo-stay?',
                ]),
                'data' => ['needs' => ['dates', 'guests']],
                'follow_up' => null,
            ];
        }

        if ($checkIn !== null && $guests === null) {
            $dateText = Carbon::parse($checkIn)->isTomorrow() ? 'tomorrow' : $checkIn;
            return [
                'text' => $this->tr($language, [
                    'English' => "I can check availability for {$dateText}! How many guests will be staying?",
                    'Tagalog' => "Pwede ko pong i-check ang bakante para sa {$dateText}! Ilang guests po ang tutuloy?",
                    'Cebuano / Bisaya' => "Pwede nako i-check ang bakante para sa {$dateText}! Pila ka bisita ang mo-stay?",
                ]),
                'data' => ['check_in' => $checkIn, 'check_out' => $checkOut, 'needs' => ['guests']],
                'follow_up' => null,
            ];
        }

        if ($checkIn === null && $guests !== null) {
            return [
                'text' => $this->tr($language, [
                    'English' => "I can check rooms for {$guests} guests! What are your preferred check-in and check-out dates?",
                    'Tagalog' => "Maaari ko pong i-check ang kwarto para sa {$guests} guests! Anong petsa ng check-in at check-out ang gusto ninyo?",
                    'Cebuano / Bisaya' => "Pwede nako i-check ang mga kwarto para sa {$guests} ka bisita! Unsang petsa sa check-in ug check-out?",
                ]),
                'data' => ['guests' => $guests, 'needs' => ['dates']],
                'follow_up' => null,
            ];
        }

        // Both dates and guest count known -> real DB availability check!
        $targetTypes = $context['room_slug'] !== null ? [$context['room_slug']] : self::CANONICAL_TYPES;
        $availableList = [];
        foreach ($targetTypes as $tSlug) {
            $est = $this->reservationService->calculateGuestAccommodationEstimate($tSlug, $guests, $context['nights'] ?? 1, $checkIn, $checkOut);
            if (!empty($est['success']) && $est['fits_guests'] && ($est['available_units'] ?? 0) > 0) {
                $availableList[] = $est;
            }
        }

        if (empty($availableList)) {
            return [
                'text' => "No single room fitting {$guests} guests is available from {$checkIn} to {$checkOut}. Would you like to check nearby dates or multiple rooms?",
                'data' => ['check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => $guests, 'available' => []],
                'follow_up' => null,
            ];
        }

        $items = [];
        foreach (array_slice($availableList, 0, 3) as $a) {
            $items[] = "{$a['display_name']} ({$a['available_units']} available, ₱" . number_format($a['total_amount'], 0) . " total)";
        }

        return [
            'text' => "Yes, for {$guests} guests ({$checkIn} to {$checkOut}), we have: " . implode('; ', $items) . '.',
            'data' => ['check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => $guests, 'available' => $availableList],
            'follow_up' => 'Would you like to proceed with reserving one of these?',
        ];
    }

    /**
     * Find canonical room types capable of hosting a given number of adult/total guests.
     */
    private function findFittingRooms(int $guests, int $nights, ?string $checkIn, ?string $checkOut): array
    {
        $fitting = [];
        foreach (self::CANONICAL_TYPES as $slug) {
            $est = $this->reservationService->calculateGuestAccommodationEstimate($slug, $guests, $nights, $checkIn, $checkOut);
            if (!empty($est['success']) && !empty($est['fits_guests'])) {
                $fitting[] = $est;
            }
        }
        return $fitting;
    }

    private function classifySpecificTopics(string $norm, array $context): array
    {
        $topics = [];

        if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening|maayong|magandang araw)[!\.\s]*$/i', $norm)) {
            return ['greeting'];
        }

        // Amenity sub-topics
        $amenityPatterns = [
            'pool'           => '/\b(swimming pool|pool)\b/',
            'aircon'         => '/\b(air conditioning|aircon|ac|airconditioned)\b/',
            'wifi'           => '/\b(wifi|internet)\b/',
            'breakfast'      => '/\b(breakfast|pamahaw|almusal)\b/',
            'restaurant'     => '/\b(restaurant|dining|food|bar|kainan)\b/',
            'pets'           => '/\b(pets?|dog|dogs|cat|cats|aso|pusa)\b/',
            'parking'        => '/\b(parking|car park|park)\b/',
            'motor'          => '/\b(motorcycle|scooter|motor|motorbike|bike rental)\b/',
            'island_hopping' => '/\b(island hopping|virgin island|tour|tours)\b/',
            'luggage'        => '/\b(luggage|baggage|bagahe|maleta|early arrival|leave bags)\b/',
        ];
        foreach ($amenityPatterns as $key => $regex) {
            if (preg_match($regex, $norm)) {
                $topics[] = 'amenity:' . $key;
            }
        }

        if (preg_match('/\b(check[\s\-]?in|checkin|check in time|oras ng check in)\b/', $norm)) {
            $topics[] = 'checkin_time';
        }
        if (preg_match('/\b(check[\s\-]?out|checkout|check out time)\b/', $norm)) {
            $topics[] = 'checkout_time';
        }

        if (preg_match('/\b(gcash)\b/', $norm)) {
            $topics[] = 'payment_gcash';
        } elseif (preg_match('/\b(payment|pay|maya|paymongo|card|bank|cash|mode of payment)\b/', $norm) && !preg_match('/\bdeposit\b/', $norm)) {
            $topics[] = 'payment_methods';
        }
        if (preg_match('/\b(deposit|downpayment|down payment)\b/', $norm)) {
            $topics[] = 'payment_deposit';
        }

        if (preg_match('/\bwalk[\s\-]?in\b/', $norm)) {
            $topics[] = 'walkin';
        }
        if (preg_match('/\b(cancel|cancellation|refund|rebook|reschedule)\b/', $norm)) {
            $topics[] = 'cancellation';
        }
        if (preg_match('/\b(senior|pwd|discount)\b/', $norm)) {
            $topics[] = 'discounts';
        }
        if (preg_match('/\b(where|location|directions?|address|port|pantalan|airport|ferry|how to get)\b/', $norm)) {
            $topics[] = 'directions';
        }

        $asksPrice = (bool) preg_match('/\b(how much|price|rate|rates|cost|costs|total|presyo)\b/', $norm);
        $isFollowupNumberOnly = !$topics && ($context['guests_in_current'] || $context['nights_in_current']) && $context['room_slug'] !== null;
        if ($asksPrice || $isFollowupNumberOnly) {
            $topics[] = 'pricing';
        }

        $asksAvailability = (bool) preg_match('/\b(available|availability|vacant|rooms? left|openings?)\b/', $norm);
        if ($asksAvailability && !in_array('pricing', $topics, true)) {
            $topics[] = 'availability';
        }

        if (empty($topics) && $context['dates_in_current']) {
            $topics[] = $context['room_slug'] !== null ? 'pricing' : 'availability';
        }

        if (empty($topics) && $context['room_slug'] !== null) {
            $topics[] = 'pricing';
        }

        return array_values(array_unique($topics ?: ['general']));
    }

    private function answerSpecificAmenity(string $item, ?string $roomSlug, string $language, array $facts): string
    {
        switch ($item) {
            case 'pool':
                return $this->tr($language, [
                    'English' => 'We do not have a swimming pool, as Santa Fe Beach Club offers direct beachfront swimming right along our calm white-sand shoreline.',
                    'Tagalog' => 'Wala po kaming swimming pool dahil beachfront ang Santa Fe Beach Club at may direktang access sa malinis at kalmadong dagat.',
                    'Cebuano / Bisaya' => 'Wala mi swimming pool kay beachfront ang Santa Fe Beach Club ug diretso ra sa limpyo ug kalmadong dagat.',
                ]);
            case 'aircon':
                $unitLabel = ($roomSlug !== null && !empty($facts['accommodations'][$roomSlug]['display_name']))
                    ? $facts['accommodations'][$roomSlug]['display_name']
                    : null;
                return $this->tr($language, [
                    'English' => $unitLabel ? "Yes, our {$unitLabel} includes air conditioning." : 'Yes, all accommodations include air conditioning.',
                    'Tagalog' => $unitLabel ? "Opo, may air conditioning ang aming {$unitLabel}." : 'Opo, may air conditioning ang lahat ng aming accommodations.',
                    'Cebuano / Bisaya' => $unitLabel ? "Oo, naay air conditioning ang among {$unitLabel}." : 'Oo, naay air conditioning ang tanang accommodations.',
                ]);
            case 'wifi':
                return $this->tr($language, [
                    'English' => 'Yes, complimentary Wi-Fi is available in all rooms and dining areas.',
                    'Tagalog' => 'Opo, may libreng Wi-Fi sa lahat ng kwarto at dining area.',
                    'Cebuano / Bisaya' => 'Oo, naay libreng Wi-Fi sa tanang kwarto ug dining area.',
                ]);
            case 'breakfast':
                if ($roomSlug !== null && isset(self::UNIT_SPECS[$roomSlug])) {
                    $name = self::UNIT_SPECS[$roomSlug]['display_name'];
                    return self::UNIT_SPECS[$roomSlug]['breakfast_included']
                        ? "Yes, breakfast is included with our {$name}."
                        : "Breakfast is not included in the {$name} base rate, though our beachfront restaurant serves daily breakfast.";
                }
                return 'Breakfast is included with Beachview Duplex, Seaview Duplex, and Beach Villa bookings, and is available a la carte at our beachfront restaurant for Standard accommodations.';
            case 'restaurant':
                return $this->tr($language, [
                    'English' => 'Yes, we have an on-site beachfront restaurant and bar.',
                    'Tagalog' => 'Opo, may sarili kaming beachfront restaurant at bar sa resort.',
                    'Cebuano / Bisaya' => 'Oo, naa mi beachfront restaurant ug bar sulod sa resort.',
                ]);
            case 'pets':
                return $this->tr($language, [
                    'English' => 'Pets are allowed in designated ground-level Beach Villa units with advance notice; please keep pets leashed in shared areas.',
                    'Tagalog' => 'Pinapayagan ang alagang hayop sa piling ground-level Beach Villa units kung may paunang abiso; panatilihing naka-leash sa shared areas.',
                    'Cebuano / Bisaya' => 'Pwede ang mga binuhi sa piling ground-level Beach Villa units basta magpahibalo daan; palihug ibutang sa leash sa shared areas.',
                ]);
            case 'parking':
                return 'Yes, designated parking areas are available on site for guests.';
            case 'motor':
                return 'Yes, motorcycle and scooter rentals can be arranged at reception.';
            case 'island_hopping':
                return 'Yes, we offer island hopping tour assistance (such as Virgin Island) through our reception desk.';
            case 'luggage':
                return 'Yes, you may store your luggage at reception and use beachfront areas if you arrive before 2:00 PM check-in.';
            default:
                return 'All units include air conditioning, private bathroom, complimentary Wi-Fi, and direct beachfront access.';
        }
    }

    private function buildCancellationDetailsText(array $context, string $language): string
    {
        if ($context['check_in'] !== null) {
            $nights = $context['nights'] ?? 1;
            $hours = $nights >= 7 ? 168 : ($nights >= 4 ? 120 : 48);
            $label = $nights >= 7 ? '7 days' : ($nights >= 4 ? '5 days' : '48 hours (2 days)');
            $deadline = Carbon::parse($context['check_in'])->setTime(14, 0)->subHours($hours)->format('M j, Y g:i A');
            return "For a {$nights}-night stay starting {$context['check_in']}, free 100% cancellation is available until {$deadline} ({$label} before 2:00 PM check-in); after that deadline, the booking is non-refundable.";
        }

        return 'For 1–3 nights, cancel at least 48 hours (2 days) before 2:00 PM check-in for a 100% refund; 4–6 nights require 5 days notice, and 7+ nights require 7 days notice. Cancellations after the deadline are non-refundable.';
    }

    private function combinePartsCleanly(array $topics, array $parts, ?string $followUp, string $language): string
    {
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));

        // Smooth multi-question pairing like "How much is your villa and does it have aircon?"
        if (in_array('pricing', $topics, true) && count($parts) >= 2 && str_starts_with((string) $topics[0], 'amenity:')) {
            $amenityPart = array_shift($parts);
            $pricePart = array_shift($parts);
            $amenityBody = preg_replace('/^(Yes|Opo|Oo),?\s+/i', '', $amenityPart) ?? $amenityPart;
            $text = rtrim($pricePart, '. ') . ', and yes, ' . lcfirst(rtrim($amenityBody, '. ')) . '.';
        } elseif (in_array('pricing', $topics, true) && count($parts) >= 2 && str_starts_with((string) ($topics[1] ?? ''), 'amenity:')) {
            $pricePart = $parts[0];
            $amenityPart = $parts[1];
            $amenityBody = preg_replace('/^(Yes|Opo|Oo),?\s+/i', '', $amenityPart) ?? $amenityPart;
            $text = rtrim($pricePart, '. ') . ', and yes, ' . lcfirst(rtrim($amenityBody, '. ')) . '.';
        } else {
            $text = implode(' ', $parts);
        }

        if ($text === '') {
            $text = $this->tr($language, [
                'English' => "I'm not completely sure about that specific request. Please call reception at 0950 522 3146 or message us on the Contact page so our staff can assist you directly.",
                'Tagalog' => 'Hindi po ako sigurado sa partikular na detalye na iyan. Maaari pong tumawag sa reception sa 0950 522 3146 o mag-message sa Contact page para matulungan kayo agad.',
                'Cebuano / Bisaya' => 'Dili ko sigurado ana nga detalye. Palihug og tawag sa reception sa 0950 522 3146 o pag-message sa Contact page aron matabangan ka dayon.',
            ]);
            return $text;
        }

        if ($followUp !== null && trim($followUp) !== '' && !str_ends_with($text, '?')) {
            $text .= "\n\n" . trim($followUp);
        }

        return $text;
    }

    private function detectRoomMention(string $norm, bool $isModelTurn = false): ?string
    {
        // Only infer room from a model message if that model turn clearly discussed a single room type
        if ($isModelTurn) {
            $matches = [];
            foreach (['beach_villa' => 'beach villa', 'beachview_duplex' => 'beachview duplex', 'seaview_duplex' => 'seaview duplex', 'standard_king' => 'standard family room', 'standard_room' => 'standard room', 'cottage' => 'cottage'] as $slug => $kw) {
                if (str_contains($norm, $kw)) {
                    $matches[] = $slug;
                }
            }
            return count($matches) === 1 ? $matches[0] : null;
        }

        if (preg_match('/\b(beach\s*view|beachview)\b/', $norm)) {
            return 'beachview_duplex';
        }
        if (preg_match('/\b(sea\s*view|seaview)\b/', $norm)) {
            return 'seaview_duplex';
        }
        if (preg_match('/\b(standard\s*family|family\s*room|standard\s*king|king)\b/', $norm)) {
            return 'standard_king';
        }
        if (preg_match('/\bstandard\s*room\b/', $norm)) {
            return 'standard_room';
        }
        if (preg_match('/\b(beach\s*villa|villa)\b/', $norm)) {
            return 'beach_villa';
        }
        if (preg_match('/\bduplex\b/', $norm)) {
            return 'beachview_duplex';
        }
        if (preg_match('/\bcottage\b/', $norm)) {
            return 'cottage';
        }

        return null;
    }

    private function extractGuestCount(string $norm): ?int
    {
        if (preg_match('/\b(\d{1,2})\s*(?:ka\s+)?(?:persons?|guests?|adults?|katao)\b/', $norm, $m)) {
            return max(1, (int) $m[1]);
        }
        if (preg_match('/\bfor\s+(\d{1,2})\b(?!\s*nights?)/', $norm, $m)) {
            return max(1, (int) $m[1]);
        }
        return null;
    }

    private function extractNightsCount(string $norm): ?int
    {
        if (preg_match('/\b(\d{1,2})\s*(?:ka\s+)?nights?\b/', $norm, $m)) {
            return max(1, (int) $m[1]);
        }
        return null;
    }

    private function extractDates(string $norm): ?array
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})\s*(?:to|-|until|through)\s*(\d{4}-\d{2}-\d{2})/', $norm, $m)) {
            $s = Carbon::parse($m[1]);
            $e = Carbon::parse($m[2]);
            if ($e->greaterThan($s)) {
                return [$s->toDateString(), $e->toDateString()];
            }
        }
        if (preg_match('/\btomorrow\b/', $norm)) {
            $s = Carbon::tomorrow();
            return [$s->toDateString(), $s->copy()->addDay()->toDateString()];
        }
        if (preg_match('/\btoday\b/', $norm)) {
            $s = Carbon::today();
            return [$s->toDateString(), $s->copy()->addDay()->toDateString()];
        }
        return null;
    }

    private function tr(string $language, array $map): string
    {
        return $map[$language] ?? $map['English'];
    }
}
