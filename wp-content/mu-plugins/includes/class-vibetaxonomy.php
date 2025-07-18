<?php
/**
 * Vibe Taxonomy System
 * 
 * Implements the comprehensive vibe taxonomy with 100+ vibes
 * organized into 10 master categories for mood-based discovery.
 * 
 * @package ZipPicks\Foundation
 */

namespace ZipPicks\Foundation;

if (!defined('ABSPATH')) {
    exit;
}

class VibeTaxonomy {
    
    /**
     * Taxonomy name
     * 
     * @var string
     */
    const TAXONOMY = 'zippicks_vibe';
    
    /**
     * Master vibe categories with their vibes
     * 
     * @var array
     */
    private $master_categories = [
        'energy' => [
            'label' => 'Energy & Atmosphere',
            'icon' => 'dashicons-star-filled',
            'vibes' => [
                'lively' => ['label' => 'Lively', 'description' => 'High energy, buzzing atmosphere'],
                'chill' => ['label' => 'Chill', 'description' => 'Relaxed, laid-back environment'],
                'vibrant' => ['label' => 'Vibrant', 'description' => 'Colorful, dynamic, full of life'],
                'intimate' => ['label' => 'Intimate', 'description' => 'Cozy, personal, quiet atmosphere'],
                'buzzing' => ['label' => 'Buzzing', 'description' => 'Busy, popular, always active'],
                'serene' => ['label' => 'Serene', 'description' => 'Peaceful, calm, tranquil setting'],
                'festive' => ['label' => 'Festive', 'description' => 'Celebratory, party atmosphere'],
                'sophisticated' => ['label' => 'Sophisticated', 'description' => 'Refined, elegant ambiance'],
                'casual' => ['label' => 'Casual', 'description' => 'Easy-going, unpretentious vibe'],
                'electric' => ['label' => 'Electric', 'description' => 'High-energy, exciting atmosphere']
            ]
        ],
        'social' => [
            'label' => 'Social Scene',
            'icon' => 'dashicons-groups',
            'vibes' => [
                'date-night' => ['label' => 'Date Night', 'description' => 'Perfect for romantic evenings'],
                'group-friendly' => ['label' => 'Group Friendly', 'description' => 'Great for parties and gatherings'],
                'solo-dining' => ['label' => 'Solo Dining', 'description' => 'Comfortable for dining alone'],
                'business-meeting' => ['label' => 'Business Meeting', 'description' => 'Professional atmosphere'],
                'family-friendly' => ['label' => 'Family Friendly', 'description' => 'Welcoming to children'],
                'see-and-be-seen' => ['label' => 'See and Be Seen', 'description' => 'Trendy social hotspot'],
                'locals-spot' => ['label' => 'Locals Spot', 'description' => 'Neighborhood favorite'],
                'tourist-friendly' => ['label' => 'Tourist Friendly', 'description' => 'Welcoming to visitors'],
                'lgbtq-friendly' => ['label' => 'LGBTQ+ Friendly', 'description' => 'Inclusive and welcoming'],
                'singles-scene' => ['label' => 'Singles Scene', 'description' => 'Good for meeting people']
            ]
        ],
        'aesthetic' => [
            'label' => 'Design & Aesthetic',
            'icon' => 'dashicons-art',
            'vibes' => [
                'instagrammable' => ['label' => 'Instagrammable', 'description' => 'Photogenic, social media worthy'],
                'minimalist' => ['label' => 'Minimalist', 'description' => 'Clean, simple design'],
                'maximalist' => ['label' => 'Maximalist', 'description' => 'Bold, abundant decor'],
                'industrial' => ['label' => 'Industrial', 'description' => 'Raw, warehouse-style aesthetic'],
                'vintage' => ['label' => 'Vintage', 'description' => 'Retro, nostalgic design'],
                'modern' => ['label' => 'Modern', 'description' => 'Contemporary, current design'],
                'rustic' => ['label' => 'Rustic', 'description' => 'Natural, countryside charm'],
                'art-deco' => ['label' => 'Art Deco', 'description' => 'Glamorous 1920s style'],
                'bohemian' => ['label' => 'Bohemian', 'description' => 'Eclectic, free-spirited decor'],
                'neon-lights' => ['label' => 'Neon Lights', 'description' => 'Bright, colorful lighting']
            ]
        ],
        'dining_style' => [
            'label' => 'Dining Style',
            'icon' => 'dashicons-food',
            'vibes' => [
                'fine-dining' => ['label' => 'Fine Dining', 'description' => 'Upscale, formal service'],
                'comfort-food' => ['label' => 'Comfort Food', 'description' => 'Hearty, familiar dishes'],
                'farm-to-table' => ['label' => 'Farm to Table', 'description' => 'Local, seasonal ingredients'],
                'street-food' => ['label' => 'Street Food', 'description' => 'Casual, authentic flavors'],
                'tapas-style' => ['label' => 'Tapas Style', 'description' => 'Small plates for sharing'],
                'tasting-menu' => ['label' => 'Tasting Menu', 'description' => 'Multi-course experience'],
                'buffet' => ['label' => 'Buffet', 'description' => 'All-you-can-eat selection'],
                'counter-service' => ['label' => 'Counter Service', 'description' => 'Quick, casual ordering'],
                'communal-dining' => ['label' => 'Communal Dining', 'description' => 'Shared tables and experiences'],
                'chef-driven' => ['label' => 'Chef Driven', 'description' => 'Focused on chef creativity']
            ]
        ],
        'special_features' => [
            'label' => 'Special Features',
            'icon' => 'dashicons-star-empty',
            'vibes' => [
                'rooftop' => ['label' => 'Rooftop', 'description' => 'Elevated outdoor dining'],
                'waterfront' => ['label' => 'Waterfront', 'description' => 'Views of water'],
                'outdoor-seating' => ['label' => 'Outdoor Seating', 'description' => 'Al fresco dining option'],
                'live-music' => ['label' => 'Live Music', 'description' => 'Regular performances'],
                'open-kitchen' => ['label' => 'Open Kitchen', 'description' => 'Watch chefs at work'],
                'private-dining' => ['label' => 'Private Dining', 'description' => 'Secluded spaces available'],
                'byob' => ['label' => 'BYOB', 'description' => 'Bring your own bottle'],
                'late-night' => ['label' => 'Late Night', 'description' => 'Open after midnight'],
                '24-hour' => ['label' => '24 Hour', 'description' => 'Always open'],
                'speakeasy' => ['label' => 'Speakeasy', 'description' => 'Hidden, exclusive entrance']
            ]
        ],
        'dietary' => [
            'label' => 'Dietary & Health',
            'icon' => 'dashicons-carrot',
            'vibes' => [
                'vegan-friendly' => ['label' => 'Vegan Friendly', 'description' => 'Plant-based options'],
                'vegetarian' => ['label' => 'Vegetarian', 'description' => 'Meat-free choices'],
                'gluten-free' => ['label' => 'Gluten Free', 'description' => 'Celiac-safe options'],
                'keto-friendly' => ['label' => 'Keto Friendly', 'description' => 'Low-carb options'],
                'paleo' => ['label' => 'Paleo', 'description' => 'Caveman diet friendly'],
                'organic' => ['label' => 'Organic', 'description' => 'Certified organic ingredients'],
                'healthy' => ['label' => 'Healthy', 'description' => 'Nutritious, balanced options'],
                'juice-bar' => ['label' => 'Juice Bar', 'description' => 'Fresh juices and smoothies'],
                'raw-food' => ['label' => 'Raw Food', 'description' => 'Uncooked cuisine'],
                'allergen-friendly' => ['label' => 'Allergen Friendly', 'description' => 'Accommodates allergies']
            ]
        ],
        'beverage' => [
            'label' => 'Beverage Focus',
            'icon' => 'dashicons-coffee',
            'vibes' => [
                'cocktail-focused' => ['label' => 'Cocktail Focused', 'description' => 'Creative mixed drinks'],
                'wine-bar' => ['label' => 'Wine Bar', 'description' => 'Extensive wine selection'],
                'craft-beer' => ['label' => 'Craft Beer', 'description' => 'Artisanal beer selection'],
                'coffee-culture' => ['label' => 'Coffee Culture', 'description' => 'Serious about coffee'],
                'tea-house' => ['label' => 'Tea House', 'description' => 'Tea ceremony and selection'],
                'natural-wine' => ['label' => 'Natural Wine', 'description' => 'Organic, biodynamic wines'],
                'mocktails' => ['label' => 'Mocktails', 'description' => 'Creative non-alcoholic drinks'],
                'sake-focused' => ['label' => 'Sake Focused', 'description' => 'Japanese rice wine selection'],
                'tiki-bar' => ['label' => 'Tiki Bar', 'description' => 'Tropical cocktails'],
                'dive-bar' => ['label' => 'Dive Bar', 'description' => 'No-frills drinking spot']
            ]
        ],
        'service_style' => [
            'label' => 'Service Style',
            'icon' => 'dashicons-businessman',
            'vibes' => [
                'full-service' => ['label' => 'Full Service', 'description' => 'Traditional table service'],
                'self-service' => ['label' => 'Self Service', 'description' => 'Order and pickup yourself'],
                'tableside' => ['label' => 'Tableside', 'description' => 'Prepared at your table'],
                'omakase' => ['label' => 'Omakase', 'description' => "Chef's choice experience"],
                'interactive' => ['label' => 'Interactive', 'description' => 'Cook your own or customize'],
                'delivery-focused' => ['label' => 'Delivery Focused', 'description' => 'Optimized for takeout'],
                'grab-and-go' => ['label' => 'Grab and Go', 'description' => 'Quick pickup options'],
                'reservation-only' => ['label' => 'Reservation Only', 'description' => 'Booking required'],
                'walk-ins-welcome' => ['label' => 'Walk-ins Welcome', 'description' => 'No reservation needed'],
                'membership-required' => ['label' => 'Membership Required', 'description' => 'Exclusive access']
            ]
        ],
        'cultural' => [
            'label' => 'Cultural & Authentic',
            'icon' => 'dashicons-admin-site-alt3',
            'vibes' => [
                'authentic' => ['label' => 'Authentic', 'description' => 'True to cultural origins'],
                'fusion' => ['label' => 'Fusion', 'description' => 'Blends multiple cuisines'],
                'traditional' => ['label' => 'Traditional', 'description' => 'Classic preparation methods'],
                'innovative' => ['label' => 'Innovative', 'description' => 'Creative, modern twists'],
                'immigrant-owned' => ['label' => 'Immigrant Owned', 'description' => 'Supporting immigrant businesses'],
                'women-owned' => ['label' => 'Women Owned', 'description' => 'Female-led business'],
                'black-owned' => ['label' => 'Black Owned', 'description' => 'Black-owned business'],
                'minority-owned' => ['label' => 'Minority Owned', 'description' => 'Minority-owned business'],
                'legacy-business' => ['label' => 'Legacy Business', 'description' => 'Long-standing institution'],
                'celebrity-chef' => ['label' => 'Celebrity Chef', 'description' => 'Famous chef involvement']
            ]
        ],
        'occasion' => [
            'label' => 'Perfect For',
            'icon' => 'dashicons-calendar-alt',
            'vibes' => [
                'birthday-worthy' => ['label' => 'Birthday Worthy', 'description' => 'Great for celebrations'],
                'anniversary' => ['label' => 'Anniversary', 'description' => 'Romantic milestone dining'],
                'power-lunch' => ['label' => 'Power Lunch', 'description' => 'Business lunch spot'],
                'brunch-scene' => ['label' => 'Brunch Scene', 'description' => 'Weekend brunch destination'],
                'happy-hour' => ['label' => 'Happy Hour', 'description' => 'After-work drinks and bites'],
                'pre-theater' => ['label' => 'Pre-Theater', 'description' => 'Quick dining before shows'],
                'sports-watching' => ['label' => 'Sports Watching', 'description' => 'Game day atmosphere'],
                'study-spot' => ['label' => 'Study Spot', 'description' => 'Good for working/studying'],
                'instagram-brunch' => ['label' => 'Instagram Brunch', 'description' => 'Photogenic brunch spot'],
                'proposal-worthy' => ['label' => 'Proposal Worthy', 'description' => 'Romantic enough for proposals']
            ]
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register taxonomy early
        add_action('init', [$this, 'register'], 0);
        
        // Admin customizations
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_filter('manage_edit-' . self::TAXONOMY . '_columns', [$this, 'add_admin_columns']);
        add_filter('manage_' . self::TAXONOMY . '_custom_column', [$this, 'render_admin_columns'], 10, 3);
    }
    
    /**
     * Register the vibe taxonomy
     */
    public function register() {
        $labels = [
            'name' => _x('Vibes', 'taxonomy general name', 'zippicks-foundation'),
            'singular_name' => _x('Vibe', 'taxonomy singular name', 'zippicks-foundation'),
            'search_items' => __('Search Vibes', 'zippicks-foundation'),
            'all_items' => __('All Vibes', 'zippicks-foundation'),
            'parent_item' => __('Parent Category', 'zippicks-foundation'),
            'parent_item_colon' => __('Parent Category:', 'zippicks-foundation'),
            'edit_item' => __('Edit Vibe', 'zippicks-foundation'),
            'update_item' => __('Update Vibe', 'zippicks-foundation'),
            'add_new_item' => __('Add New Vibe', 'zippicks-foundation'),
            'new_item_name' => __('New Vibe Name', 'zippicks-foundation'),
            'menu_name' => __('Vibes', 'zippicks-foundation'),
        ];
        
        $args = [
            'labels' => $labels,
            'public' => true,
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'rest_base' => 'vibes',
            'rewrite' => [
                'slug' => 'vibe',
                'with_front' => false,
                'hierarchical' => true
            ],
            'capabilities' => [
                'manage_terms' => 'manage_options',
                'edit_terms' => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'edit_posts'
            ]
        ];
        
        register_taxonomy(self::TAXONOMY, [], $args);
        
        // Populate initial vibes if not exists
        if (!get_option('zippicks_vibes_populated')) {
            $this->populate_initial_vibes();
        }
    }
    
    /**
     * Populate initial vibes
     */
    private function populate_initial_vibes() {
        foreach ($this->master_categories as $category_slug => $category_data) {
            // Create master category
            $parent_term = wp_insert_term(
                $category_data['label'],
                self::TAXONOMY,
                [
                    'slug' => $category_slug,
                    'description' => sprintf('Master category for %s vibes', $category_data['label'])
                ]
            );
            
            if (!is_wp_error($parent_term)) {
                $parent_id = $parent_term['term_id'];
                
                // Add category metadata
                update_term_meta($parent_id, '_zippicks_vibe_type', 'category');
                update_term_meta($parent_id, '_zippicks_vibe_icon', $category_data['icon']);
                
                // Create child vibes
                foreach ($category_data['vibes'] as $vibe_slug => $vibe_data) {
                    $child_term = wp_insert_term(
                        $vibe_data['label'],
                        self::TAXONOMY,
                        [
                            'slug' => $vibe_slug,
                            'description' => $vibe_data['description'],
                            'parent' => $parent_id
                        ]
                    );
                    
                    if (!is_wp_error($child_term)) {
                        update_term_meta($child_term['term_id'], '_zippicks_vibe_type', 'vibe');
                        update_term_meta($child_term['term_id'], '_zippicks_parent_category', $category_slug);
                    }
                }
            }
        }
        
        update_option('zippicks_vibes_populated', true);
    }
    
    /**
     * Get all vibes organized by category
     * 
     * @return array Vibes by category
     */
    public function get_vibes_by_category() {
        $vibes_by_category = [];
        
        foreach ($this->master_categories as $category_slug => $category_data) {
            $category_term = get_term_by('slug', $category_slug, self::TAXONOMY);
            
            if ($category_term) {
                $vibes_by_category[$category_slug] = [
                    'label' => $category_data['label'],
                    'icon' => $category_data['icon'],
                    'term_id' => $category_term->term_id,
                    'vibes' => []
                ];
                
                // Get child vibes
                $child_terms = get_terms([
                    'taxonomy' => self::TAXONOMY,
                    'parent' => $category_term->term_id,
                    'hide_empty' => false
                ]);
                
                foreach ($child_terms as $child_term) {
                    $vibes_by_category[$category_slug]['vibes'][$child_term->slug] = [
                        'label' => $child_term->name,
                        'description' => $child_term->description,
                        'term_id' => $child_term->term_id,
                        'count' => $child_term->count
                    ];
                }
            }
        }
        
        return $vibes_by_category;
    }
    
    /**
     * Get vibes for a business
     * 
     * @param int $business_id Business post ID
     * @return array Vibes data
     */
    public function get_business_vibes($business_id) {
        $terms = wp_get_object_terms($business_id, self::TAXONOMY, [
            'orderby' => 'parent',
            'order' => 'ASC'
        ]);
        
        $vibes = [];
        foreach ($terms as $term) {
            if ($term->parent > 0) { // Only include actual vibes, not categories
                $parent_term = get_term($term->parent, self::TAXONOMY);
                $vibes[] = [
                    'id' => $term->term_id,
                    'slug' => $term->slug,
                    'label' => $term->name,
                    'category' => $parent_term->slug,
                    'category_label' => $parent_term->name
                ];
            }
        }
        
        return $vibes;
    }
    
    /**
     * Search vibes by keyword
     * 
     * @param string $keyword Search keyword
     * @param array $args Additional arguments
     * @return array Matching vibes
     */
    public function search_vibes($keyword, $args = []) {
        $defaults = [
            'number' => 10,
            'hide_empty' => false,
            'exclude_categories' => true
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'search' => $keyword,
            'number' => $args['number'],
            'hide_empty' => $args['hide_empty']
        ]);
        
        $vibes = [];
        foreach ($terms as $term) {
            // Skip category terms if requested
            if ($args['exclude_categories'] && $term->parent == 0) {
                continue;
            }
            
            $vibes[] = [
                'id' => $term->term_id,
                'slug' => $term->slug,
                'label' => $term->name,
                'description' => $term->description,
                'count' => $term->count
            ];
        }
        
        return $vibes;
    }
    
