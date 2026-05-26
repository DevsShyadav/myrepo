<?php
/**
 * Groq AI Integration
 * Personalized message generation engine
 * 
 * Handles:
 * - Prompt building with lead data
 * - Language adaptation based on region
 * - Website/no-website branching (Type A / Type B)
 * - Service selection intelligence
 * - Anti-spam messaging
 * - Fallback generation
 */

/**
 * Generate personalized outreach message for a lead
 * 
 * @param array $lead Lead data from database
 * @return array ['message' => string, 'reasoning' => string, 'success' => bool]
 */
function generateOutreachMessage($lead) {
    $apiKey = getSetting('groq_api_key', GROQ_API_KEY);
    $model = getSetting('groq_model', GROQ_MODEL);
    
    if (empty($apiKey)) {
        writeLog('Groq API key not configured', 'ERROR');
        return [
            'success' => false,
            'message' => generateFallbackMessage($lead),
            'reasoning' => 'Fallback: API key not configured'
        ];
    }
    
    $prompt = buildPrompt($lead);
    
    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => getSystemPrompt()
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => GROQ_MAX_TOKENS,
        'temperature' => GROQ_TEMPERATURE,
        'top_p' => 0.9,
        'stream' => false
    ];
    
    $ch = curl_init(GROQ_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error || $httpCode !== 200) {
        writeLog("Groq API error: HTTP {$httpCode}, Error: {$error}", 'ERROR');
        return [
            'success' => false,
            'message' => generateFallbackMessage($lead),
            'reasoning' => "API error: HTTP {$httpCode}"
        ];
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        writeLog('Groq API: Invalid response structure', 'ERROR');
        return [
            'success' => false,
            'message' => generateFallbackMessage($lead),
            'reasoning' => 'Invalid API response'
        ];
    }
    
    $generatedMessage = trim($result['choices'][0]['message']['content']);
    
    // Clean up any markdown or extra formatting
    $generatedMessage = preg_replace('/^```[\w]*\n?/', '', $generatedMessage);
    $generatedMessage = preg_replace('/\n?```$/', '', $generatedMessage);
    $generatedMessage = trim($generatedMessage);
    
    $reasoning = buildReasoning($lead);
    
    logActivity('success', 'ai', "Message generated for: {$lead['business_name']}", [
        'lead_id' => $lead['id'],
        'pitch_type' => $lead['pitch_type'],
        'language' => $lead['language_preference']
    ]);
    
    return [
        'success' => true,
        'message' => $generatedMessage,
        'reasoning' => $reasoning
    ];
}

/**
 * Build the system prompt for Groq
 * 
 * @return string System prompt
 */
