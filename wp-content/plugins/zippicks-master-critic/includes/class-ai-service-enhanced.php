<?php
/**
 * Enhanced AI Service for superior first-pass recommendations
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_AI_Service_Enhanced extends ZipPicks_Master_Critic_AI_Service {
    
    /**
     * Execute enhanced AI generation with context and validation
     *
     * @param array $params
     * @return array
     */
    public function execute_enhanced_generation($params) {
        error_log('[ZipPicks Enhanced] Starting enhanced generation with params: ' . json_encode($params));
        
        try {
            // CRITICAL: Check ZipBusiness API availability first
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-zipbusiness-api-client.php';
            $api_client = new ZipPicks_Master_Critic_ZipBusiness_API_Client();
            
            // Check if API verification is enabled
            if (!get_option('zippicks_enable_api_verification', true)) {
                error_log('[ZipPicks Enhanced] ERROR: ZipBusiness API verification is disabled');
                return array(
                    'success' => false,
                    'error' => 'ZipBusiness API verification must be enabled for high-quality generation. Please enable it in settings.',
                    'api_required' => true
                );
            }
            
            // Test API connectivity
            $api_status = $api_client->get_api_status();
            if (!$api_status['connected']) {
                error_log('[ZipPicks Enhanced] ERROR: Cannot connect to ZipBusiness API');
                return array(
                    'success' => false,
                    'error' => 'Cannot connect to ZipBusiness API. Real restaurant data is required for generation.',
                    'api_error' => $api_status['error'] ?? 'Unknown connection error',
                    'api_required' => true
                );
            }
            
            // Fetch real restaurant data for the location
            error_log('[ZipPicks Enhanced] Fetching restaurant data for ' . $params['location']);
            $city_parts = $this->parse_location($params['location']);
            
            try {
                $restaurant_data = $api_client->get_city_restaurants($city_parts['city'], $city_parts['state']);
                
                if (empty($restaurant_data)) {
                    error_log('[ZipPicks Enhanced] ERROR: No restaurant data returned from API');
                    return array(
                        'success' => false,
                        'error' => 'No restaurant data available for ' . $params['location'] . '. Cannot generate without real data.',
                        'api_required' => true
                    );
                }
                
                error_log('[ZipPicks Enhanced] Successfully fetched ' . count($restaurant_data) . ' restaurants from ZipBusiness API');
                
                // Store restaurant data for use in prompt
                $params['zipbusiness_data'] = $restaurant_data;
                
            } catch (Exception $e) {
                error_log('[ZipPicks Enhanced] ERROR: Failed to fetch restaurant data: ' . $e->getMessage());
                return array(
                    'success' => false,
                    'error' => 'Failed to fetch restaurant data from ZipBusiness API: ' . $e->getMessage(),
                    'api_required' => true
                );
            }
            
            // Step 1: Build context-aware prompt with real data
            error_log('[ZipPicks Enhanced] Building enhanced prompt with real restaurant data...');
            $enhanced_prompt = $this->build_enhanced_prompt($params);
            error_log('[ZipPicks Enhanced] Enhanced prompt built, length: ' . strlen($enhanced_prompt));
            
            // Step 2: Select model based on city size for cost optimization
            $model = $this->get_optimal_model($params['location']);
            error_log('[ZipPicks Enhanced] Selected model: ' . $model);
            
            // Check if we have API access to the selected model
            $anthropic_key = get_option('zippicks_anthropic_api_key');
            if (empty($anthropic_key)) {
                error_log('[ZipPicks Enhanced] ERROR: No Anthropic API key set');
                return array(
                    'success' => false,
                    'error' => 'Anthropic API key not configured. Please add it in Settings.',
                    'provider' => 'anthropic'
                );
            }
            
            // Override the default model temporarily
            $original_model = get_option('zippicks_anthropic_model');
            if ($model !== $original_model) {
                update_option('zippicks_anthropic_model', $model);
                error_log('[ZipPicks Enhanced] Temporarily switched model from ' . $original_model . ' to ' . $model);
            }
            
            // Execute generation with optimal model
            error_log('[ZipPicks Enhanced] Executing AI generation...');
            $result = $this->execute_ai_generation($enhanced_prompt, 'anthropic');
            error_log('[ZipPicks Enhanced] AI generation result: ' . json_encode(array(
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
                'provider' => $result['provider'] ?? null
            )));
            
            // Check if Opus model failed due to access restrictions
            if (!$result['success'] && $model === 'claude-3-opus-20240229' && 
                (strpos($result['error'], 'model') !== false || strpos($result['error'], '403') !== false)) {
                error_log('[ZipPicks Enhanced] Opus model access denied, falling back to Sonnet...');
                
                // Mark Opus as unavailable for future requests
                update_option('zippicks_opus_available', 'false');
                
                // Try again with Sonnet
                update_option('zippicks_anthropic_model', 'claude-3-sonnet-20240229');
                $result = $this->execute_ai_generation($enhanced_prompt, 'anthropic');
                error_log('[ZipPicks Enhanced] Sonnet fallback result: ' . json_encode(array(
                    'success' => $result['success'],
                    'error' => $result['error'] ?? null
                )));
            }
            
            // Restore original model
            if ($model !== $original_model) {
                update_option('zippicks_anthropic_model', $original_model);
            }
        
            // Step 3: Validate and enhance results
            if ($result['success']) {
                error_log('[ZipPicks Enhanced] AI generation successful, parsing response...');
                $businesses = $this->parse_ai_response($result['data']);
                
                if ($businesses && is_array($businesses)) {
                    // Additional validation - ensure we have a valid array
                    if (empty($businesses)) {
                        error_log('[ZipPicks Enhanced] WARNING: Parsed businesses array is empty');
                        return array(
                            'success' => false,
                            'error' => 'AI returned empty businesses list',
                            'provider' => $result['provider']
                        );
                    }
                    
                    // Check if businesses array has non-numeric keys indicating wrong structure
                    $has_numeric_keys = true;
                    foreach (array_keys($businesses) as $key) {
                        if (!is_numeric($key)) {
                            $has_numeric_keys = false;
                            error_log('[ZipPicks Enhanced] WARNING: Businesses array has non-numeric key: ' . $key);
                            break;
                        }
                    }
                    
                    // If non-numeric keys, try to extract businesses
                    if (!$has_numeric_keys && isset($businesses['businesses'])) {
                        error_log('[ZipPicks Enhanced] Extracting businesses from nested structure');
                        $businesses = $businesses['businesses'];
                    }
                    
                    error_log('[ZipPicks Enhanced] Parsed ' . count($businesses) . ' businesses');
                    
                    // Validate each business
                    error_log('[ZipPicks Enhanced] Validating businesses...');
                    $validated = $this->validate_businesses($businesses, $params);
                    
                    // Check if validation removed all businesses
                    if (empty($validated['businesses'])) {
                        error_log('[ZipPicks Enhanced] ERROR: All businesses failed validation');
                        return array(
                            'success' => false,
                            'error' => 'All businesses failed validation checks',
                            'validation_report' => $validated['report'],
                            'provider' => $result['provider']
                        );
                    }
                    
                    // Calculate confidence score
                    $confidence = $this->calculate_confidence($validated, $params);
                    error_log('[ZipPicks Enhanced] Confidence score: ' . $confidence);
                    
                    return array(
                        'success' => true,
                        'data' => json_encode($validated['businesses']), // For compatibility
                        'businesses' => $validated['businesses'],
                        'confidence' => $confidence,
                        'validation_report' => $validated['report'],
                        'prompt_used' => $enhanced_prompt,
                        'provider' => $result['provider'],
                        'cached' => isset($result['cached']) ? $result['cached'] : false
                    );
                } else {
                    error_log('[ZipPicks Enhanced] ERROR: Failed to parse businesses from AI response or invalid type: ' . gettype($businesses));
                }
            } else {
                error_log('[ZipPicks Enhanced] AI generation failed with primary provider');
            }
        
        // Step 4: Fallback to GPT-4 if Claude fails
        if (!$result['success'] && strpos($result['error'], 'API key') === false) {
            error_log('[ZipPicks Enhanced] Primary provider failed, trying GPT-4 fallback...');
            $result = $this->execute_ai_generation($enhanced_prompt, 'openai');
            
            // If GPT-4 succeeds, process the results
            if ($result['success']) {
                $businesses = $this->parse_ai_response($result['data']);
                
                if ($businesses && is_array($businesses)) {
                    // Additional validation for GPT-4 response
                    if (empty($businesses)) {
                        error_log('[ZipPicks Enhanced] WARNING: GPT-4 returned empty businesses list');
                        return array(
                            'success' => false,
                            'error' => 'GPT-4 returned empty businesses list',
                            'provider' => $result['provider']
                        );
                    }
                    
                    // Check structure and fix if needed
                    $has_numeric_keys = true;
                    foreach (array_keys($businesses) as $key) {
                        if (!is_numeric($key)) {
                            $has_numeric_keys = false;
                            break;
                        }
                    }
                    
                    if (!$has_numeric_keys && isset($businesses['businesses'])) {
                        error_log('[ZipPicks Enhanced] Extracting businesses from GPT-4 nested structure');
                        $businesses = $businesses['businesses'];
                    }
                    
                    $validated = $this->validate_businesses($businesses, $params);
                    
                    // Check if validation removed all businesses
                    if (empty($validated['businesses'])) {
                        error_log('[ZipPicks Enhanced] ERROR: All GPT-4 businesses failed validation');
                        return array(
                            'success' => false,
                            'error' => 'All GPT-4 businesses failed validation checks',
                            'validation_report' => $validated['report'],
                            'provider' => $result['provider']
                        );
                    }
                    
                    $confidence = $this->calculate_confidence($validated, $params);
                    
                    return array(
                        'success' => true,
                        'data' => json_encode($validated['businesses']),
                        'businesses' => $validated['businesses'],
                        'confidence' => $confidence,
                        'validation_report' => $validated['report'],
                        'prompt_used' => $enhanced_prompt,
                        'provider' => $result['provider'],
                        'cached' => isset($result['cached']) ? $result['cached'] : false
                    );
                }
            }
        }
        
            // Return the error result with model info
            $result['model_used'] = $model;
            return $result;
            
        } catch (Exception $e) {
            error_log('[ZipPicks Enhanced] EXCEPTION: ' . $e->getMessage());
            error_log('[ZipPicks Enhanced] Stack trace: ' . $e->getTraceAsString());
            
            return array(
                'success' => false,
                'error' => 'Enhanced generation failed: ' . $e->getMessage(),
                'provider' => 'anthropic'
            );
        }
    }
    
    /**
     * Build enhanced prompt with city context
     *
     * @param array $params
     * @return string
     */
    public function build_enhanced_prompt($params) {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-city-context.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-restaurant-intelligence-integration.php';
        
        // Get base prompt template
        $template = file_get_contents(ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/prompts/enhanced-master-prompt.txt');
        
        // Get ZipBusiness API data (required)
        $restaurant_context = '';
        if (isset($params['zipbusiness_data']) && !empty($params['zipbusiness_data'])) {
            $restaurant_context = $this->format_zipbusiness_data_for_prompt(
                $params['zipbusiness_data'],
                $params['business_category']
            );
        } else {
            // This should never happen as we check for data in execute_enhanced_generation
            error_log('[ZipPicks Enhanced] WARNING: No ZipBusiness data in prompt parameters');
        }
        
        // Get city-specific context
        $city_context = ZipPicks_Master_Critic_City_Context::get_context(
            $params['location'], 
            $params['business_category']
        );
        
        // Get category configuration
        $categories = get_option('zippicks_business_categories');
        $category_config = $categories[$params['business_category']] ?? $categories['custom'];
        
        // Build replacements
        $replacements = array(
            '{business_category}' => $category_config['label'],
            '{business_category_plural}' => $category_config['plural'],
            '{topic}' => $params['topic'],
            '{location}' => $params['location'],
            '{category_specific_context}' => $city_context . "\n\n" . $restaurant_context
        );
        
        // Add pillar replacements
        $pillar_index = 1;
        foreach ($category_config['pillars'] as $key => $label) {
            $replacements['{category_pillar_' . $pillar_index . '}'] = $key;
            $pillar_index++;
        }
        
        // Apply replacements
        $prompt = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
        
        // Add category-specific enhancements if list_category is provided
        if (!empty($params['list_category'])) {
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-category-handler.php';
            $category_prompt = ZipPicks_Master_Critic_Category_Handler::build_category_prompt(
                $params['list_category'], 
                $params
            );
            if ($category_prompt) {
                $prompt .= $category_prompt;
            }
            
            // Add category-specific restaurant data
            $category_restaurants = ZipPicks_Master_Critic_Restaurant_Intelligence_Integration::get_restaurants_for_category(
                $params['location'],
                $params['list_category']
            );
            
            if (!empty($category_restaurants)) {
                $prompt .= "\n\nRELEVANT RESTAURANT DATA FOR THIS CATEGORY:\n";
                $prompt .= ZipPicks_Master_Critic_Restaurant_Intelligence_Integration::format_restaurants_for_prompt($category_restaurants);
                $prompt .= "\n\nPLEASE PRIORITIZE THESE VERIFIED RESTAURANTS IN YOUR SELECTION.\n";
            } else {
                // Log warning but continue with AI generation
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[Master Critic] No restaurant data available for ' . $params['location'] . ' - ' . $params['list_category']);
                }
            }
        }
        
        // Add recent trends if available
        $prompt .= $this->get_recent_trends($params['location'], $params['business_category']);
        
        return $prompt;
    }
    
    /**
     * Validate businesses for accuracy
     *
     * @param array $businesses
     * @param array $params
     * @return array
     */
    private function validate_businesses($businesses, $params) {
        $validated = array();
        $report = array(
            'total' => 0,
            'validated' => 0,
            'removed' => 0,
            'api_verified' => 0,
            'missing_zpid' => 0,
            'warnings' => array(),
            'errors' => array()
        );
        
        // Enterprise-grade input validation
        if (!is_array($businesses)) {
            error_log('[ZipPicks Enhanced] ERROR: validate_businesses received non-array input: ' . gettype($businesses));
            $report['errors'][] = 'Invalid businesses data structure received';
            return array(
                'businesses' => array(),
                'report' => $report
            );
        }
        
        $report['total'] = count($businesses);
        
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-city-context.php';
        $valid_neighborhoods = ZipPicks_Master_Critic_City_Context::get_valid_neighborhoods($params['location']);
        
        // Re-index array to ensure numeric keys
        $businesses = array_values($businesses);
        $position = 1;
        
        foreach ($businesses as $index => $business) {
            // Enterprise-grade type validation for each business
            if (!is_array($business)) {
                error_log('[ZipPicks Enhanced] WARNING: Business at position ' . $position . ' is not an array: ' . gettype($business));
                $report['warnings'][] = sprintf('Invalid business data at position %d (type: %s)', $position, gettype($business));
                $report['removed']++;
                $position++;
                continue;
            }
            
            // Validate required fields exist
            $required_fields = array('name', 'score');
            $missing_fields = array();
            foreach ($required_fields as $field) {
                if (!isset($business[$field])) {
                    $missing_fields[] = $field;
                }
            }
            
            if (!empty($missing_fields)) {
                error_log('[ZipPicks Enhanced] WARNING: Business missing required fields: ' . implode(', ', $missing_fields));
                $report['warnings'][] = sprintf(
                    'Business at position %d missing required fields: %s',
                    $position,
                    implode(', ', $missing_fields)
                );
                $report['removed']++;
                $position++;
                continue;
            }
            
            $validation = array(
                'neighborhood_valid' => true,
                'score_valid' => true,
                'pillars_valid' => true,
                'api_verified' => false
            );
            
            // CRITICAL: Verify against ZipBusiness API data
            if (isset($params['zipbusiness_data']) && !empty($params['zipbusiness_data'])) {
                $found_in_api = false;
                $business_zpid = null;
                
                // Check if business exists in API data
                foreach ($params['zipbusiness_data'] as $api_restaurant) {
                    if (strcasecmp($api_restaurant['name'], $business['name']) === 0) {
                        $found_in_api = true;
                        $business_zpid = $api_restaurant['zpid'] ?? null;
                        break;
                    }
                }
                
                if (!$found_in_api) {
                    error_log('[ZipPicks Enhanced] WARNING: Business not found in ZipBusiness API: ' . $business['name']);
                    $report['warnings'][] = sprintf(
                        '%s: Not found in ZipBusiness API - may not be a real establishment',
                        $business['name']
                    );
                    $report['missing_zpid']++;
                    $report['removed']++;
                    $position++;
                    continue; // Skip businesses not in API
                } else {
                    $validation['api_verified'] = true;
                    $report['api_verified']++;
                    
                    // Add ZPID to business data if found
                    if ($business_zpid) {
                        $business['zpid'] = $business_zpid;
                    }
                }
            } else {
                // This should never happen as we require API data
                error_log('[ZipPicks Enhanced] CRITICAL: No ZipBusiness data available for validation');
                $report['errors'][] = 'ZipBusiness API data missing - cannot verify establishments';
                return array(
                    'businesses' => array(),
                    'report' => $report
                );
            }
            
            // Validate neighborhood if provided
            if (!empty($business['neighborhood']) && !empty($valid_neighborhoods)) {
                $found = false;
                foreach ($valid_neighborhoods as $area => $neighborhoods) {
                    if (is_array($neighborhoods) && in_array($business['neighborhood'], $neighborhoods)) {
                        $found = true;
                        break;
                    }
                }
                $validation['neighborhood_valid'] = $found;
                
                if (!$found) {
                    $report['warnings'][] = sprintf(
                        '%s: Neighborhood "%s" may not be in %s',
                        $business['name'],
                        $business['neighborhood'],
                        $params['location']
                    );
                }
            }
            
            // Validate scores with type checking
            if (!is_numeric($business['score'])) {
                error_log('[ZipPicks Enhanced] WARNING: Business score is not numeric: ' . gettype($business['score']));
                $validation['score_valid'] = false;
                $report['warnings'][] = sprintf(
                    '%s: Invalid score type (expected numeric, got %s)',
                    $business['name'],
                    gettype($business['score'])
                );
            } elseif ((float)$business['score'] < 8.0) {
                $validation['score_valid'] = false;
                $report['warnings'][] = sprintf(
                    '%s: Score %.1f is below excellence threshold',
                    $business['name'],
                    (float)$business['score']
                );
            }
            
            // Ensure pillar scores align with overall score
            if (!empty($business['pillar_scores']) && is_array($business['pillar_scores'])) {
                // Validate pillar scores are numeric
                $valid_pillar_scores = array();
                $has_invalid_pillars = false;
                
                foreach ($business['pillar_scores'] as $pillar => $score) {
                    if (is_numeric($score)) {
                        $valid_pillar_scores[$pillar] = (float)$score;
                    } else {
                        $has_invalid_pillars = true;
                        error_log('[ZipPicks Enhanced] WARNING: Invalid pillar score for ' . $business['name'] . ' - ' . $pillar . ': ' . gettype($score));
                    }
                }
                
                if (!$has_invalid_pillars && count($valid_pillar_scores) > 0) {
                    $avg_pillar = array_sum($valid_pillar_scores) / count($valid_pillar_scores);
                    if (is_numeric($business['score']) && abs($avg_pillar - (float)$business['score']) > 0.5) {
                        $validation['pillars_valid'] = false;
                        $report['warnings'][] = sprintf(
                            '%s: Pillar average %.1f doesn\'t match overall score %.1f',
                            $business['name'],
                            $avg_pillar,
                            (float)$business['score']
                        );
                    }
                } else if ($has_invalid_pillars) {
                    $validation['pillars_valid'] = false;
                    $report['warnings'][] = sprintf(
                        '%s: Contains invalid pillar scores',
                        $business['name']
                    );
                }
            }
            
            // Add to validated list if passes checks
            if ($validation['neighborhood_valid'] && $validation['score_valid']) {
                // Ensure all data is properly sanitized before adding
                $sanitized_business = $this->sanitize_business_data($business);
                $sanitized_business['validation'] = $validation;
                $validated[] = $sanitized_business;
                $report['validated']++;
            } else {
                $report['removed']++;
            }
            
            $position++;
        }
        
        return array(
            'businesses' => $validated,
            'report' => $report
        );
    }
    
    /**
     * Calculate confidence score for recommendations
     *
     * @param array $validated
     * @param array $params
     * @return float
     */
    private function calculate_confidence($validated, $params) {
        $confidence = 100.0;
        
        // Deduct for validation issues
        $total = $validated['report']['total'];
        $removed = $validated['report']['removed'];
        
        if ($removed > 0) {
            $confidence -= ($removed / $total) * 20;
        }
        
        // Deduct for warnings
        $warning_count = count($validated['report']['warnings']);
        $confidence -= min($warning_count * 2, 20);
        
        // Deduct if too few results
        if (count($validated['businesses']) < 5) {
            $confidence -= 15;
        }
        
        // Boost for known good cities
        $high_confidence_cities = ['New York', 'Los Angeles', 'Chicago', 'San Francisco', 'Austin'];
        if (in_array($params['location'], $high_confidence_cities)) {
            $confidence += 5;
        }
        
        return max(0, min(100, $confidence));
    }
    
    /**
     * Get recent trends for location/category
     *
     * @param string $location
     * @param string $category
     * @return string
     */
    private function get_recent_trends($location, $category) {
        // This could pull from a database of recent openings, closures, etc.
        // For now, return a prompt addition that encourages current information
        
        return "\n\nRECENT CONSIDERATIONS:\n" .
               "- Prioritize businesses that are currently thriving (not coasting on past reputation)\n" .
               "- Include notable recent openings if they've already proven excellence\n" .
               "- Avoid places with recent quality decline or controversy\n" .
               "- Consider pandemic resilience and current operational status";
    }
    
    /**
     * Parse location string into city and state
     *
     * @param string $location
     * @return array
     */
    private function parse_location($location) {
        // Handle various location formats
        $location = trim($location);
        
        // Check for "City, State" format
        if (strpos($location, ',') !== false) {
            $parts = explode(',', $location);
            return array(
                'city' => trim($parts[0]),
                'state' => trim($parts[1] ?? '')
            );
        }
        
        // Check for "City State" format (last word is state)
        $parts = explode(' ', $location);
        if (count($parts) > 1) {
            $state = array_pop($parts);
            $city = implode(' ', $parts);
            
            // Check if last part looks like a state code
            if (strlen($state) == 2 && ctype_alpha($state)) {
                return array(
                    'city' => $city,
                    'state' => strtoupper($state)
                );
            }
        }
        
        // Default to treating entire string as city
        return array(
            'city' => $location,
            'state' => ''
        );
    }
    
    /**
     * Format ZipBusiness API data for inclusion in prompt
     *
     * @param array $restaurant_data
     * @param string $category
     * @return string
     */
    private function format_zipbusiness_data_for_prompt($restaurant_data, $category) {
        $formatted = "\n\n🔍 VERIFIED RESTAURANT DATA FROM ZIPBUSINESS API 🔍\n";
        $formatted .= "The following restaurants are confirmed to exist in this location:\n\n";
        
        // Filter restaurants by category if applicable
        $relevant_restaurants = array();
        foreach ($restaurant_data as $restaurant) {
            // Check if restaurant matches the category
            if ($category === 'restaurant' || 
                (isset($restaurant['cuisine']) && stripos($restaurant['cuisine'], $category) !== false) ||
                (isset($restaurant['category']) && stripos($restaurant['category'], $category) !== false)) {
                $relevant_restaurants[] = $restaurant;
            }
        }
        
        // If no category-specific restaurants, use all
        if (empty($relevant_restaurants)) {
            $relevant_restaurants = $restaurant_data;
        }
        
        // Limit to top 50 most relevant
        $relevant_restaurants = array_slice($relevant_restaurants, 0, 50);
        
        foreach ($relevant_restaurants as $restaurant) {
            $formatted .= sprintf(
                "- %s (ZPID: %s)\n",
                $restaurant['name'] ?? 'Unknown',
                $restaurant['zpid'] ?? 'Unknown'
            );
            
            if (!empty($restaurant['address'])) {
                $formatted .= "  Address: " . $restaurant['address'] . "\n";
            }
            if (!empty($restaurant['cuisine'])) {
                $formatted .= "  Cuisine: " . $restaurant['cuisine'] . "\n";
            }
            if (!empty($restaurant['price_range'])) {
                $formatted .= "  Price: " . $restaurant['price_range'] . "\n";
            }
            if (!empty($restaurant['rating'])) {
                $formatted .= "  Rating: " . $restaurant['rating'] . "\n";
            }
            $formatted .= "\n";
        }
        
        $formatted .= "\n📋 IMPORTANT INSTRUCTIONS:\n";
        $formatted .= "1. You MUST prioritize restaurants from the above verified list\n";
        $formatted .= "2. You may ONLY include restaurants that actually exist (verified by ZPID)\n";
        $formatted .= "3. If a restaurant is not in the verified list, do NOT include it\n";
        $formatted .= "4. Use the ZPID as proof of existence when available\n";
        $formatted .= "5. This ensures all recommendations are for REAL, VERIFIABLE establishments\n\n";
        
        return $formatted;
    }
    
    /**
     * Test prompt quality with known good examples
     *
     * @param string $prompt
     * @param array $expected_businesses
     * @return array
     */
    public function test_prompt_quality($prompt, $expected_businesses) {
        $result = $this->execute_ai_generation($prompt, 'anthropic');
        
        if ($result['success']) {
            $businesses = $this->parse_ai_response($result['data']);
            $matches = 0;
            
            foreach ($businesses as $business) {
                foreach ($expected_businesses as $expected) {
                    if (stripos($business['name'], $expected) !== false) {
                        $matches++;
                        break;
                    }
                }
            }
            
            return array(
                'success' => true,
                'accuracy' => ($matches / count($expected_businesses)) * 100,
                'matches' => $matches,
                'total_expected' => count($expected_businesses),
                'businesses_returned' => $businesses
            );
        }
        
        return array('success' => false, 'error' => $result['error']);
    }
    
    /**
     * Sanitize business data to ensure type safety
     *
     * @param array $business
     * @return array
     */
    private function sanitize_business_data($business) {
        $sanitized = array();
        
        // Required fields with type casting
        $sanitized['name'] = isset($business['name']) ? sanitize_text_field($business['name']) : '';
        $sanitized['score'] = isset($business['score']) ? (float)$business['score'] : 0.0;
        
        // Optional fields with safe defaults
        $sanitized['rank'] = isset($business['rank']) ? (int)$business['rank'] : 0;
        $sanitized['review_count'] = isset($business['review_count']) ? (int)$business['review_count'] : 0;
        $sanitized['price_tier'] = isset($business['price_tier']) ? sanitize_text_field($business['price_tier']) : '';
        $sanitized['summary'] = isset($business['summary']) ? wp_kses_post($business['summary']) : '';
        $sanitized['neighborhood'] = isset($business['neighborhood']) ? sanitize_text_field($business['neighborhood']) : '';
        
        // Handle arrays safely
        if (isset($business['top_dishes']) && is_array($business['top_dishes'])) {
            $sanitized['top_dishes'] = array_map('sanitize_text_field', $business['top_dishes']);
        } else {
            $sanitized['top_dishes'] = array();
        }
        
        if (isset($business['vibes']) && is_array($business['vibes'])) {
            $sanitized['vibes'] = array_map('sanitize_text_field', $business['vibes']);
        } else {
            $sanitized['vibes'] = array();
        }
        
        // Handle pillar scores with validation
        if (isset($business['pillar_scores']) && is_array($business['pillar_scores'])) {
            $sanitized['pillar_scores'] = array();
            foreach ($business['pillar_scores'] as $pillar => $score) {
                $sanitized_pillar = sanitize_key($pillar);
                if (is_numeric($score)) {
                    $sanitized['pillar_scores'][$sanitized_pillar] = (float)$score;
                }
            }
        } else {
            $sanitized['pillar_scores'] = array();
        }
        
        // Preserve any additional custom fields safely
        $protected_fields = array('name', 'score', 'rank', 'review_count', 'price_tier', 
                                 'summary', 'neighborhood', 'top_dishes', 'vibes', 'pillar_scores');
        
        foreach ($business as $key => $value) {
            if (!in_array($key, $protected_fields)) {
                if (is_string($value)) {
                    $sanitized[$key] = sanitize_text_field($value);
                } elseif (is_numeric($value)) {
                    $sanitized[$key] = $value;
                } elseif (is_array($value)) {
                    $sanitized[$key] = array_map('sanitize_text_field', $value);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get optimal model based on city size
     *
     * @param string $location
     * @return string
     */
    private function get_optimal_model($location) {
        // Check if Opus model is available (some API keys don't have access)
        $opus_available = get_option('zippicks_opus_available', 'unknown');
        
        // Major cities that deserve the best quality
        $major_cities = array(
            'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix',
            'Philadelphia', 'San Antonio', 'San Diego', 'Dallas', 'San Jose',
            'Austin', 'Jacksonville', 'Fort Worth', 'Columbus', 'Charlotte',
            'San Francisco', 'Indianapolis', 'Seattle', 'Denver', 'Washington',
            'Boston', 'El Paso', 'Nashville', 'Detroit', 'Oklahoma City',
            'Portland', 'Las Vegas', 'Memphis', 'Louisville', 'Baltimore',
            'Milwaukee', 'Albuquerque', 'Tucson', 'Fresno', 'Mesa',
            'Sacramento', 'Atlanta', 'Kansas City', 'Colorado Springs', 'Miami',
            'Raleigh', 'Omaha', 'Long Beach', 'Virginia Beach', 'Oakland',
            'Minneapolis', 'Tulsa', 'Tampa', 'Arlington', 'New Orleans'
        );
        
        // Check if location is a major city (case-insensitive)
        $location_lower = strtolower(trim($location));
        $is_major_city = false;
        foreach ($major_cities as $city) {
            if (strtolower($city) === $location_lower) {
                $is_major_city = true;
                break;
            }
        }
        
        // Select model based on city size and Opus availability
        if ($is_major_city && $opus_available !== 'false') {
            // Try Opus for major cities if not explicitly unavailable
            return 'claude-3-opus-20240229';
        }
        
        // Default to Sonnet for smaller cities or when Opus is unavailable
        return 'claude-3-sonnet-20240229';
    }
}