    /**
     * Get related vibes
     * 
     * @param string $vibe_slug Vibe slug
     * @param int $limit Number of related vibes
     * @return array Related vibes
     */
    public function get_related_vibes($vibe_slug, $limit = 5) {
        $term = get_term_by('slug', $vibe_slug, self::TAXONOMY);
        
        if (!$term || $term->parent == 0) {
            return [];
        }
        
        // Get sibling vibes from same category
        $siblings = get_terms([
            'taxonomy' => self::TAXONOMY,
            'parent' => $term->parent,
            'exclude' => [$term->term_id],
            'number' => $limit,
            'orderby' => 'count',
            'order' => 'DESC',
            'hide_empty' => false
        ]);
        
        $related = [];
        foreach ($siblings as $sibling) {
            $related[] = [
                'id' => $sibling->term_id,
                'slug' => $sibling->slug,
                'label' => $sibling->name,
                'count' => $sibling->count
            ];
        }
        
        return $related;
    }
    
    /**
     * Get trending vibes
     * 
     * @param array $args Query arguments
     * @return array Trending vibes
     */
    public function get_trending_vibes($args = []) {
        $defaults = [
            'number' => 10,
            'days' => 7,
            'zip' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // This would analyze recent search and interaction data
        // For now, return popular vibes
        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'number' => $args['number'],
            'orderby' => 'count',
            'order' => 'DESC',
            'hide_empty' => true,
            'parent__not_in' => [0] // Exclude categories
        ]);
        
