<?php
/**
 * Plugin installer class
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Installer {
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';
    
    /**
     * Run the installer
     *
     * @return void
     */
    public static function install() {
        // Log activation attempt
        error_log('ZipPicks Master Critic: Starting activation...');
        
        // Load required files
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database.php';
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database-migrator.php';
        
        // First, try direct table creation to ensure tables exist
        try {
            ZipPicks_Master_Critic_Database::create_tables();
            error_log('ZipPicks Master Critic: Direct table creation completed');
        } catch (Exception $e) {
            error_log('ZipPicks Master Critic: Direct table creation failed - ' . $e->getMessage());
        }
        
        // Then run migrations to ensure we're at the correct version
        try {
            $migration_result = ZipPicks_Master_Critic_Database_Migrator::run_migrations();
            error_log('ZipPicks Master Critic: Migration result - ' . json_encode($migration_result));
            
            // Store migration result for admin notice
            update_option('zippicks_master_critic_activation_result', $migration_result);
        } catch (Exception $e) {
            error_log('ZipPicks Master Critic: Migration failed - ' . $e->getMessage());
        }
        
        // Verify tables exist
        $tables_exist = self::tables_exist();
        error_log('ZipPicks Master Critic: Tables exist check - ' . ($tables_exist ? 'YES' : 'NO'));
        
        // Set default options
        self::set_default_options();
        
        // Create capabilities
        self::create_capabilities();
        
        // Store plugin version (separate from database version)
        update_option('zippicks_master_critic_version', self::VERSION);
        
        // Create default prompt templates
        self::create_default_templates();
        
        // Register with Foundation if available
        try {
            self::register_with_foundation();
        } catch (Exception $e) {
            error_log('ZipPicks Master Critic: Foundation registration failed - ' . $e->getMessage());
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        error_log('ZipPicks Master Critic: Activation completed');
    }
    
    /**
     * Check if tables exist
     *
     * @return bool
     */
    public static function tables_exist() {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database.php';
        return ZipPicks_Master_Critic_Database::verify_tables();
    }
    
    /**
     * Set default plugin options
     *
     * @return void
     */
    private static function set_default_options() {
        // API settings
        add_option('zippicks_anthropic_api_key', '');
        add_option('zippicks_openai_api_key', '');
        add_option('zippicks_default_ai_provider', 'anthropic');
        
        // Rate limiting
        add_option('zippicks_ai_rate_limit_per_minute', 20);
        add_option('zippicks_ai_rate_limit_per_day', 1000);
        
        // Cache settings
        add_option('zippicks_ai_cache_ttl', 3600); // 1 hour
        
        // Feature flags
        add_option('zippicks_enable_prompt_templates', true);
        add_option('zippicks_enable_auto_business_creation', true);
        
        // Business categories configuration
        add_option('zippicks_business_categories', self::get_default_categories());
    }
    
    /**
     * Create capabilities
     *
     * @return void
     */
    private static function create_capabilities() {
        $role = get_role('administrator');
        
        if ($role) {
            $role->add_cap('manage_zippicks_master_critic');
            $role->add_cap('use_zippicks_ai_generation');
            $role->add_cap('manage_zippicks_prompt_templates');
        }
        
        // Also add to editor role
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('use_zippicks_ai_generation');
        }
    }
    
    /**
     * Get default business categories configuration
     *
     * @return array
     */
    private static function get_default_categories() {
        return array(
            'restaurant' => array(
                'label' => 'Restaurant',
                'plural' => 'restaurants',
                'pillars' => array(
                    'food_quality' => 'Food Quality',
                    'service' => 'Service',
                    'atmosphere_design' => 'Atmosphere & Design',
                    'value' => 'Value',
                    'consistency' => 'Consistency',
                    'cultural_relevance' => 'Cultural Relevance'
                ),
                'features_label' => 'top_dishes',
                'unit' => 'person'
            ),
            'hotel' => array(
                'label' => 'Hotel',
                'plural' => 'hotels',
                'pillars' => array(
                    'room_quality' => 'Room Quality',
                    'service' => 'Service',
                    'location' => 'Location',
                    'amenities' => 'Amenities',
                    'value' => 'Value',
                    'cleanliness' => 'Cleanliness'
                ),
                'features_label' => 'top_amenities',
                'unit' => 'night'
            ),
            'bar' => array(
                'label' => 'Bar',
                'plural' => 'bars',
                'pillars' => array(
                    'drink_quality' => 'Drink Quality',
                    'atmosphere' => 'Atmosphere',
                    'service' => 'Service',
                    'music_vibe' => 'Music & Vibe',
                    'value' => 'Value',
                    'crowd' => 'Crowd'
                ),
                'features_label' => 'signature_drinks',
                'unit' => 'drink'
            ),
            'spa' => array(
                'label' => 'Spa',
                'plural' => 'spas',
                'pillars' => array(
                    'treatment_quality' => 'Treatment Quality',
                    'ambiance' => 'Ambiance',
                    'staff_expertise' => 'Staff Expertise',
                    'cleanliness' => 'Cleanliness',
                    'value' => 'Value',
                    'amenities' => 'Amenities'
                ),
                'features_label' => 'signature_treatments',
                'unit' => 'treatment'
            ),
            'gym' => array(
                'label' => 'Gym',
                'plural' => 'gyms',
                'pillars' => array(
                    'equipment_quality' => 'Equipment Quality',
                    'cleanliness' => 'Cleanliness',
                    'staff_coaching' => 'Staff & Coaching',
                    'class_variety' => 'Class Variety',
                    'value' => 'Value',
                    'community' => 'Community'
                ),
                'features_label' => 'best_features',
                'unit' => 'month'
            ),
            'salon' => array(
                'label' => 'Salon',
                'plural' => 'salons',
                'pillars' => array(
                    'technical_skill' => 'Technical Skill',
                    'customer_service' => 'Customer Service',
                    'cleanliness' => 'Cleanliness',
                    'ambiance' => 'Ambiance',
                    'value' => 'Value',
                    'product_quality' => 'Product Quality'
                ),
                'features_label' => 'signature_services',
                'unit' => 'service'
            ),
            'coffee_shop' => array(
                'label' => 'Coffee Shop',
                'plural' => 'coffee shops',
                'pillars' => array(
                    'coffee_quality' => 'Coffee Quality',
                    'atmosphere' => 'Atmosphere',
                    'service' => 'Service',
                    'food_options' => 'Food Options',
                    'value' => 'Value',
                    'wifi_workspace' => 'WiFi & Workspace'
                ),
                'features_label' => 'signature_drinks',
                'unit' => 'drink'
            ),
            'shopping' => array(
                'label' => 'Shopping',
                'plural' => 'shops',
                'pillars' => array(
                    'product_quality' => 'Product Quality',
                    'selection' => 'Selection',
                    'customer_service' => 'Customer Service',
                    'store_experience' => 'Store Experience',
                    'value' => 'Value',
                    'uniqueness' => 'Uniqueness'
                ),
                'features_label' => 'best_finds',
                'unit' => 'item'
            ),
            'entertainment' => array(
                'label' => 'Entertainment',
                'plural' => 'entertainment venues',
                'pillars' => array(
                    'experience_quality' => 'Experience Quality',
                    'atmosphere' => 'Atmosphere',
                    'staff_service' => 'Staff & Service',
                    'facilities' => 'Facilities',
                    'value' => 'Value',
                    'uniqueness' => 'Uniqueness'
                ),
                'features_label' => 'highlights',
                'unit' => 'experience'
            ),
            'services' => array(
                'label' => 'Services',
                'plural' => 'service providers',
                'pillars' => array(
                    'expertise' => 'Expertise',
                    'reliability' => 'Reliability',
                    'customer_service' => 'Customer Service',
                    'response_time' => 'Response Time',
                    'value' => 'Value',
                    'professionalism' => 'Professionalism'
                ),
                'features_label' => 'key_services',
                'unit' => 'service'
            ),
            'custom' => array(
                'label' => 'Custom',
                'plural' => 'businesses',
                'pillars' => array(
                    'quality' => 'Quality',
                    'service' => 'Service',
                    'experience' => 'Experience',
                    'value' => 'Value',
                    'reliability' => 'Reliability',
                    'uniqueness' => 'Uniqueness'
                ),
                'features_label' => 'highlights',
                'unit' => 'visit'
            )
        );
    }
    
    /**
     * Create default prompt templates
     *
     * @return void
     */
    private static function create_default_templates() {
        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database.php';
        
        // Create master template
        $master_template = 'You are the Master Critic AI for ZipPicks — a $100M local discovery platform that curates the best {business_category_plural} in every U.S. city. Your job is to evaluate {business_category_plural} using ZipPicks\' proprietary six-pillar scoring framework. Our content is trusted by top food editors, local influencers, and restaurant investors.

Only include {business_category_plural} that genuinely deserve recognition. You may list up to 10 businesses, but fewer is acceptable — only include the best. Quality > quantity.

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

Scoring Guidelines:
• All scores must be on a 0–10 scale, not 1–5. If source reviews are modeled on a 5-point system, multiply by 2 and round to 1 decimal place.
• Avoid 10.0 unless the {business_category} is an undeniable outlier.
• Base scores on simulated consensus from Yelp, Google, and TripAdvisor reviews, with heavier weight given to recent, high-quality reviews (last 12 months).
• Flag any {business_category_plural} with mixed sentiment, low confidence, or inconsistent quality.

Editorial Voice:
• Write like a seasoned critic for top-tier publications (The Infatuation, Eater, Condé Nast Traveler).
• Avoid sounding robotic or like a listicle.
• Do not repeat phrases across {business_category_plural}. Each entry should feel distinct and editorially considered.
• Do not include chain {business_category_plural} or sponsored entries. Maintain integrity above all.

Context:

You are now generating the list for:
Top {topic} in {location}
(For reference: think of this like "Top Sushi in San Francisco" or "Best Tacos in Austin.")

Now return only the valid JSON array. No preamble. No closing statements. No formatting errors.';
        
        ZipPicks_Master_Critic_Database::save_prompt_template(array(
            'name' => 'Master Critic Universal Template',
            'business_category' => 'all',
            'prompt_template' => $master_template,
            'is_default' => 1
        ));
    }
    
    /**
     * Register with Foundation database installer if available
     *
     * @return void
     */
    private static function register_with_foundation() {
        // Check if Foundation exists and has database installer
        if (!function_exists('zippicks')) {
            return;
        }
        
        $foundation = zippicks();
        if (!$foundation->has('database.installer')) {
            return;
        }
        
        $installer = $foundation->get('database.installer');
        
        // Register our schema with Foundation
        $installer->register_schema('master-critic', function() {
            require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-database.php';
            
            // Return associative array of table_name => sql
            return [
                'generations' => ZipPicks_Master_Critic_Database::get_generations_table_sql(),
                'templates' => ZipPicks_Master_Critic_Database::get_templates_table_sql(),
                'analytics' => ZipPicks_Master_Critic_Database::get_analytics_table_sql(),
                'query_metrics' => ZipPicks_Master_Critic_Database::get_query_metrics_table_sql(),
                'api_usage' => ZipPicks_Master_Critic_Database::get_api_usage_table_sql(),
                'api_cost' => ZipPicks_Master_Critic_Database::get_api_cost_table_sql(),
                'query_patterns' => ZipPicks_Master_Critic_Database::get_query_patterns_table_sql(),
                'scrape_log' => ZipPicks_Master_Critic_Database::get_scrape_log_table_sql()
            ];
        }, ZipPicks_Master_Critic_Database_Migrator::CURRENT_SCHEMA_VERSION);
    }
}