function getSystemPrompt() {
    return "You are Shubham — a young, sharp Indian freelance developer who personally reaches out to local businesses via WhatsApp. You write like a real human typing a message, NOT like an AI or a marketing bot.

YOUR PERSONALITY:
- You're 24, based in India, passionate about helping local businesses grow digitally
- You type casually, like messaging a friend-of-a-friend about a business idea
- You use natural pauses, short sentences, sometimes incomplete thoughts
- You're genuinely curious about their business, not salesy
- You notice SPECIFIC things about their business (rating, location, type) and reference them naturally
- You NEVER sound like a template or mass message

STRICT RULES:
- NEVER start with 'Hi [Business Name] team' — that's a dead giveaway of automation
- Start with something observational or casual like 'Hey!' or 'Hi!' then immediately show you know something specific about THEM
- NEVER use the word 'leverage', 'synergy', 'optimize', 'streamline', or corporate jargon
- NEVER mention pricing, packages, offers, or discounts
- NEVER use bullet points or numbered lists
- NEVER say 'I noticed' — instead SHOW what you noticed through your message naturally
- NEVER list multiple services — mention maximum ONE specific thing you could help with
- Keep it to 3-4 SHORT paragraphs (2-3 lines each max)
- End with a super casual question, not a CTA like 'Would you be interested?'
- Use 1-2 emojis naturally, not plastered everywhere
- Each message MUST feel like it could only be written for THIS specific business

YOUR SERVICES (only hint at ONE relevant one, never name it formally):
- Building websites/landing pages
- Making existing websites convert better
- WhatsApp automation for customer handling
- AI chatbots for enquiries
- Custom apps
- Digital marketing/visibility

WHAT MAKES YOUR MESSAGES SPECIAL:
- You reference the EXACT locality/area they're in
- You mention their rating/reviews as social proof THEY built
- You connect their business type to a specific digital opportunity
- You write like you personally stumbled upon their business
- The reader should think 'this guy actually looked at my business'

OUTPUT: ONLY the raw WhatsApp message. Nothing else. No quotes, no labels, no explanations.";}


/**
 * Build personalized prompt for a lead
 * 
 * @param array $lead Lead data
 * @return string Prompt
 */
function buildPrompt($lead) {
    $businessName = $lead['business_name'];
    $locality = $lead['locality'] ?? '';
    $city = $lead['city'] ?? '';
    $state = $lead['state'] ?? '';
    $rating = $lead['rating'] ?? '';
    $reviews = $lead['review_count'] ?? 0;
    $websiteStatus = $lead['website_status'];
    $pitchType = $lead['pitch_type'];
    $language = $lead['language_preference'];
    
    $location = implode(', ', array_filter([$locality, $city, $state]));
    
    $prompt = "Write a WhatsApp message to this business owner as Shubham:\n\n";
    $prompt .= "═══ BUSINESS DATA ═══\n";
    $prompt .= "Name: {$businessName}\n";
    $prompt .= "Area: {$locality}\n";
    $prompt .= "City: {$city}\n";
    $prompt .= "State: {$state}\n";
    
    if (!empty($rating)) {
        $prompt .= "Google Rating: {$rating}/5 stars";
        if ($reviews > 0) {
            $prompt .= " with {$reviews} reviews";
            if ($reviews > 500) {
                $prompt .= " (that's SERIOUSLY impressive — top-tier trust)";
            } elseif ($reviews > 100) {
                $prompt .= " (that's impressive for a local business!)";
            }
        }
        $prompt .= "\n";
    }
    
    $prompt .= "Has Website: " . ($websiteStatus === 'has_website' ? 'Yes' : 'No') . "\n";
    $prompt .= "\n";
    
    // Deep pitch context
    $prompt .= "═══ YOUR ANGLE ═══\n";
    if ($pitchType === 'type_a') {
        $prompt .= "They HAVE a website already. So DON'T pitch them a website.\n";
        $prompt .= "Instead, think about:\n";
        $prompt .= "- Their website probably doesn't have WhatsApp chat integration\n";
        $prompt .= "- They probably handle enquiries manually\n";
        $prompt .= "- They probably don't have automated follow-ups\n";
        $prompt .= "- Their customers probably can't book/enquire easily\n";
        $prompt .= "- With {$reviews} reviews, they have traffic but maybe poor conversion\n";
        $prompt .= "Pick ONE specific gap and mention it naturally — like you personally visited their setup.\n";
    } else {
        $prompt .= "They have NO website. In 2024, that's a massive missed opportunity.\n";
        $prompt .= "Think about:\n";
        $prompt .= "- People searching for businesses like theirs on Google find competitors\n";
        $prompt .= "- They rely ONLY on word-of-mouth and walk-ins\n";
        $prompt .= "- A simple landing page could capture leads 24/7\n";
        $prompt .= "- Their {$rating}/5 rating shows quality but nobody online can find them easily\n";
        $prompt .= "Position it as: 'You're clearly good at what you do, but online visibility is missing.'\n";
    }
    
    $prompt .= "\n═══ LANGUAGE ═══\n";
    switch ($language) {
        case 'hinglish':
            $prompt .= "Write in natural Hinglish — the way a young Indian professional actually texts.\n";
            $prompt .= "Mix Hindi (Roman script) and English fluidly. Example tone:\n";
            $prompt .= "'Hey! Maine aapka {$locality} wala setup dekha — {$rating} rating pe {$reviews} reviews, matlab log trust karte hain aap pe.'\n";
            $prompt .= "Keep it breezy, like you're genuinely impressed and just wanted to say hi.";
            break;
        case 'gujarati_english':
            $prompt .= "Write in English mixed with light Gujarati words/phrases.\n";
            $prompt .= "Tone: Warm, respectful, business-minded. Like talking to a fellow Gujarati entrepreneur.\n";
            $prompt .= "Can use words like 'bhai', 'tamara business', 'mast', 'idea share karvu htu'";
            break;
        case 'marathi_english':
            $prompt .= "Write in English mixed with Marathi conversational phrases.\n";
            $prompt .= "Tone: Friendly but professional. Like a young Pune/Mumbai professional reaching out.\n";
            $prompt .= "Can use words like 'tumcha business', 'ekdum solid', 'ek idea hota'";
            break;
        default:
            $prompt .= "Write in simple, warm English. Not formal. Not corporate. Like texting a potential business contact you respect.";
            break;
    }
    
    $prompt .= "\n\n═══ STRUCTURE (follow loosely, not rigidly) ═══\n";
    $prompt .= "1. Casual opener that IMMEDIATELY shows you know their specific business/area\n";
    $prompt .= "2. One genuine observation about their digital presence or opportunity\n";
    $prompt .= "3. One specific thing you could help with (hint, don't pitch formally)\n";
    $prompt .= "4. Super casual close — a question that invites response without pressure\n";
    $prompt .= "\nKEEP IT SHORT. 3-4 paragraphs MAX. No paragraph longer than 3 lines.";
    
    return $prompt;
}

/**
 * Build reasoning text for AI decision
 * 
 * @param array $lead Lead data
 * @return string Reasoning
 */
function buildReasoning($lead) {
    $reasons = [];
    $reasons[] = "Pitch Type: " . ($lead['pitch_type'] === 'type_a' ? 'Type A (Has Website)' : 'Type B (No Website)');
    $reasons[] = "Language: " . ucfirst(str_replace('_', ' ', $lead['language_preference']));
    $reasons[] = "Location: " . implode(', ', array_filter([$lead['locality'], $lead['city'], $lead['state']]));
    
    if (!empty($lead['rating'])) {
        $reasons[] = "Rating: {$lead['rating']}/5" . ($lead['review_count'] > 0 ? " ({$lead['review_count']} reviews)" : '');
    }
    
    if ($lead['pitch_type'] === 'type_a') {
        $reasons[] = "Angle: Optimization & growth of existing digital presence";
    } else {
        $reasons[] = "Angle: Building new digital presence from scratch";
    }
    
    return implode("\n", $reasons);
}

/**
 * Generate fallback message when AI is unavailable
 * Uses actual lead data for deep personalization
 * 
 * @param array $lead Lead data
 * @return string Fallback message
 */
function generateFallbackMessage($lead) {
    $name = $lead['business_name'] ?? 'Business';
    $locality = $lead['locality'] ?? '';
    $city = $lead['city'] ?? '';
    $state = $lead['state'] ?? '';
    $rating = $lead['rating'] ?? '';
    $reviews = $lead['review_count'] ?? 0;
    $language = $lead['language_preference'] ?? 'hinglish';
    $hasWebsite = ($lead['website_status'] ?? '') === 'has_website';
    
    $location = $locality ?: $city;
    $ratingText = '';
    if (!empty($rating) && $reviews > 0) {
        $ratingText = "{$rating} rating aur {$reviews} reviews";
    } elseif (!empty($rating)) {
        $ratingText = "{$rating} rating";
    }
    
    if ($language === 'hinglish') {
        if ($hasWebsite) {
            // Type A - Has Website - Hinglish
            $msg = "Hey! Maine aapka {$name} ka profile dekha";
            if (!empty($location)) $msg .= " — {$location} area mein";
            $msg .= ".";
            if (!empty($ratingText)) {
                $msg .= " {$ratingText} ke saath kaafi solid trust build kiya hai aapne.";
            }
            $msg .= "\n\nWebsite bhi hai aapki, jo achhi baat hai. Lekin maine dekha ki aaj kal jo businesses apni website pe WhatsApp automation ya AI-based enquiry handling add kar rahe hain, unka conversion kaafi improve ho raha hai.";
            $msg .= "\n\nMujhe laga ki aap jaise established business ke liye ek simple digital upgrade kaafi farak la sakta hai — jaise automated customer handling ya better lead capture.";
            $msg .= "\n\nAgar interest ho to main ek short idea share kar sakta hoon — bilkul no pressure, sirf ek thought hai.";
            return $msg;
        } else {
            // Type B - No Website - Hinglish
            $msg = "Hey! {$name} ke baare mein Google pe dekha";
            if (!empty($location)) $msg .= " — {$location} mein";
            $msg .= ".";
            if (!empty($ratingText)) {
                $msg .= " {$ratingText} matlab log trust karte hain aap pe, quality hai aapki service mein.";
            }
            $msg .= "\n\nEk cheez notice ki — abhi aapki koi website ya landing page nahi hai. Matlab jo log Google pe search kar rahe hain aapke type ka business, wo aapko nahi mil pa rahe.";
            $msg .= "\n\nEk simple professional landing page se enquiries aa sakti hain 24/7 — bina kisi extra effort ke. Maine similar businesses ke liye ye kiya hai aur result kaafi accha raha.";
            $msg .= "\n\nKya aap interested honge ek quick chat ke liye? Sirf 2 min mein idea samjha dunga.";
            return $msg;
        }
    }
    
    // English fallback
    if ($hasWebsite) {
        $msg = "Hi! Came across {$name}";
        if (!empty($location)) $msg .= " in {$location}";
        $msg .= ".";
        if (!empty($ratingText)) $msg .= " {$ratingText} — that's solid trust you've built.";
        $msg .= "\n\nSaw you have a website already, which is great. One thing I've seen work well for businesses at your stage is adding WhatsApp automation or AI-based enquiry handling — helps convert more visitors into actual customers.";
        $msg .= "\n\nI work on exactly this kind of digital upgrade for local businesses. Nothing complex, just practical improvements that actually move the needle.";
        $msg .= "\n\nWould you be open to hearing a quick idea? No pitch, just a thought that might be relevant.";
        return $msg;
    } else {
        $msg = "Hi! Found {$name}";
        if (!empty($location)) $msg .= " in {$location}";
        $msg .= " on Google.";
        if (!empty($ratingText)) $msg .= " {$ratingText} — clearly doing great work.";
        $msg .= "\n\nNoticed you don't have a website yet. In today's market, a simple landing page could help you capture enquiries from people searching for your kind of service online — 24/7, even when you're busy.";
        $msg .= "\n\nI help local businesses set up clean, professional web presence. Simple, affordable, and designed to actually bring in customers.";
        $msg .= "\n\nWould a quick 2-min chat be worth your time? Just wanted to share an idea.";
        return $msg;
    }
}

/**
 * Select relevant services based on lead data
 * 
 * @param array $lead Lead data
 * @return array Selected services (max 2)
 */
function selectServices($lead) {
    $typeA = ['Custom Web Apps', 'AI Agents & Chatbots', 'Automation Systems', 'Digital Marketing'];
    $typeB = ['Landing Pages', 'Business Websites', 'eCommerce Websites', 'Digital Marketing'];
    
    $pool = $lead['pitch_type'] === 'type_a' ? $typeA : $typeB;
    shuffle($pool);
    
    return array_slice($pool, 0, 2);
}