        $trending = [];
        foreach ($terms as $term) {
            $parent = get_term($term->parent, self::TAXONOMY);
            $trending[] = [
                'id' => $term->term_id,
                'slug' => $term->slug,
                'label' => $term->name,
                'category' => $parent->slug,
                'count' => $term->count,
                'trend_score' => $this->calculate_trend_score($term->term_id, $args['days'])
            ];
        }
        
        // Sort by trend score
        usort($trending, function($a, $b) {
            return $b['trend_score'] - $a['trend_score'];
        });
        
        return array_slice($trending, 0, $args['number']);
    }
    
    /**
     * Calculate trend score
     * 
     * @param int $term_id Term ID
     * @param int $days Number of days
     * @return float Trend score
     */
    private function calculate_trend_score($term_id, $days) {
        // This would calculate based on recent activity
        // For now, return a simple score based on count
        $term = get_term($term_id, self::TAXONOMY);
        return $term->count * 1.5; // Placeholder calculation
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'zippicks-dashboard',
            __('Vibe Management', 'zippicks-foundation'),
            __('Vibes', 'zippicks-foundation'),
            'manage_options',
            'zippicks-vibes',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $vibes_by_category = $this->get_vibes_by_category();
        ?>
        <div class="wrap">
            <h1><?php _e('Vibe Management', 'zippicks-foundation'); ?></h1>
            
            <div class="zippicks-vibes-grid">
                <?php foreach ($vibes_by_category as $category_slug => $category_data): ?>
                    <div class="vibe-category">
                        <h2>
                            <span class="<?php echo esc_attr($category_data['icon']); ?>"></span>
                            <?php echo esc_html($category_data['label']); ?>
                        </h2>
                        
                        <div class="vibes-list">
                            <?php foreach ($category_data['vibes'] as $vibe_slug => $vibe_data): ?>
                                <div class="vibe-item">
                                    <strong><?php echo esc_html($vibe_data['label']); ?></strong>
                                    <span class="count">(<?php echo number_format($vibe_data['count']); ?>)</span>
                                    <p class="description"><?php echo esc_html($vibe_data['description']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <style>
                .zippicks-vibes-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                .vibe-category {
                    background: #fff;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                }
                .vibe-category h2 {
                    margin-top: 0;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #f0f0f0;
                }
                .vibes-list {
                    margin-top: 15px;
                }
                .vibe-item {
                    padding: 10px 0;
                    border-bottom: 1px solid #f0f0f0;
                }
                .vibe-item:last-child {
                    border-bottom: none;
                }
                .vibe-item .count {
                    color: #666;
                    font-size: 0.9em;
                }
                .vibe-item .description {
                    margin: 5px 0 0;
                    color: #666;
                    font-size: 0.9em;
                }
            </style>
        </div>
        <?php
    }
    
    /**
     * Add admin columns
     * 
     * @param array $columns Current columns
     * @return array Modified columns
     */
    public function add_admin_columns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $label) {
            if ($key == 'posts') {
                $new_columns['category'] = __('Category', 'zippicks-foundation');
                $new_columns['trending'] = __('Trending', 'zippicks-foundation');
            }
            $new_columns[$key] = $label;
        }
        
        return $new_columns;
    }
    
    /**
     * Render admin columns
     * 
     * @param string $content Column content
     * @param string $column_name Column name
     * @param int $term_id Term ID
     * @return string Column content
     */
    public function render_admin_columns($content, $column_name, $term_id) {
        switch ($column_name) {
            case 'category':
                $term = get_term($term_id, self::TAXONOMY);
                if ($term->parent > 0) {
                    $parent = get_term($term->parent, self::TAXONOMY);
                    $content = $parent->name;
                } else {
                    $content = '<strong>' . __('Master Category', 'zippicks-foundation') . '</strong>';
                }
                break;
                
            case 'trending':
                $trend_score = $this->calculate_trend_score($term_id, 7);
                if ($trend_score > 100) {
                    $content = '<span style="color: #d63638;">🔥 ' . __('Hot', 'zippicks-foundation') . '</span>';
                } elseif ($trend_score > 50) {
                    $content = '<span style="color: #dba617;">📈 ' . __('Rising', 'zippicks-foundation') . '</span>';
                } else {
                    $content = '<span style="color: #666;">—</span>';
                }
                break;
        }
        
        return $content;
    }
    
    /**
     * Get vibe schema data
     * 
     * @param array $vibes Vibe data
     * @return array Schema.org DefinedTerm data
     */
    public function get_vibe_schema($vibes) {
        $schema_terms = [];
        
        foreach ($vibes as $vibe) {
            $schema_terms[] = [
                '@type' => 'DefinedTerm',
                'name' => $vibe['label'],
                'identifier' => $vibe['slug'],
                'inDefinedTermSet' => 'https://zippicks.com/vibes/'
            ];
        }
        
        return $schema_terms;
    }
}