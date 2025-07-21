<?php
/**
 * Business Manager Service
 *
 * Core business logic for creating, updating, and managing businesses.
 * Migrated and enhanced from Master Critic plugin.
 */
class ZipPicks_Business_Manager {
    
    private $database;
    private $cache;
    private $logger;
    
    public function __construct() {
        $this->database = new ZipPicks_Business_Database();
        
        // Use Foundation services if available
        if (function_exists('zippicks')) {
            $this->cache = zippicks()->has('cache') ? zippicks()->get('cache') : null;
            $this->logger = zippicks()->has('logger') ? zippicks()->get('logger') : null;
        }
    }
    
    /**
     * Create businesses from AI-generated data (migrated from Master Critic)
     *
     * @param array $businesses Array of business data
     * @param array $context Context information (source, list_id, etc.)
     * @return array Results with created IDs and any errors
     */
    public function bulk_create_from_ai($businesses, $context = array()) {
        $created_count = 0;
        $created_ids = array();
        $errors = array();
        
        // Log the operation
        if ($this->logger) {
            $this->logger->info('Bulk creating businesses from AI', array(
                'count' => count($businesses),
                'context' => $context
            ));
        }
        
        foreach ($businesses as $index => $business_data) {
            try {
                // Validate business data
                $validated = $this->validate_business_data($business_data);
                if (!$validated['valid']) {
                    $business_name = isset($business_data['name']) ? $business_data['name'] : 'Unknown';
                    $errors[] = "Business {$index} ({$business_name}): " . implode(', ', $validated['errors']);
                    continue;
                }
                
                // Check for duplicates
                if ($this->business_exists($validated['data']['name'])) {
                    $errors[] = "Business {$index} ({$validated['data']['name']}): Already exists";
                    continue;
                }
                
                // Create business
                $business_id = $this->create_business($validated['data'], $context);
                if ($business_id) {
                    $created_ids[] = $business_id;
                    $created_count++;
                    
                    // Track analytics
                    ZipPicks_Business_Database::track_event(
                        $business_id, 
                        'created_from_ai', 
                        isset($context['source']) ? $context['source'] : 'unknown'
                    );
                }
                
            } catch (Exception $e) {
                $errors[] = "Business {$index}: " . $e->getMessage();
                if ($this->logger) {
                    $this->logger->error('Business creation failed', array(
                        'business_data' => $business_data,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ));
                }
            }
        }
        
        // Fire action for other plugins
        if ($created_count > 0) {
            do_action('zippicks_businesses_created', $created_ids, $context);
        }
        
        // Clear caches
        $this->clear_business_caches();
        
        return array(
            'success' => $created_count > 0,
            'created_count' => $created_count,
            'business_ids' => $created_ids,
            'errors' => $errors,
            'total_attempted' => count($businesses)
        );
    }
    
    /**
     * Create a single business
     *
     * @param array $data Validated business data
     * @param array $context Additional context
     * @return int|false Business ID or false on failure
     */
    public function create_business($data, $context = array()) {
        // Prepare post data
        $post_data = array(
            'post_title' => $data['name'],
            'post_content' => isset($data['summary']) ? $data['summary'] : '',
            'post_excerpt' => isset($data['tagline']) ? $data['tagline'] : '',
            'post_type' => 'zippicks_business',
            'post_status' => 'publish',
            'meta_input' => array(
                '_zp_score' => $data['score'],
                '_zp_review_count' => $data['review_count'],
                '_zp_price_tier' => $data['price_tier'],
                '_zp_pillar_scores' => isset($data['pillar_scores']) ? $data['pillar_scores'] : array(),
                '_zp_top_features' => isset($data['top_features']) ? $data['top_features'] : array(),
                '_zp_creation_source' => isset($context['source']) ? $context['source'] : 'manual',
                '_zp_listing_tier' => 'basic', // Default tier
                '_zp_verified' => false,
                '_zp_address' => isset($data['address']) ? $data['address'] : '',
                '_zp_phone' => isset($data['phone']) ? $data['phone'] : '',
                '_zp_website' => isset($data['website']) ? $data['website'] : '',
                '_zp_hours' => isset($data['hours']) ? $data['hours'] : array(),
                '_zp_created_from_list' => isset($context['list_id']) ? $context['list_id'] : null
            )
        );
        
        // Add author if specified
        if (isset($context['author_id'])) {
            $post_data['post_author'] = $context['author_id'];
        }
        
        // Create post
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }
        
