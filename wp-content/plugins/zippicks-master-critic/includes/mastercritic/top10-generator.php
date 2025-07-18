<?php
/**
 * Top 10 List Generator
 *
 * Generates verified Top 10 lists using real restaurant data from ZipBusiness API
 * and Claude AI for editorial content generation.
 *
 * @package ZipPicks_Master_Critic
 * @subpackage MasterCritic
 * @since 2.0.0
 */

class ZipPicks_Master_Critic_TopTenGenerator {
    
    /**
     * Minimum confidence score for restaurants
     */
    const MIN_CONFIDENCE = 0.6;
    
    /**
     * Minimum restaurants required to generate a list
     */
    const MIN_RESTAURANTS = 5;
    
    /**
     * Cache TTL for generated lists (7 days)
     */
    const CACHE_TTL = 604800;
    
    /**
     * Logger instance
     *
     * @var object|null
     */
    private $logger;
    
    /**
     * ZipBusiness API client
     *
     * @var ZipPicks_Master_Critic_ZipBusiness_API_Client
     */
    private $api_client;
    
    /**
     * AI Service instance
     *
     * @var ZipPicks_Master_Critic_AI_Service
     */
    private $ai_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize logger with null-safe pattern
        $this->logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize services
        $this->api_client = new ZipPicks_Master_Critic_ZipBusiness_API_Client();
        $this->ai_service = new ZipPicks_Master_Critic_AI_Service();
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        $required_files = [
            'includes/services/class-zipbusiness-api-client.php' => 'ZipPicks_Master_Critic_ZipBusiness_API_Client',
            'includes/class-ai-service.php' => 'ZipPicks_Master_Critic_AI_Service',
            'includes/mastercritic/helpers/prompt-builder.php' => 'ZipPicks_Master_Critic_PromptBuilder',
            'includes/mastercritic/helpers/CityStateHelper.php' => 'ZipPicks_Master_Critic_CityStateHelper'
        ];
        
