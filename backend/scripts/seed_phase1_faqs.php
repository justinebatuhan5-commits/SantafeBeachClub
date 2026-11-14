<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

$faqs = [
    // Accommodations
    [
        'category' => 'accommodations',
        'language' => 'English',
        'question' => 'What is the difference between Beachview Duplex, Seaview Duplex, and Beach Villa?',
        'answer' => "Santa Fe Beach Club offers 5 distinct accommodation types:\n- **Beachview Duplex (₱6,900/night)**: Accommodates 2 guests (max +1 extra adult at +₱1,000/stay) with direct beachfront and garden view.\n- **Seaview Duplex (₱7,900/night)**: Accommodates 2 guests (max +1 extra adult at +₱1,000/stay) with elevated panoramic ocean view.\n- **Beach Villa (₱7,900/night)**: Spacious standalone villa for up to 4 guests (max +3 extra adults at +₱1,000 each/stay), perfect for families.\n- **Standard Family Room (₱4,300/night)**: Accommodates 4 guests (max +1 extra adult at +₱700/stay).\n- **Standard Room (₱3,450/night)**: Cozy room for 2 guests (max +3 extra adults at +₱700 each/stay).\nAll units include air conditioning, private bathroom, hot shower, and beachfront access."
    ],
    [
        'category' => 'accommodations',
        'language' => 'English',
        'question' => 'Can we add extra persons or extra beds to our room and what are the fees?',
        'answer' => "Yes, extra persons are welcomed up to the room's maximum capacity limit:\n- **Duplex & Beach Villa units**: Extra adult fee is **₱1,000** per adult for the entire stay.\n- **Standard Room & Standard Family units**: Extra adult fee is **₱700** per adult for the entire stay.\nExtra person fees include extra bedding, linens, and resort amenities access."
    ],
    [
        'category' => 'accommodations',
        'language' => 'Tagalog',
        'question' => 'Magkano ang bayad para sa extra person at pwede ba magdagdag ng kama?',
        'answer' => "Opo! Pwedeng magdagdag ng extra guests hanggang sa maximum capacity ng bawat kwarto:\n- Sa **Beachview Duplex, Seaview Duplex, at Beach Villa**: **₱1,000** bawat extra adult para sa buong stay.\n- Sa **Standard Room at Standard Family**: **₱700** bawat extra adult para sa buong stay.\nKasama na rito ang extra bedding, tuwalya, at access sa beachfront facilities."
    ],
    [
        'category' => 'accommodations',
        'language' => 'Cebuano / Bisaya',
        'question' => 'Pila ang bayad sa extra person ug pwede ba magdugang ug higdaanan?',
        'answer' => "Oo, pwede kaayo magdugang ug extra guests subay sa capacity sa kwarto:\n- Sa **Beachview Duplex, Seaview Duplex, ug Beach Villa**: **₱1,000** matag extra adult para sa tibuok stay.\n- Sa **Standard Room ug Standard Family**: **₱700** matag extra adult para sa tibuok stay.\nApil na niini ang dugang foam/bedding ug tualya."
    ],

    // Early arrival & luggage storage
    [
        'category' => 'policies',
        'language' => 'English',
        'question' => 'Can we store our luggage or use resort facilities if we arrive early before 2:00 PM check-in?',
        'answer' => "Yes! If you arrive in Santa Fe before the standard 2:00 PM check-in time, you may store your bags for **free at the front desk**. You are welcome to relax by the beachfront, lounge in our garden areas, or dine at our on-site restaurant while your room is prepared. If your room happens to be vacant and cleaned early, early check-in is accommodated subject to availability."
    ],
    [
        'category' => 'policies',
        'language' => 'Tagalog',
        'question' => 'Pwede ba mag-iwan ng bagahe kung maaga kaming dumating bago ang 2:00 PM check-in?',
        'answer' => "Opo! Libreng maiiwan ang inyong mga bagahe sa front desk kung maaga kayong makarating bago mag-2:00 PM. Pwede na kayong mag-relax sa beachfront, maglakad-lakad, o kumain sa aming restaurant habang inihahanda ang inyong kwarto. Kapag bakante at handa na ang kwarto, maaari kayong bigyan ng early check-in nang walang dagdag na bayad."
    ],
    [
        'category' => 'policies',
        'language' => 'Cebuano / Bisaya',
        'question' => 'Pwede ba magbilin ug maleta kung sayo mi maabot sa dili pa ang 2:00 PM check-in?',
        'answer' => "Oo! Libre ra kaayo ninyo mabilin ang inyong mga gamit ug maleta sa atong front desk kung sayo mo maabot. Pwede na mo mag-tambay sa beachfront o mokaon sa atong restaurant samtang gipanglimpyohan pa ang inyong kwarto."
    ],

    // Pet Policy
    [
        'category' => 'policies',
        'language' => 'English',
        'question' => 'Are pets allowed at Santa Fe Beach Club?',
        'answer' => "Pets are allowed in designated ground-level accommodations (such as our Beach Villas) upon prior request and management confirmation. Pet owners are kindly requested to bring their own pet beddings, keep pets on a leash in public resort areas, and ensure cleanliness at all times. Please inform us when booking if you intend to bring your furry companion!"
    ],
    [
        'category' => 'policies',
        'language' => 'Tagalog',
        'question' => 'Pwede ba magdala ng alagang aso o pusa sa resort?',
        'answer' => "Pinapayagan ang mga alagang hayop (pets) sa mga piling kwarto tulad ng Beach Villa basta't ipagbigay-alam sa front desk bago mag-check in. Hinihiling po namin na magdala ng sariling pet bed, itali sa mga pampublikong lugar, at panatilihin ang kalinisan sa resort grounds."
    ],

    // Amenities & Activities
    [
        'category' => 'amenities',
        'language' => 'English',
        'question' => 'Does the resort have free Wi-Fi, restaurant, and tour or motorcycle rentals?',
        'answer' => "Yes! Santa Fe Beach Club provides:\n- **Free High-Speed Wi-Fi** in all guest rooms, lobby, and beachfront dining area.\n- **Beachfront Restaurant & Bar** offering daily breakfast, fresh local seafood, refreshing shakes, and tropical cocktails.\n- **Motorcycle / Scooter Rental** assistance to explore Bantayan Island, Kota Beach, Ogtong Cave, and Virgin Island.\n- **Island Hopping & Boat Tour Assistance** directly coordinated at our reception counter."
    ],
    [
        'category' => 'amenities',
        'language' => 'Tagalog',
        'question' => 'Mayroon bang libreng Wi-Fi, restaurant, at arkila ng motor o island hopping tours?',
        'answer' => "Opo! Kumpleto ang amenities sa Santa Fe Beach Club:\n- **Libreng Wi-Fi** sa lahat ng kwarto at beachfront restaurant.\n- **Beachfront Restaurant at Bar** na naghahain ng masasarap na almusal, sariwang seafood, at inumin.\n- **Motorcycle / Scooter Rental** para makapaglibot sa buong Bantayan Island.\n- **Island Hopping Tour assistance** papuntang Virgin Island at mga white sand beaches."
    ],
    [
        'category' => 'amenities',
        'language' => 'Cebuano / Bisaya',
        'question' => 'Naa bay libreng Wi-Fi, restaurant, ug motor rental o island hopping sa resort?',
        'answer' => "Oo! Kompleto ang kalingawan sa Santa Fe Beach Club:\n- **Libreng kusog nga Wi-Fi** sa tanang kwarto ug kan-anan.\n- **Beachfront Restaurant ug Bar** para sa lamiang pamahaw ug lab-as nga seafood.\n- **Motorcycle / Scooter Rental** para makalaag sa tibuok Isla sa Bantayan.\n- **Island Hopping tour assistance** padulong sa Virgin Island ug uban pang nindot nga beaches."
    ],

    // Port Transfer / Transportation
    [
        'category' => 'directions',
        'language' => 'English',
        'question' => 'How far is the resort from Santa Fe Port and Bantayan Airport?',
        'answer' => "Santa Fe Beach Club is conveniently located along the Pantalan shoreline in Santa Fe, Bantayan Island:\n- **From Santa Fe Port**: Approximately 3 to 5 minutes by motorized tricycle or habal-habal (fares are typically ₱25–₱50 per person).\n- **From Bantayan Airport**: Around 15 to 20 minutes by tricycle.\nIf you require assistance coordinating port arrival pickup, feel free to call our reception desk at **0950 522 3146**."
    ],
    [
        'category' => 'directions',
        'language' => 'Cebuano / Bisaya',
        'question' => 'Unsa ka layo ang resort gikan sa Santa Fe Port ug unsaon pag-adto?',
        'answer' => "Duol ra kaayo ang Santa Fe Beach Club:\n- Gikan sa **Santa Fe Port**, mga 3 hangtod 5 minutos ra sakay ug tricycle o habal-habal (mga ₱25–₱50 matag pasahero).\n- Gikan sa **Bantayan Airport**, mga 15–20 minutos sakay ug tricycle.\nPwede usab mo motawag sa atong front desk sa **0950 522 3146** kung magpatabang mo sa inyong pagsugat sa pantalan."
    ],

    // Payment & Security
    [
        'category' => 'payment',
        'language' => 'English',
        'question' => 'What payment methods do you accept for the 50% deposit and remaining balance?',
        'answer' => "We accept convenient, secure digital and in-person payments:\n- **GCash & Maya**: Send directly or scan our official QR code (Account: 0950 522 3146).\n- **PayMongo Online**: Direct credit/debit card and e-wallet checkout.\n- **Bank Deposit / Fund Transfer**: BDO or BPI account details provided on checkout.\n- **Cash / Over-the-counter**: Accepted at our resort reception for the remaining 50% balance upon check-in.\nRemember: 50% deposit confirms your reservation, and the remaining 50% balance is settled 24 hours before your check-in time."
    ]
];

$countAdded = 0;
foreach ($faqs as $faq) {
    // Check if question exists
    $stmt = $conn->prepare("SELECT id FROM chatbot_knowledge WHERE question = ? AND language = ? LIMIT 1");
    $stmt->bind_param('ss', $faq['question'], $faq['language']);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        $ins = $conn->prepare("INSERT INTO chatbot_knowledge (category, language, question, answer, is_active, updated_by) VALUES (?, ?, ?, ?, 1, 'system')");
        $ins->bind_param('ssss', $faq['category'], $faq['language'], $faq['question'], $faq['answer']);
        $ins->execute();
        $ins->close();
        $countAdded++;
    }
}

echo "Successfully seeded {$countAdded} Phase 1 Essential FAQs.\n";
