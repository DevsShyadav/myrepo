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
    return "You are an expert business development assistant who writes highly personalized WhatsApp outreach messages for a digital services company.

RULES:
- Write in the specified language style
- Keep messages 4-5 short paragraphs
- Use a warm, professional, human tone
- Never mention pricing or packages
- Never use fake urgency or pressure tactics
- Never list all services - only mention 1-2 most relevant ones
- Make it feel like a personally written message, not a template
- Include a soft, no-pressure CTA at the end
- Use appropriate emojis sparingly (1-2 max)
- The message must feel like it was typed by a real person
- Never start with 'Dear Sir/Madam' or formal openings
- Start casually, like messaging a potential business connection

SERVICES AVAILABLE (only mention relevant ones):
- Landing Pages
- Business Websites
- eCommerce Websites
- Custom Web Apps
- AI Agents & Chatbots
- Automation Systems
- Android Apps
- Chrome Extensions
- Digital Marketing

OUTPUT: Only the message text. No subject lines, no labels, no explanations.";
}

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
    
    $prompt = "Generate a personalized WhatsApp outreach message for:\n\n";
    $prompt .= "Business: {$businessName}\n";
    $prompt .= "Location: {$location}\n";
    
    if (!empty($rating)) {
        $prompt .= "Google Rating: {$rating}/5";
        if ($reviews > 0) {
            $prompt .= " ({$reviews} reviews)";
        }
        $prompt .= "\n";
    }
    
    $prompt .= "Website: " . ($websiteStatus === 'has_website' ? 'Yes (has existing website)' : 'No website') . "\n";
    $prompt .= "\n";
    
    // Pitch type instructions
    if ($pitchType === 'type_a') {
        $prompt .= "PITCH ANGLE (Has Website - Type A):\n";
        $prompt .= "- They already have online presence\n";
        $prompt .= "- Focus on: conversion optimization, AI automation, CRM, WhatsApp integration, growth\n";
        $prompt .= "- Suggest improvements/additions, not replacements\n";
        $prompt .= "- Pick 1-2 relevant services only\n";
    } else {
        $prompt .= "PITCH ANGLE (No Website - Type B):\n";
        $prompt .= "- They lack digital presence\n";
        $prompt .= "- Focus on: building online presence, landing pages, business websites, local discoverability\n";
        $prompt .= "- Show opportunity they're missing\n";
        $prompt .= "- Pick 1-2 relevant services only\n";
    }
    
    $prompt .= "\n";
    
    // Language instruction
    $prompt .= "LANGUAGE STYLE: ";
    switch ($language) {
        case 'hinglish':
            $prompt .= "Write in Hinglish (Hindi words in Roman script mixed with English). Example: 'Aapka business kaafi accha chal raha hai...' Keep it natural and conversational.";
            break;
        case 'gujarati_english':
            $prompt .= "Write in a mix of simple English with occasional Gujarati-friendly phrases. Keep it warm and business-friendly. Example: 'Tamara business mate ek idea share karvu htu...'";
            break;
        case 'marathi_english':
            $prompt .= "Write in a mix of English with Marathi-friendly conversational tone. Keep professional but approachable. Example: 'Tumchya business baaddal ek suggestion hota...'";
            break;
        default:
            $prompt .= "Write in simple, clear business English. Keep it conversational, not formal.";
            break;
    }
    
    $prompt .= "\n\nMESSAGE STRUCTURE:\n";
    $prompt .= "1. Opening: Local/trust observation (mention their area/rating naturally)\n";
    $prompt .= "2. Digital observation specific to their business\n";
    $prompt .= "3. Tailored opportunity/idea\n";
    $prompt .= "4. Relevant service mention (1-2 only)\n";
    $prompt .= "5. Soft CTA (no pressure)\n";
    
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
 * 
 * @param array $lead Lead data
 * @return string Fallback message
 */
function generateFallbackMessage($lead) {
    $name = $lead['business_name'];
    $city = $lead['city'] ?? '';
    $language = $lead['language_preference'];
    
    if ($language === 'hinglish') {
        if ($lead['pitch_type'] === 'type_b') {
            return "Hi! Main {$city} mein local businesses ke liye digital solutions provide karta hoon. {$name} ke baare mein socha ki aapki online presence aur strong ho sakti hai. Ek simple website ya landing page se kaafi enquiries aa sakti hain. Kya aap interested hain iss baare mein baat karne mein?";
        } else {
            return "Hi! Maine {$name} ko online dekha - kaafi accha setup hai. Main {$city} mein businesses ke liye digital growth solutions provide karta hoon. Aapke existing setup mein kuch smart additions se conversions aur improve ho sakte hain. Kya aap interested hain quick discussion ke liye?";
        }
    }
    
    if ($lead['pitch_type'] === 'type_b') {
        return "Hi! I work with local businesses in {$city} on their digital presence. Noticed {$name} doesn't have a website yet - a simple landing page could help capture more enquiries from people searching online. Would you be open to a quick chat about this?";
    }
    
    return "Hi! I came across {$name} online and was impressed by your setup. I help businesses in {$city} improve their digital conversions with smart solutions. Had a few ideas that might work for you. Would you be open to a brief chat?";
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