        foreach ($required_files as $file => $class) {
            if (!class_exists($class)) {
                $file_path = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                } else {
                    throw new RuntimeException("Required file not found: {$file}");
                }
            }
        }
    }
    
    /**
     * Generate a Top 10 list for a city and vibe
     *
     * @param string $city City name
     * @param string $vibe_slug Vibe slug
     * @param array $options Optional parameters (force regeneration, etc.)
     * @return array|WP_Error Generated list data or error
     */
    public function generate_list($city, $vibe_slug, $options = []) {
        // Validate inputs
        if (empty($city) || empty($vibe_slug)) {
            $this->log_error('Invalid input: missing city or vibe', [
                'city' => $city,
                'vibe_slug' => $vibe_slug
            ]);
            return new WP_Error('invalid_input', 'City and vibe slug are required');
        }
        
        // Sanitize inputs
        $city = sanitize_text_field($city);
        $vibe_slug = sanitize_key($vibe_slug);
        
        $this->log_info('Starting Top 10 list generation', [
            'city' => $city,
            'vibe_slug' => $vibe_slug,
            'force' => !empty($options['force']),
            'options' => $options
        ]);
        
        // Check cache unless force regeneration is requested
        if (empty($options['force'])) {
            $cached = $this->get_cached_list($city, $vibe_slug);
            if ($cached !== false && is_array($cached)) {
                $this->log_info('Using cached Top 10 list', [
                    'city' => $city,
                    'vibe' => $vibe_slug
                ]);
                return $cached;
            }
        }
        
        try {
            // Step 1: Fetch restaurants from API
            $this->log_info('Step 1: Fetching restaurants from API');
            $restaurants = $this->fetch_restaurants($city, $vibe_slug);
            
            if (is_wp_error($restaurants)) {
                $this->log_error('Failed to fetch restaurants', [
                    'error_code' => $restaurants->get_error_code(),
                    'error_message' => $restaurants->get_error_message()
                ]);
                return $restaurants;
            }
            
            if (!is_array($restaurants)) {
                $this->log_error('Invalid restaurant data type', [
                    'type' => gettype($restaurants)
                ]);
                return new WP_Error('invalid_data', 'Invalid restaurant data received');
            }
            
            $this->log_info('Fetched restaurants from API', [
                'count' => count($restaurants)
            ]);
            
            // Step 2: Filter by confidence threshold
            $this->log_info('Step 2: Filtering by confidence threshold');
            $verified_restaurants = $this->filter_by_confidence($restaurants);
            
            $this->log_info('Filtered restaurants by confidence', [
                'original_count' => count($restaurants),
                'verified_count' => count($verified_restaurants),
                'confidence_threshold' => self::MIN_CONFIDENCE
            ]);
            
            // Step 3: Check minimum restaurant count
            if (count($verified_restaurants) < self::MIN_RESTAURANTS) {
                $error_msg = sprintf(
                    'Insufficient restaurants for Top 10 generation. Found %d restaurants with confidence >= %s for city: %s, vibe: %s',
                    count($verified_restaurants),
                    self::MIN_CONFIDENCE,
                    $city,
                    $vibe_slug
                );
                
                $this->log_error($error_msg, [
                    'verified_count' => count($verified_restaurants),
                    'min_required' => self::MIN_RESTAURANTS
                ]);
                return new WP_Error('insufficient_data', $error_msg);
            }
            
            // Step 4: Build Claude prompt with verified restaurant names
            $this->log_info('Step 4: Building AI prompt');
            $vibe_name = $this->get_vibe_name($vibe_slug);
            $restaurant_names = array_column($verified_restaurants, 'name');
            
            // Ensure unique restaurant names
            $restaurant_names = array_unique($restaurant_names);
            
            // Sort restaurants by confidence for better selection
            usort($verified_restaurants, function($a, $b) {
                $confidence_a = floatval($a['confidence_score'] ?? 0);
                $confidence_b = floatval($b['confidence_score'] ?? 0);
                return $confidence_b <=> $confidence_a;
            });
            
            // Take top candidates if we have more than needed
            if (count($restaurant_names) > 20) {
                $restaurant_names = array_slice($restaurant_names, 0, 20);
            }
            
            $this->log_info('Building prompt with restaurants', [
                'city' => $city,
                'vibe_name' => $vibe_name,
                'restaurant_count' => count($restaurant_names)
            ]);
            
            $prompt = ZipPicks_Master_Critic_PromptBuilder::for_top10(
                $city,
                $vibe_name,
                $restaurant_names
            );
            
            $this->log_info('Prompt built', [
                'prompt_length' => strlen($prompt)
            ]);
            
            // Step 5: Send to Claude for editorial generation
            $this->log_info('Step 5: Sending to AI for generation');
            $ai_response = $this->generate_with_claude($prompt);
            
            if (is_wp_error($ai_response)) {
                $this->log_error('AI generation failed', [
                    'error_code' => $ai_response->get_error_code(),
                    'error_message' => $ai_response->get_error_message()
                ]);
                return $ai_response;
            }
            
            // Step 6: Parse and validate Claude response
            $parsed_list = $this->parse_ai_response($ai_response, $verified_restaurants);
            
            if (is_wp_error($parsed_list)) {
                return $parsed_list;
            }
            
            if (!is_array($parsed_list) || empty($parsed_list)) {
                return new WP_Error('parsing_failed', 'Failed to parse AI response into valid list');
            }
            
            // Step 7: Create final structured output
            $result = [
                'city' => $city,
                'city_slug' => sanitize_title($city),
                'vibe' => $vibe_name,
                'vibe_slug' => $vibe_slug,
                'restaurants' => $parsed_list,
                'metadata' => [
                    'generated_at' => current_time('mysql'),
                    'total_candidates' => count($restaurants),
                    'verified_candidates' => count($verified_restaurants),
                    'confidence_threshold' => self::MIN_CONFIDENCE,
                    'ai_provider' => $ai_response['provider'] ?? 'anthropic',
                    'model_used' => $ai_response['model'] ?? 'claude-3-sonnet'
                ]
            ];
            
            // Step 8: Cache the result
            $this->cache_list($city, $vibe_slug, $result);
            
            // Step 9: Log generation details
            $this->log_generation($city, $vibe_slug, $prompt, $ai_response, $result);
            
            $this->log_info('Successfully generated Top 10 list', [
                'city' => $city,
                'vibe' => $vibe_slug,
                'restaurants_count' => count($parsed_list)
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->log_error('Top 10 generation failed', [
                'city' => $city,
                'vibe' => $vibe_slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new WP_Error('generation_failed', $e->getMessage());
        }
    }
    
    /**
     * Fetch restaurants from ZipBusiness API
     *
     * @param string $city City name
     * @param string $vibe_slug Vibe slug
     * @return array|WP_Error Restaurant data or error
     */
    private function fetch_restaurants($city, $vibe_slug) {
        try {
            // Get state using CityStateHelper
            $state = $this->get_state_for_city($city);
            
            if (!$state) {
                return new WP_Error('invalid_city', "City '{$city}' not found in our database");
            }
            
            // Fetch all restaurants for the city
            $all_restaurants = $this->api_client->get_city_restaurants($city, $state);
            
            if (!is_array($all_restaurants)) {
                return new WP_Error('api_error', 'Invalid response from restaurant API');
            }
            
            if (empty($all_restaurants)) {
                return new WP_Error('no_restaurants', "No restaurants found for {$city}, {$state}");
            }
            
            // Filter by vibe if possible
            $filtered = $this->filter_by_vibe($all_restaurants, $vibe_slug);
            
            return $filtered;
            
        } catch (Exception $e) {
            $this->log_error('Failed to fetch restaurants', [
                'city' => $city,
                'vibe' => $vibe_slug,
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error('api_error', 'Failed to fetch restaurants: ' . $e->getMessage());
        }
    }
    
    /**
     * Filter restaurants by confidence score
     *
     * @param array $restaurants Restaurant data
     * @return array Filtered restaurants
     */
    private function filter_by_confidence($restaurants) {
        if (!is_array($restaurants)) {
            return [];
        }
        
        return array_filter($restaurants, function($restaurant) {
            if (!is_array($restaurant)) {
                return false;
            }
            
            $confidence = isset($restaurant['confidence_score']) ? 
                floatval($restaurant['confidence_score']) : 0;
            
            return $confidence >= self::MIN_CONFIDENCE;
        });
    }
    
    /**
     * Filter restaurants by vibe
     *
     * @param array $restaurants Restaurant data
     * @param string $vibe_slug Vibe slug
     * @return array Filtered restaurants
     */
    private function filter_by_vibe($restaurants, $vibe_slug) {
        if (!is_array($restaurants)) {
            return [];
        }
        
        // If restaurants have vibe data, filter by it
        $filtered = array_filter($restaurants, function($restaurant) use ($vibe_slug) {
            if (!is_array($restaurant)) {
                return false;
            }
            
            if (!empty($restaurant['vibes']) && is_array($restaurant['vibes'])) {
                return in_array($vibe_slug, $restaurant['vibes'], true);
            }
            
            // If no vibe data, include all for Claude to decide
            return true;
        });
        
        // If filtering reduces results too much, return all restaurants
        if (count($filtered) < self::MIN_RESTAURANTS) {
            return $restaurants;
        }
        
        return array_values($filtered);
    }
    
    /**
     * Generate content with Claude
     *
     * @param string $prompt The prompt to send
     * @return array|WP_Error AI response or error
     */
    private function generate_with_claude($prompt) {
        try {
            $this->log_info('Calling AI service for generation');
            
            $response = $this->ai_service->execute_ai_generation($prompt, 'anthropic');
            
            if (!is_array($response)) {
                $this->log_error('Invalid AI service response type', [
                    'response_type' => gettype($response)
                ]);
                return new WP_Error('ai_error', 'Invalid AI service response');
            }
            
            if (empty($response['success'])) {
                $this->log_error('AI generation returned failure', [
                    'error' => $response['error'] ?? 'Unknown error',
                    'raw_response' => isset($response['raw_response']) ? substr($response['raw_response'], 0, 500) : 'N/A',
                    'http_code' => $response['http_code'] ?? 'N/A',
                    'execution_time' => $response['execution_time'] ?? 'N/A'
                ]);
                return new WP_Error('ai_error', $response['error'] ?? 'AI generation failed');
            }
            
            $this->log_info('AI generation successful', [
                'data_length' => strlen($response['data'] ?? ''),
                'provider' => $response['provider'] ?? 'unknown',
                'model' => $response['model'] ?? 'unknown',
                'execution_time' => $response['execution_time'] ?? 'N/A'
            ]);
            
            return $response;
            
        } catch (Exception $e) {
            $this->log_error('AI service exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return new WP_Error('ai_exception', 'AI service error: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse AI response and match with restaurant data
     *
     * @param array $ai_response AI service response
     * @param array $verified_restaurants Verified restaurant data
     * @return array|WP_Error Parsed list or error
     */
    private function parse_ai_response($ai_response, $verified_restaurants) {
        if (!is_array($ai_response) || empty($ai_response['data'])) {
            return new WP_Error('invalid_response', 'Empty or invalid AI response');
        }
        
        if (!is_array($verified_restaurants)) {
            return new WP_Error('invalid_data', 'Invalid restaurant data for parsing');
        }
        
        // Create lookup map for restaurants by name
        $restaurant_map = [];
        foreach ($verified_restaurants as $restaurant) {
            if (!is_array($restaurant) || empty($restaurant['name'])) {
                continue;
            }
            
            $normalized_name = $this->normalize_name($restaurant['name']);
            $restaurant_map[$normalized_name] = $restaurant;
        }
        
        // Parse the AI response
        $content = $ai_response['data'];
        
        // Try to parse as JSON first
        $json_match = [];
        if (preg_match('/\{[\s\S]*\}|\[[\s\S]*\]/m', $content, $json_match)) {
            $parsed = json_decode($json_match[0], true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($parsed)) {
                $result = $this->process_json_response($parsed, $restaurant_map);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        
        // Fallback to text parsing
        return $this->process_text_response($content, $restaurant_map);
    }
    
    /**
     * Process JSON response from Claude
     *
     * @param array $parsed Parsed JSON data
     * @param array $restaurant_map Restaurant lookup map
     * @return array Processed list
     */
    private function process_json_response($parsed, $restaurant_map) {
        $result = [];
        
        if (!is_array($parsed)) {
            return $result;
        }
        
        // Handle different JSON structures
        $items = $parsed;
        if (isset($parsed['restaurants']) && is_array($parsed['restaurants'])) {
            $items = $parsed['restaurants'];
        } elseif (isset($parsed['top10']) && is_array($parsed['top10'])) {
            $items = $parsed['top10'];
        } elseif (isset($parsed['list']) && is_array($parsed['list'])) {
            $items = $parsed['list'];
        }
        
        // Ensure we're working with an array
        if (!is_array($items)) {
            return $result;
        }
        
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            
            $name = $item['name'] ?? $item['restaurant'] ?? '';
            if (empty($name)) {
                continue;
            }
            
            $normalized_name = $this->normalize_name($name);
            
            if (isset($restaurant_map[$normalized_name])) {
                $restaurant_data = $restaurant_map[$normalized_name];
                
                $result[] = [
                    'rank' => intval($item['rank'] ?? count($result) + 1),
                    'name' => $restaurant_data['name'],
                    'zpid' => $restaurant_data['zpid'] ?? '',
                    'address' => $restaurant_data['address'] ?? '',
                    'zip' => $restaurant_data['zip'] ?? '',
                    'confidence_score' => floatval($restaurant_data['confidence_score'] ?? 0),
                    'editorial_summary' => sanitize_text_field($item['summary'] ?? $item['description'] ?? ''),
                    'ai_reasoning' => sanitize_text_field($item['reasoning'] ?? '')
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Process text response from Claude
     *
     * @param string $content Text content
     * @param array $restaurant_map Restaurant lookup map
     * @return array Processed list
     */
    private function process_text_response($content, $restaurant_map) {
        $result = [];
        
        if (!is_string($content) || empty($content)) {
            return $result;
        }
        
        // Split by numbered lines (1. Restaurant Name, 2. Restaurant Name, etc.)
        $pattern = '/(\d+)\.\s*([^:\n]+)(?::?\s*(.*))?/m';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $rank = intval($match[1]);
            $name = trim($match[2]);
            $description = trim($match[3] ?? '');
            
            if (empty($name)) {
                continue;
            }
            
            $normalized_name = $this->normalize_name($name);
            
            if (isset($restaurant_map[$normalized_name])) {
                $restaurant_data = $restaurant_map[$normalized_name];
                
                $result[] = [
                    'rank' => $rank,
                    'name' => $restaurant_data['name'],
                    'zpid' => $restaurant_data['zpid'] ?? '',
                    'address' => $restaurant_data['address'] ?? '',
                    'zip' => $restaurant_data['zip'] ?? '',
                    'confidence_score' => floatval($restaurant_data['confidence_score'] ?? 0),
                    'editorial_summary' => sanitize_text_field($description)
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Normalize restaurant name for matching
     *
     * @param string $name Restaurant name
     * @return string Normalized name
     */
    private function normalize_name($name) {
        if (!is_string($name)) {
            return '';
        }
        
        // Remove special characters and normalize
        $normalized = strtolower($name);
        $normalized = preg_replace('/[^a-z0-9\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }
    
    /**
     * Get cached list
     *
     * @param string $city City name
     * @param string $vibe_slug Vibe slug
     * @return array|false Cached data or false
     */
    private function get_cached_list($city, $vibe_slug) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_top10_cache';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return false;
        }
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE city_slug = %s AND vibe_slug = %s 
             AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            sanitize_title($city),
            $vibe_slug
        ), ARRAY_A);
        
        if ($result && !empty($result['ai_response'])) {
            $decoded = json_decode($result['ai_response'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        return false;
    }
    
    /**
     * Cache generated list
     *
     * @param string $city City name
     * @param string $vibe_slug Vibe slug
     * @param array $data List data
     */
    private function cache_list($city, $vibe_slug, $data) {
        global $wpdb;
        
        if (!is_array($data)) {
            return;
        }
        
        $table = $wpdb->prefix . 'zippicks_top10_cache';
        
        // Create table if it doesn't exist
        $this->ensure_cache_table_exists();
        
        $restaurant_names = [];
        if (!empty($data['restaurants']) && is_array($data['restaurants'])) {
            $restaurant_names = array_column($data['restaurants'], 'name');
        }
        
        $wpdb->replace($table, [
            'city_slug' => sanitize_title($city),
            'vibe_slug' => sanitize_key($vibe_slug),
            'restaurant_names' => json_encode($restaurant_names),
            'full_prompt' => '', // We store this in the log table instead
            'ai_response' => json_encode($data),
            'confidence_avg' => $this->calculate_average_confidence($data['restaurants'] ?? []),
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Ensure cache table exists
     */
    private function ensure_cache_table_exists() {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/mastercritic/schema/top10-cache-schema.php';
        ZipPicks_Master_Critic_Top10_Cache_Schema::create_table();
    }
    
    /**
     * Log generation details
     *
     * @param string $city City name
     * @param string $vibe_slug Vibe slug
     * @param string $prompt Full prompt
     * @param array $ai_response AI response
     * @param array $result Final result
     */
    private function log_generation($city, $vibe_slug, $prompt, $ai_response, $result) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_top10_generation_log';
        
        // Create log table if needed
        $this->ensure_log_table_exists();
        
        $wpdb->insert($table, [
            'city' => sanitize_text_field($city),
            'vibe_slug' => sanitize_key($vibe_slug),
            'prompt' => wp_kses_post($prompt),
            'ai_response' => json_encode($ai_response),
            'result' => json_encode($result),
            'confidence_avg' => $this->calculate_average_confidence($result['restaurants'] ?? []),
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Ensure log table exists
     */
    private function ensure_log_table_exists() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'zippicks_top10_generation_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            city varchar(100) NOT NULL,
            vibe_slug varchar(100) NOT NULL,
            prompt text NOT NULL,
            ai_response longtext NOT NULL,
            result longtext NOT NULL,
            confidence_avg float DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY city_vibe (city, vibe_slug),
            KEY created_at (created_at)
        ) {$charset_collate}";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Calculate average confidence score
     *
     * @param array $restaurants Restaurant list
     * @return float Average confidence
     */
    private function calculate_average_confidence($restaurants) {
        if (!is_array($restaurants) || empty($restaurants)) {
            return 0;
        }
        
        $total = 0;
        $count = 0;
        
        foreach ($restaurants as $restaurant) {
            if (is_array($restaurant) && isset($restaurant['confidence_score'])) {
                $total += floatval($restaurant['confidence_score']);
                $count++;
            }
        }
        
        return $count > 0 ? round($total / $count, 3) : 0;
    }
    
    /**
     * Get vibe display name from slug
     *
     * @param string $vibe_slug Vibe slug
     * @return string Vibe display name
     */
    private function get_vibe_name($vibe_slug) {
        // Load vibe integration service if available
        $vibe_service_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/services/class-vibe-integration-service.php';
        
        if (file_exists($vibe_service_file)) {
            require_once $vibe_service_file;
            
            if (class_exists('ZipPicks_Master_Critic_Vibe_Integration_Service')) {
                $vibe_service = new ZipPicks_Master_Critic_Vibe_Integration_Service();
                $vibe = $vibe_service->get_vibe_by_slug($vibe_slug);
                if ($vibe && isset($vibe['name'])) {
                    return $vibe['name'];
                }
            }
        }
        
        // Fallback to title case conversion
        return ucwords(str_replace(['-', '_'], ' ', $vibe_slug));
    }
    
    /**
     * Get state for city using CityStateHelper
     *
     * @param string $city City name or slug
     * @return string|null State code or null if not found
     */
    private function get_state_for_city($city) {
        $state = ZipPicks_Master_Critic_CityStateHelper::get_state_for_city($city);
        
        if (!$state) {
            // Log warning for unknown city
            $this->log_error("Unknown city: '{$city}'. Please ensure it exists in city_list.py");
        }
        
        return $state;
    }
    
    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    private function log_info($message, $context = []) {
        if ($this->logger) {
            $this->logger->info('TopTenGenerator: ' . $message, $context);
        }
    }
    
    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    private function log_error($message, $context = []) {
        if ($this->logger) {
            $this->logger->error('TopTenGenerator: ' . $message, $context);
        }
        
        // Always log to error_log for critical errors
        error_log('ZipPicks TopTenGenerator Error: ' . $message . ' ' . json_encode($context));
    }
}