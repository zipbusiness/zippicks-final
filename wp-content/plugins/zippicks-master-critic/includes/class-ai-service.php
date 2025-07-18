<?php
/**
 * AI Service class for Claude and OpenAI integration
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_AI_Service {
    
    /**
     * Cache group
     */
    const CACHE_GROUP = 'zippicks_ai';
    
    /**
     * Rate limiting transient prefix
     */
    const RATE_LIMIT_PREFIX = 'zippicks_ai_rate_';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load rate limiter class if not already loaded
        if (!class_exists('ZipPicks_Master_Critic_Rate_Limiter')) {
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-rate-limiter.php';
        }
    }
    
    /**
     * Execute enhanced AI generation with confidence scoring
     *
     * @param array $params Generation parameters
     * @return array
     */
    public function execute_enhanced_generation($params) {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-ai-service-enhanced.php';
        $enhanced_service = new ZipPicks_Master_Critic_AI_Service_Enhanced();
        return $enhanced_service->execute_enhanced_generation($params);
    }
    
    /**
     * Execute AI generation
     *
     * @param string $prompt The prompt to send
     * @param string $provider AI provider (anthropic or openai)
     * @return array
     */
    public function execute_ai_generation($prompt, $provider = null) {
        // Use default provider if not specified
        if (!$provider) {
            $provider = get_option('zippicks_default_ai_provider', 'anthropic');
        }
        
        // Check cache first
        $cache_key = $this->get_cache_key($prompt, $provider);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($cached !== false) {
            return array(
                'success' => true,
                'data' => $cached,
                'cached' => true,
                'provider' => $provider
            );
        }
        
        // Check rate limits using enterprise rate limiter
        $rate_check = ZipPicks_Master_Critic_Rate_Limiter::check_limit('ai_generation');
        
        if (!$rate_check['allowed']) {
            // Apply rate limit headers
            ZipPicks_Master_Critic_Rate_Limiter::apply_headers($rate_check);
            
            return array(
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'provider' => $provider,
                'rate_limit' => ZipPicks_Master_Critic_Rate_Limiter::format_error_response($rate_check)
            );
        }
        
        // Route to appropriate API
        switch ($provider) {
            case 'anthropic':
                $result = $this->call_anthropic_api($prompt);
                break;
                
            case 'openai':
                $result = $this->call_openai_api($prompt);
                break;
                
            default:
                return array(
                    'success' => false,
                    'error' => 'Invalid AI provider selected',
                    'provider' => $provider
                );
        }
        
        // Add provider to result
        $result['provider'] = $provider;
        
        // Cache successful results with smart caching
        if ($result['success']) {
            // Use 7-day cache for stable queries (major cities + common categories)
            $cache_ttl = $this->get_smart_cache_ttl($prompt);
            wp_cache_set($cache_key, $result['data'], self::CACHE_GROUP, $cache_ttl);
            
            // Cache individual business descriptions for reuse
            $this->cache_business_components($result['data']);
            
            // Update rate limit counters
            $this->increment_rate_limit_counter();
        }
        
        return $result;
    }
    
    /**
     * Call Anthropic Claude API
     *
     * @param string $prompt
     * @return array
     */
    private function call_anthropic_api($prompt) {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';
        $api_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_anthropic_api_key');
        
        if (empty($api_key)) {
            $error_msg = 'Anthropic API key not configured. Please add it in Settings.';
            error_log('[ZipPicks AI Service] ERROR: ' . $error_msg);
            return array(
                'success' => false,
                'error' => $error_msg
            );
        }
        
        $url = 'https://api.anthropic.com/v1/messages';
        
        // Get the selected model from settings
        $model = get_option('zippicks_anthropic_model', 'claude-3-sonnet-20240229');
        
        // Build messages array with improved system prompt
        $messages = array(
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        $data = array(
            'model' => $model,
            'max_tokens' => 4000,
            'temperature' => 0.7,
            'messages' => $messages
        );
        
        $args = array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => get_option('zippicks_ai_timeout', 120), // Use configurable timeout
            'method' => 'POST',
            'sslverify' => true,
            'httpversion' => '1.1'
        );
        
        // Always log critical API calls
        error_log('[ZipPicks AI Service] Calling Anthropic API');
        error_log('[ZipPicks AI Service] Model: ' . $model);
        error_log('[ZipPicks AI Service] Prompt length: ' . strlen($prompt));
        error_log('[ZipPicks AI Service] Timeout: ' . $args['timeout'] . ' seconds');
        
        $start_time = microtime(true);
        $response = wp_remote_post($url, $args);
        $execution_time = microtime(true) - $start_time;
        
        error_log('[ZipPicks AI Service] API call took ' . round($execution_time, 2) . ' seconds');
        
        if (is_wp_error($response)) {
            $error_msg = 'API request failed: ' . $response->get_error_message();
            error_log('[ZipPicks AI Service] ERROR: ' . $error_msg);
            error_log('[ZipPicks AI Service] WP Error Code: ' . $response->get_error_code());
            error_log('[ZipPicks AI Service] WP Error Data: ' . json_encode($response->get_error_data()));
            
            return array(
                'success' => false,
                'error' => $error_msg,
                'wp_error_code' => $response->get_error_code(),
                'execution_time' => $execution_time
            );
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        // Always log response details for troubleshooting
        error_log('[ZipPicks AI Service] Response Code: ' . $response_code);
        error_log('[ZipPicks AI Service] Response Body Length: ' . strlen($body));
        
        if ($response_code !== 200) {
            error_log('[ZipPicks AI Service] ERROR: Non-200 response from Anthropic');
            error_log('[ZipPicks AI Service] Full Response Body: ' . substr($body, 0, 1000));
        }
        
        // Handle different response codes
        if ($response_code !== 200) {
            $error_message = 'API Error: ';
            
            if (isset($decoded['error']['message'])) {
                $error_message .= $decoded['error']['message'];
            } else if (isset($decoded['error'])) {
                $error_message .= json_encode($decoded['error']);
            } else {
                $error_message .= 'HTTP ' . $response_code . ' - ' . $body;
            }
            
            // Add helpful hints for common errors
            if ($response_code === 401) {
                $error_message .= ' (Please check your API key in settings)';
            } else if ($response_code === 400 && strpos($body, 'model') !== false) {
                $error_message .= ' (The selected model may not be available with your API key. Try Claude 3 Sonnet or Haiku)';
            } else if ($response_code === 429) {
                $error_message .= ' (Rate limit exceeded. Please wait before trying again)';
            } else if ($response_code === 500 || $response_code === 502 || $response_code === 503) {
                $error_message .= ' (Anthropic API service error. Please try again later)';
            }
            
            error_log('[ZipPicks AI Service] ERROR Details: ' . $error_message);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'raw_response' => $body,
                'http_code' => $response_code,
                'execution_time' => $execution_time
            );
        }
        
        if (isset($decoded['content'][0]['text'])) {
            error_log('[ZipPicks AI Service] SUCCESS: Received valid response from Anthropic');
            error_log('[ZipPicks AI Service] Response text length: ' . strlen($decoded['content'][0]['text']));
            
            return array(
                'success' => true,
                'data' => $decoded['content'][0]['text'],
                'model' => $model,
                'execution_time' => $execution_time
            );
        } else {
            $error_message = isset($decoded['error']['message']) 
                ? $decoded['error']['message'] 
                : 'Invalid Anthropic API response structure';
            
            error_log('[ZipPicks AI Service] ERROR: Invalid response structure');
            error_log('[ZipPicks AI Service] Decoded response: ' . json_encode($decoded));
            
            return array(
                'success' => false,
                'error' => $error_message,
                'raw_response' => $body,
                'execution_time' => $execution_time
            );
        }
    }
    
    /**
     * Call OpenAI GPT-4 API
     *
     * @param string $prompt
     * @return array
     */
    private function call_openai_api($prompt) {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-security.php';
        $api_key = ZipPicks_Master_Critic_Security::get_encrypted_option('zippicks_openai_api_key');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'error' => 'OpenAI API key not configured. Please add it in Settings.'
            );
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        // Use gpt-4-turbo-preview which supports response_format
        // or fall back to gpt-4 without response_format
        $model = get_option('zippicks_openai_model', 'gpt-4-turbo-preview');
        
        // Build messages array with improved system prompt
        $messages = array(
            array(
                'role' => 'system',
                'content' => 'You are an expert local business critic with deep knowledge of specific cities and their boundaries. Your PRIMARY directive is to ONLY include businesses that are PHYSICALLY LOCATED within the exact geographic boundaries specified in the user\'s request. NEVER include businesses from neighboring cities, suburbs, or metro areas. When generating business rankings, you must verify each business is actually located in the specified location. If you cannot find enough businesses in the specified location, return fewer results rather than including businesses from other areas. Always provide complete, valid JSON arrays with location-accurate data.'
            ),
            array(
                'role' => 'user',
                'content' => $prompt
            )
        );
        
        $data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.8,  // Slightly higher for more creative responses
            'max_tokens' => 4000,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );
        
        // Only add response_format for models that support it
        $models_with_json_mode = array('gpt-4-turbo-preview', 'gpt-4-1106-preview', 'gpt-3.5-turbo-1106', 'gpt-4-turbo', 'gpt-4o', 'gpt-4o-mini');
        if (in_array($model, $models_with_json_mode)) {
            $data['response_format'] = array('type' => 'json_object');
        }
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => get_option('zippicks_ai_timeout', 120), // Use configurable timeout
            'method' => 'POST',
            'sslverify' => true,
            'httpversion' => '1.1'
        );
        
        // Log request if debug is enabled
        if (defined('ZIPPICKS_DEBUG') && ZIPPICKS_DEBUG) {
            error_log('ZipPicks AI: Calling OpenAI API with model: ' . $model);
            error_log('ZipPicks AI: Request data: ' . json_encode($data));
        }
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => 'API request failed: ' . $response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        // Log response for debugging
        if (defined('ZIPPICKS_DEBUG') && ZIPPICKS_DEBUG) {
            error_log('ZipPicks AI: Response status: ' . wp_remote_retrieve_response_code($response));
            if (isset($decoded['error'])) {
                error_log('ZipPicks AI: API Error: ' . json_encode($decoded['error']));
            }
        }
        
        if (isset($decoded['choices'][0]['message']['content'])) {
            $content = $decoded['choices'][0]['message']['content'];
            
            // If we get an error message in the content, try to extract it
            if (strpos($content, '"error"') !== false) {
                // Try to parse as JSON to get the error
                $content_decoded = json_decode($content, true);
                if (isset($content_decoded['error'])) {
                    // Special handling for "No data available" error
                    if (strpos($content_decoded['error'], 'No data available') !== false) {
                        // Log the issue for debugging
                        error_log('ZipPicks AI: Model returned "No data available" - prompt may be too complex or model may not understand the format');
                        
                        // Return a more helpful error message
                        return array(
                            'success' => false,
                            'error' => 'The AI model could not generate the requested data. This often happens with complex prompts on certain models. Try using GPT-4 Turbo or GPT-4o instead, or simplify your prompt.',
                            'raw_response' => $content,
                            'suggestion' => 'Switch to GPT-4 Turbo or GPT-4o model in settings for better results.'
                        );
                    }
                    
                    return array(
                        'success' => false,
                        'error' => 'AI returned error: ' . $content_decoded['error'],
                        'raw_response' => $content
                    );
                }
            }
            
            return array(
                'success' => true,
                'data' => $content
            );
        } else {
            $error_message = isset($decoded['error']['message']) 
                ? $decoded['error']['message'] 
                : 'Invalid OpenAI API response';
            
            return array(
                'success' => false,
                'error' => $error_message,
                'raw_response' => $body
            );
        }
    }
    
    /**
     * Execute verified restaurant ranking
     * 
     * IMPORTANT: This method is the EXCLUSIVE pathway for API-verified generations.
     * When API verification is enabled in settings, this method ensures:
     * 1. Only the verified-ranking-prompt.txt template is used
     * 2. AI can ONLY select from the provided verified restaurant list
     * 3. All restaurant data comes from ZipBusiness API (no hallucinations)
     * 
     * The prompt template enforces strict selection from the verified list by:
     * - Providing explicit ZPIDs that must be used
     * - Requiring the AI to return only those ZPIDs
     * - Validating that returned data matches the input list
     *
     * @param array $params Generation parameters
     * @param array $verified_restaurants Real restaurants to rank
     * @return array AI rankings with verified data
     */
    public function execute_verified_generation($params, $verified_restaurants) {
        if (empty($verified_restaurants)) {
            return array(
                'success' => false,
                'error' => 'No verified restaurants provided for ranking'
            );
        }
        
        // Build verified ranking prompt
        $prompt = $this->build_verified_prompt($params, $verified_restaurants);
        
        // Execute AI generation
        $provider = get_option('zippicks_default_ai_provider', 'anthropic');
        $result = $this->execute_ai_generation($prompt, $provider);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Parse AI response
        $parsed = $this->parse_ai_response($result['data']);
        
        if (!$parsed) {
            return array(
                'success' => false,
                'error' => 'Failed to parse AI response for verified generation'
            );
        }
        
        // Validate against input restaurants
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-restaurant-validator.php';
        $validator = new ZipPicks_Master_Critic_Restaurant_Validator();
        
        $validated = $validator->validate_selections($parsed, $verified_restaurants);
        
        if (empty($validated)) {
            return array(
                'success' => false,
                'error' => 'AI failed to match any verified restaurants'
            );
        }
        
        return array(
            'success' => true,
            'data' => $validated,
            'total_verified' => count($validated),
            'verification_rate' => round((count($validated) / count($parsed)) * 100, 2),
            'provider' => $provider
        );
    }
    
    /**
     * Build verified ranking prompt
     *
     * This method EXCLUSIVELY uses the verified-ranking-prompt.txt template
     * when API verification is enabled. The template is specifically designed to:
     * - Constrain AI selection to only the provided restaurant list
     * - Require exact ZPID matching in responses
     * - Prevent any hallucinated or external restaurant data
     *
     * @param array $params Generation parameters
     * @param array $restaurants Verified restaurants
     * @return string
     */
    private function build_verified_prompt($params, $restaurants) {
        // Load verified prompt template - EXCLUSIVE template for API-verified generations
        $template_path = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/prompts/verified-ranking-prompt.txt';
        
        if (file_exists($template_path)) {
            $template = file_get_contents($template_path);
        } else {
            // Use inline template if file doesn't exist
            $template = $this->get_verified_prompt_template();
        }
        
        // Build restaurant list with details
        $restaurant_list = array();
        foreach ($restaurants as $index => $restaurant) {
            $vibes = !empty($restaurant['vibes']) ? 
                implode(', ', array_column($restaurant['vibes'], 'name')) : 
                'Not specified';
            
            $price = str_repeat('$', $restaurant['price_level'] ?? 2);
            
            $restaurant_list[] = sprintf(
                "%d. %s (ZPID: %s)\n   Cuisine: %s | Price: %s\n   Vibes: %s\n   Address: %s",
                $index + 1,
                $restaurant['name'],
                $restaurant['zpid'] ?? 'N/A',
                $restaurant['cuisine_type'] ?? 'Various',
                $price,
                $vibes,
                $restaurant['address'] ?? 'Address not available'
            );
        }
        
        // Replace placeholders
        $replacements = array(
            '{location}' => $params['location'] ?? 'the city',
            '{count}' => count($restaurants),
            '{restaurant_list}' => implode("\n\n", $restaurant_list),
            '{topic}' => $params['topic'] ?? $params['list_category'] ?? 'restaurants',
            '{business_category}' => $params['business_category'] ?? 'restaurant'
        );
        
        foreach ($replacements as $placeholder => $value) {
            $template = str_replace($placeholder, $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Get inline verified prompt template
     *
     * @return string
     */
    private function get_verified_prompt_template() {
        return 'VERIFIED RESTAURANT RANKING TASK
================================

You are ranking REAL restaurants that exist in {location}. 
Here are {count} verified restaurants to evaluate:

{restaurant_list}

YOUR TASK:
1. Select the 10 BEST restaurants for: {topic}
2. Rank them 1-10
3. Score each across our 6 pillars (0-10 scale)
4. Write a 2-3 sentence editorial summary

IMPORTANT: 
- You may ONLY select from the provided list
- Do NOT invent any restaurants not on this list
- Base scores on the attributes provided
- Focus editorial voice on what makes each special

OUTPUT FORMAT:
{
  "restaurants": [
    {
      "rank": 1,
      "name": "EXACT name from list",
      "pillar_scores": {
        "food_quality": 9.5,
        "service": 9.2,
        "atmosphere": 9.0,
        "value": 8.5,
        "consistency": 9.3,
        "cultural_relevance": 9.7
      },
      "summary": "Editorial description focusing on strengths",
      "recommended_vibes": ["vibe1", "vibe2"]
    }
  ]
}';
    }
    
    /**
     * Generate prompt from template
     *
     * @param array $params
     * @return string
     */
    public function generate_prompt($params) {
        // Use the new enhanced prompt system
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-ai-service-enhanced.php';
        $enhanced_service = new ZipPicks_Master_Critic_AI_Service_Enhanced();
        return $enhanced_service->build_enhanced_prompt($params);
    }
    
    /**
     * Get prompt template
     *
     * @param string $category
     * @return string
     */
    private function get_prompt_template($category) {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database.php';
        
        // Check for category-specific template
        $templates = ZipPicks_Master_Critic_Database::get_prompt_templates($category);
        
        if (!empty($templates)) {
            foreach ($templates as $template) {
                if ($template->is_default) {
                    return $template->prompt_template;
                }
            }
            // Return first if no default
            return $templates[0]->prompt_template;
        }
        
        // Fall back to universal template
        $universal = ZipPicks_Master_Critic_Database::get_prompt_templates('all');
        if (!empty($universal)) {
            return $universal[0]->prompt_template;
        }
        
        // Ultimate fallback
        return $this->get_default_prompt_template();
    }
    
    /**
     * Get default prompt template
     *
     * @return string
     */
    private function get_default_prompt_template() {
        return '🚨 CRITICAL LOCATION REQUIREMENT 🚨
You MUST ONLY include {business_category_plural} that are PHYSICALLY LOCATED in {location}.

STRICT GEOGRAPHIC RULES:
1. Every single {business_category} MUST be located within the boundaries of {location}
2. If a {business_category} has multiple locations, ONLY count the one in {location}
3. Do NOT include {business_category_plural} from nearby cities, suburbs, or metro areas unless they are within {location} proper
4. Do NOT include {business_category_plural} you\'re unsure about - when in doubt, leave it out
5. If you cannot find enough {business_category_plural} in {location}, return fewer results rather than including ones from other locations

LOCATION VERIFICATION CHECKLIST:
✓ Is this {business_category} physically located in {location}? (NOT nearby cities)
✓ Does this {business_category} have a street address in {location}?
✓ Is this a real, currently operating {business_category} in {location}?

You are the Master Critic AI for ZipPicks — a location-specific discovery platform. Your SOLE task is to evaluate {business_category_plural} that exist ONLY in {location}.

TASK: Generate a list of the best {topic} that are LOCATED IN {location}

Only include {business_category_plural} that:
- Are PHYSICALLY LOCATED in {location} (not nearby areas)
- Currently exist and are operational
- Genuinely deserve recognition
- Can be verified as being in {location}

You may list up to 10 businesses, but ONLY if they are ALL in {location}. Return fewer if necessary.

Every output must follow this exact structure:

[
  {
    "rank": 1,
    "name": "{business_category} Name",
    "score": 9.6,
    "review_count": 1200,
    "price_tier": "$$",
    "summary": "Editorial description of the {business_category}, written in a stylish and trustworthy tone. Avoid generic phrases. Capture the emotional essence of experiencing this {business_category}. Mention highlights and who it\'s best for.",
    "top_dishes": ["{unit} 1", "{unit} 2"],
    "pillar_scores": {
      "{category_pillar_1}": 9.8,
      "{category_pillar_2}": 9.4,
      "{category_pillar_3}": 9.5,
      "{category_pillar_4}": 9.2,
      "{category_pillar_5}": 9.4,
      "{category_pillar_6}": 9.6
    },
    "vibes": ["Vibe 1", "Vibe 2", "Vibe 3"]
  }
]

LOCATION ENFORCEMENT:
• Every {business_category} MUST be in {location} - NO EXCEPTIONS
• Do NOT include famous {business_category_plural} from other cities
• Do NOT include {business_category_plural} from neighboring towns
• Each result must be verifiably located in {location}

Scoring Guidelines:
• All scores must be on a 0–10 scale, not 1–5
• Base scores on local reputation within {location}
• Consider how each {business_category} compares to others in {location} specifically

Editorial Voice:
• Write like a LOCAL expert who knows {location} intimately
• Reference {location}-specific details when relevant
• Focus on what makes each {business_category} special within {location}\'s scene

FINAL REMINDER: You are generating:
Top {topic} in {location}

This means ONLY {business_category_plural} that are INSIDE {location}\'s boundaries.
NOT nearby cities. NOT metro areas. ONLY {location}.

Now return only the valid JSON array with {business_category_plural} from {location}. No preamble. No closing statements. No formatting errors.';
    }
    
    /**
     * Parse AI response
     *
     * @param string $response
     * @return array|false
     */
    public function parse_ai_response($response) {
        error_log('[ZipPicks AI] Parsing AI response, length: ' . strlen($response));
        
        // Try to extract JSON from response
        $json_start = strpos($response, '[');
        $json_end = strrpos($response, ']');
        
        if ($json_start !== false && $json_end !== false) {
            $json_string = substr($response, $json_start, $json_end - $json_start + 1);
            error_log('[ZipPicks AI] Extracted JSON string, length: ' . strlen($json_string));
            
            $parsed = json_decode($json_string, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                error_log('[ZipPicks AI] Successfully parsed JSON array with ' . count($parsed) . ' items');
                
                // Process and validate each business
                $processed = array();
                foreach ($parsed as $index => $item) {
                    if (is_array($item) && isset($item['name']) && isset($item['score'])) {
                        // Extract and process vibes
                        if (isset($item['vibes']) && is_array($item['vibes'])) {
                            $item['vibe_names'] = $item['vibes'];
                            $item['vibe_ids'] = $this->extract_vibe_ids($item['vibes']);
                        }
                        
                        // Handle overall_score vs score
                        if (!isset($item['overall_score']) && isset($item['score'])) {
                            $item['overall_score'] = $item['score'];
                        }
                        
                        // Extract category-specific fields
                        if (isset($item['must_try_dish'])) {
                            $item['must_try_dish'] = sanitize_text_field($item['must_try_dish']);
                        }
                        
                        if (isset($item['price_range'])) {
                            $item['price_range'] = sanitize_text_field($item['price_range']);
                        }
                        
                        if (isset($item['cuisine_type'])) {
                            $item['cuisine_type'] = sanitize_text_field($item['cuisine_type']);
                        }
                        
                        $processed[] = $item;
                    } else {
                        error_log('[ZipPicks AI] WARNING: Invalid item at index ' . $index . ', type: ' . gettype($item));
                        if (is_array($item)) {
                            error_log('[ZipPicks AI] Item keys: ' . implode(', ', array_keys($item)));
                        }
                    }
                }
                
                error_log('[ZipPicks AI] Processed ' . count($processed) . ' valid businesses');
                return $processed;
            } else {
                error_log('[ZipPicks AI] JSON decode error: ' . json_last_error_msg());
            }
        } else {
            error_log('[ZipPicks AI] No JSON array markers found in response');
        }
        
        // Try parsing entire response as JSON
        $parsed = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            error_log('[ZipPicks AI] Successfully parsed entire response as JSON');
            
            // Check if this is an object containing a businesses array
            if (isset($parsed['businesses']) && is_array($parsed['businesses'])) {
                error_log('[ZipPicks AI] Found businesses array within JSON object, extracting ' . count($parsed['businesses']) . ' businesses');
                
                // Process businesses array
                $processed = array();
                foreach ($parsed['businesses'] as $item) {
                    if (is_array($item) && isset($item['name']) && isset($item['score'])) {
                        // Extract and process vibes
                        if (isset($item['vibes']) && is_array($item['vibes'])) {
                            $item['vibe_names'] = $item['vibes'];
                            $item['vibe_ids'] = $this->extract_vibe_ids($item['vibes']);
                        }
                        
                        // Handle overall_score vs score
                        if (!isset($item['overall_score']) && isset($item['score'])) {
                            $item['overall_score'] = $item['score'];
                        }
                        
                        $processed[] = $item;
                    }
                }
                return $processed;
            }
            
            // Check if this is already an array of businesses (numeric keys)
            if (isset($parsed[0]) && is_array($parsed[0])) {
                error_log('[ZipPicks AI] Response is already a businesses array');
                
                // Process the array
                $processed = array();
                foreach ($parsed as $item) {
                    if (is_array($item) && isset($item['name']) && isset($item['score'])) {
                        // Extract and process vibes
                        if (isset($item['vibes']) && is_array($item['vibes'])) {
                            $item['vibe_names'] = $item['vibes'];
                            $item['vibe_ids'] = $this->extract_vibe_ids($item['vibes']);
                        }
                        
                        // Handle overall_score vs score
                        if (!isset($item['overall_score']) && isset($item['score'])) {
                            $item['overall_score'] = $item['score'];
                        }
                        
                        $processed[] = $item;
                    }
                }
                return $processed;
            }
            
            // If it's an associative array but not a businesses container, convert to indexed array
            if ($this->is_single_business($parsed)) {
                error_log('[ZipPicks AI] Response appears to be a single business, wrapping in array');
                
                // Process single business
                if (isset($parsed['vibes']) && is_array($parsed['vibes'])) {
                    $parsed['vibe_names'] = $parsed['vibes'];
                    $parsed['vibe_ids'] = $this->extract_vibe_ids($parsed['vibes']);
                }
                
                if (!isset($parsed['overall_score']) && isset($parsed['score'])) {
                    $parsed['overall_score'] = $parsed['score'];
                }
                
                return array($parsed);
            }
            
            // Return as-is and let validate_businesses handle it
            error_log('[ZipPicks AI] WARNING: Unexpected JSON structure, returning as-is');
            return $parsed;
        } else {
            error_log('[ZipPicks AI] Failed to parse response as JSON: ' . json_last_error_msg());
            error_log('[ZipPicks AI] First 500 chars of response: ' . substr($response, 0, 500));
        }
        
        return false;
    }
    
    /**
     * Check if array represents a single business
     *
     * @param array $data
     * @return bool
     */
    private function is_single_business($data) {
        return isset($data['name']) && isset($data['score']) && !isset($data[0]);
    }
    
    /**
     * Extract vibe IDs from vibe names
     *
     * @param array $vibe_names Array of vibe names
     * @return array Array of vibe term IDs
     */
    private function extract_vibe_ids($vibe_names) {
        $vibe_ids = array();
        
        if (!is_array($vibe_names)) {
            return $vibe_ids;
        }
        
        if (!taxonomy_exists('vibes')) {
            error_log('[Master Critic] Vibes taxonomy not found');
            return array();
        }
        
        foreach ($vibe_names as $vibe_name) {
            $term = get_term_by('name', $vibe_name, 'vibes');
            if ($term && !is_wp_error($term)) {
                $vibe_ids[] = $term->term_id;
            }
        }
        
        return $vibe_ids;
    }
    
    /**
     * Get cache key for prompt
     *
     * @param string $prompt
     * @param string $provider
     * @return string
     */
    private function get_cache_key($prompt, $provider) {
        return 'ai_response_' . md5($prompt . '_' . $provider);
    }
    
    /**
     * Check if we can make a request (rate limiting)
     *
     * @return bool
     */
    private function can_make_request() {
        $minute_limit = get_option('zippicks_ai_rate_limit_per_minute', 20);
        $day_limit = get_option('zippicks_ai_rate_limit_per_day', 1000);
        
        $minute_count = get_transient(self::RATE_LIMIT_PREFIX . 'minute');
        $day_count = get_transient(self::RATE_LIMIT_PREFIX . 'day');
        
        if ($minute_count >= $minute_limit || $day_count >= $day_limit) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Increment rate limit counter
     *
     * @return void
     */
    private function increment_rate_limit_counter() {
        // Minute counter
        $minute_count = get_transient(self::RATE_LIMIT_PREFIX . 'minute');
        if ($minute_count === false) {
            set_transient(self::RATE_LIMIT_PREFIX . 'minute', 1, 60);
        } else {
            set_transient(self::RATE_LIMIT_PREFIX . 'minute', $minute_count + 1, 60);
        }
        
        // Day counter
        $day_count = get_transient(self::RATE_LIMIT_PREFIX . 'day');
        if ($day_count === false) {
            set_transient(self::RATE_LIMIT_PREFIX . 'day', 1, DAY_IN_SECONDS);
        } else {
            set_transient(self::RATE_LIMIT_PREFIX . 'day', $day_count + 1, DAY_IN_SECONDS);
        }
    }
    
    /**
     * Check rate limits on initialization
     *
     * @return void
     */
    private function check_rate_limits() {
        // Initialize counters if they don't exist
        if (get_transient(self::RATE_LIMIT_PREFIX . 'minute') === false) {
            set_transient(self::RATE_LIMIT_PREFIX . 'minute', 0, 60);
        }
        
        if (get_transient(self::RATE_LIMIT_PREFIX . 'day') === false) {
            set_transient(self::RATE_LIMIT_PREFIX . 'day', 0, DAY_IN_SECONDS);
        }
    }
    
    /**
     * Get remaining API calls
     *
     * @return array
     */
    public function get_remaining_calls() {
        $minute_limit = get_option('zippicks_ai_rate_limit_per_minute', 20);
        $day_limit = get_option('zippicks_ai_rate_limit_per_day', 1000);
        
        $minute_count = get_transient(self::RATE_LIMIT_PREFIX . 'minute') ?: 0;
        $day_count = get_transient(self::RATE_LIMIT_PREFIX . 'day') ?: 0;
        
        return array(
            'minute' => array(
                'used' => $minute_count,
                'limit' => $minute_limit,
                'remaining' => max(0, $minute_limit - $minute_count)
            ),
            'day' => array(
                'used' => $day_count,
                'limit' => $day_limit,
                'remaining' => max(0, $day_limit - $day_count)
            )
        );
    }
    
    /**
     * Get smart cache TTL based on query stability
     *
     * @param string $prompt
     * @return int Cache TTL in seconds
     */
    private function get_smart_cache_ttl($prompt) {
        // Major cities with stable food scenes - cache for 7 days
        $stable_cities = array(
            'new york', 'los angeles', 'chicago', 'houston', 'phoenix',
            'philadelphia', 'san antonio', 'san diego', 'dallas', 'san jose',
            'austin', 'jacksonville', 'fort worth', 'columbus', 'charlotte',
            'san francisco', 'indianapolis', 'seattle', 'denver', 'washington',
            'boston', 'portland', 'las vegas', 'miami', 'atlanta'
        );
        
        // Stable categories that don't change frequently
        $stable_categories = array(
            'pizza', 'steakhouse', 'bbq', 'italian', 'chinese', 
            'mexican', 'japanese', 'french', 'indian', 'thai'
        );
        
        $prompt_lower = strtolower($prompt);
        $is_stable_city = false;
        $is_stable_category = false;
        
        // Check if prompt contains a stable city
        foreach ($stable_cities as $city) {
            if (strpos($prompt_lower, $city) !== false) {
                $is_stable_city = true;
                break;
            }
        }
        
        // Check if prompt contains a stable category
        foreach ($stable_categories as $category) {
            if (strpos($prompt_lower, $category) !== false) {
                $is_stable_category = true;
                break;
            }
        }
        
        // Smart TTL decision
        if ($is_stable_city && $is_stable_category) {
            return 7 * DAY_IN_SECONDS; // 7 days for stable queries
        } elseif ($is_stable_city || $is_stable_category) {
            return 3 * DAY_IN_SECONDS; // 3 days for semi-stable queries
        } else {
            return DAY_IN_SECONDS; // 1 day for dynamic queries
        }
    }
    
    /**
     * Cache individual business components for reuse
     *
     * @param string $ai_response
     * @return void
     */
    private function cache_business_components($ai_response) {
        $businesses = $this->parse_ai_response($ai_response);
        
        if (!$businesses || !is_array($businesses)) {
            return;
        }
        
        foreach ($businesses as $business) {
            if (isset($business['name']) && isset($business['summary'])) {
                // Cache business description with business name as key
                $cache_key = 'business_desc_' . md5(strtolower($business['name']));
                wp_cache_set(
                    $cache_key, 
                    array(
                        'summary' => $business['summary'],
                        'pillar_scores' => $business['pillar_scores'] ?? null,
                        'vibes' => $business['vibes'] ?? null
                    ),
                    self::CACHE_GROUP,
                    30 * DAY_IN_SECONDS // Cache business components for 30 days
                );
            }
        }
    }
    
    /**
     * Try to get cached business description
     *
     * @param string $business_name
     * @return array|false
     */
    public function get_cached_business_component($business_name) {
        $cache_key = 'business_desc_' . md5(strtolower($business_name));
        return wp_cache_get($cache_key, self::CACHE_GROUP);
    }
}