        // Assign vibes if the taxonomy exists (from vibes plugin)
        if (!empty($data['vibes']) && taxonomy_exists('zippicks_vibes')) {
            wp_set_object_terms($post_id, $data['vibes'], 'zippicks_vibes');
        }
        
        // Create initial monetization record
        $this->create_monetization_record($post_id, 'basic');
        
        // Log success
        if ($this->logger) {
            $this->logger->info('Business created', array(
                'business_id' => $post_id,
                'name' => $data['name'],
                'source' => isset($context['source']) ? $context['source'] : 'manual'
            ));
        }
        
        return $post_id;
    }
    
    /**
     * Validate business data (migrated and enhanced from Master Critic)
     *
     * @param array $data Raw business data
     * @return array Validation result with valid flag, data, and errors
     */
    public function validate_business_data($data) {
        $errors = array();
        $validated = array();
        
        // Required fields
        $required = array('name', 'summary', 'score', 'review_count', 'price_tier');
        foreach ($required as $field) {
            if (empty($data[$field]) && $data[$field] !== 0) {
                $errors[] = "Missing required field: {$field}";
            }
        }
        
        if (!empty($errors)) {
            return array('valid' => false, 'errors' => $errors);
        }
        
        // Validate and sanitize name
        $validated['name'] = sanitize_text_field($data['name']);
        if (strlen($validated['name']) < 2) {
            $errors[] = "Business name too short";
        }
        if (strlen($validated['name']) > 200) {
            $errors[] = "Business name too long (max 200 characters)";
        }
        
        // Validate and sanitize summary
        $validated['summary'] = wp_kses_post($data['summary']);
        if (strlen($validated['summary']) < 10) {
            $errors[] = "Summary too short (min 10 characters)";
        }
        if (strlen($validated['summary']) > 5000) {
            $errors[] = "Summary too long (max 5000 characters)";
        }
        
        // Validate score
        $validated['score'] = floatval($data['score']);
        if ($validated['score'] < 0 || $validated['score'] > 10) {
            $errors[] = "Score must be between 0 and 10";
        }
        
        // Validate review count
        $validated['review_count'] = intval($data['review_count']);
        if ($validated['review_count'] < 0) {
            $errors[] = "Review count cannot be negative";
        }
        
        // Validate price tier
        $validated['price_tier'] = sanitize_text_field($data['price_tier']);
        if (!preg_match('/^\\$+$/', $validated['price_tier']) || strlen($validated['price_tier']) > 4) {
            $errors[] = "Invalid price tier format (use $, $$, $$$, or $$$$)";
        }
        
        // Validate optional fields
        if (isset($data['tagline'])) {
            $validated['tagline'] = sanitize_text_field($data['tagline']);
        }
        
        if (isset($data['address'])) {
            $validated['address'] = sanitize_textarea_field($data['address']);
        }
        
        if (isset($data['phone'])) {
            $validated['phone'] = preg_replace('/[^0-9+\-\(\) ]/', '', $data['phone']);
        }
        
        if (isset($data['website'])) {
            $validated['website'] = esc_url_raw($data['website']);
            if (!empty($validated['website']) && !filter_var($validated['website'], FILTER_VALIDATE_URL)) {
                $errors[] = "Invalid website URL";
            }
        }
        
        // Validate pillar scores if present
        if (isset($data['pillar_scores']) && is_array($data['pillar_scores'])) {
            $validated['pillar_scores'] = array();
            foreach ($data['pillar_scores'] as $pillar => $score) {
                $pillar_key = sanitize_key($pillar);
                $pillar_score = floatval($score);
                if ($pillar_score >= 0 && $pillar_score <= 10) {
                    $validated['pillar_scores'][$pillar_key] = $pillar_score;
                } else {
                    $errors[] = "Invalid pillar score for {$pillar}: must be 0-10";
                }
            }
        }
        
        // Validate vibes
        if (isset($data['vibes']) && is_array($data['vibes'])) {
            $validated['vibes'] = array_map('sanitize_text_field', $data['vibes']);
            // Remove empty values
            $validated['vibes'] = array_filter($validated['vibes']);
        }
        
        // Validate top features
        if (isset($data['top_features']) && is_array($data['top_features'])) {
            $validated['top_features'] = array_map('sanitize_text_field', $data['top_features']);
            // Limit to 10 features
            $validated['top_features'] = array_slice($validated['top_features'], 0, 10);
        }
        
        // Validate hours if present
        if (isset($data['hours']) && is_array($data['hours'])) {
            $validated['hours'] = array();
            $valid_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
            foreach ($data['hours'] as $day => $hours) {
                if (in_array(strtolower($day), $valid_days)) {
                    $validated['hours'][strtolower($day)] = sanitize_text_field($hours);
                }
            }
        }
        
        return array(
            'valid' => empty($errors),
            'data' => $validated,
            'errors' => $errors
        );
    }
    
    /**
     * Check if business already exists
     *
     * @param string $name Business name
     * @return bool
     */
    private function business_exists($name) {
        $existing = get_page_by_title($name, OBJECT, 'zippicks_business');
        return !empty($existing);
    }
    
    /**
     * Create monetization record for new business
     *
     * @param int $business_id
     * @param string $tier
     */
    private function create_monetization_record($business_id, $tier = 'basic') {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_business_monetization',
            array(
                'business_id' => $business_id,
                'tier' => $tier,
                'subscription_status' => 'active',
                'features' => json_encode($this->get_tier_features($tier)),
                'started_at' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get features for a tier
     *
     * @param string $tier
     * @return array
     */
    private function get_tier_features($tier) {
        $tiers = get_option('zippicks_business_tiers', array());
        return isset($tiers[$tier]['features']) ? $tiers[$tier]['features'] : array();
    }
    
    /**
     * Clear business-related caches
     */
    private function clear_business_caches() {
        if ($this->cache) {
            $this->cache->delete('zippicks_businesses_all');
            $this->cache->delete('zippicks_businesses_featured');
            $this->cache->delete('zippicks_businesses_verified');
            $this->cache->delete('zippicks_business_stats');
        }
        
        // Clear WordPress caches
        wp_cache_delete('all_businesses', 'zippicks');
        wp_cache_delete('featured_businesses', 'zippicks');
    }
    
    /**
     * Update business monetization tier
     *
     * @param int $business_id
     * @param string $tier
     * @return bool
     */
    public function update_business_tier($business_id, $tier) {
        global $wpdb;
        
        // Update post meta
        update_post_meta($business_id, '_zp_listing_tier', $tier);
        
        // Update monetization table
        $result = $wpdb->update(
            $wpdb->prefix . 'zippicks_business_monetization',
            array(
                'tier' => $tier,
                'features' => json_encode($this->get_tier_features($tier)),
                'updated_at' => current_time('mysql')
            ),
            array('business_id' => $business_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Track event
        ZipPicks_Business_Database::track_event($business_id, 'tier_changed', $tier);
        
        // Clear caches
        $this->clear_business_caches();
        
        return $result !== false;
    }
    
    /**
     * Verify a business
     *
     * @param int $business_id
     * @param string $verification_type
     * @param array $verification_data
     * @return bool
     */
    public function verify_business($business_id, $verification_type = 'manual', $verification_data = array()) {
        global $wpdb;
        
        // Update post meta
        update_post_meta($business_id, '_zp_verified', true);
        update_post_meta($business_id, '_zp_verified_at', current_time('mysql'));
        update_post_meta($business_id, '_zp_verified_by', get_current_user_id());
        
        // Insert verification record
        $result = $wpdb->insert(
            $wpdb->prefix . 'zippicks_business_verification',
            array(
                'business_id' => $business_id,
                'verification_type' => $verification_type,
                'verification_status' => 'verified',
                'verification_data' => json_encode($verification_data),
                'verified_by' => get_current_user_id(),
                'verified_at' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        // Track event
        ZipPicks_Business_Database::track_event($business_id, 'verified', $verification_type);
        
        // Fire action
        do_action('zippicks_business_verified', $business_id, $verification_type);
        
        return $result !== false;
    }
}