<?php
/**
 * JSON-LD Schema Generator for Master Critic Top 10 Lists
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Schema_Generator {
    
    /**
     * Generate ItemList schema for Top 10 lists
     *
     * @param int $list_id The list post ID
     * @return array|false Schema array or false on failure
     */
    public static function generate_top10_schema($list_id) {
        $list_post = get_post($list_id);
        if (!$list_post || $list_post->post_type !== 'master_critic_list') {
            return false;
        }
        
        // Get list category and metadata
        $list_category = get_post_meta($list_id, '_mc_list_category', true) ?: 'best_overall';
        $list_data = get_post_meta($list_id, '_mc_list_data', true);
        
        if (!$list_data || !is_array($list_data)) {
            return false;
        }
        
        // Get category handler for proper naming
        $category_info = self::get_category_info($list_category);
        
        // Build base schema
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $list_post->post_title,
            'description' => self::generate_list_description($list_post, $category_info),
            'numberOfItems' => count($list_data),
            'itemListOrder' => 'https://schema.org/ItemListOrderDescending',
            'author' => [
                '@type' => 'Organization',
                'name' => 'ZipPicks Master Critic',
                'url' => home_url()
            ],
            'datePublished' => get_the_date('c', $list_id),
            'dateModified' => get_the_modified_date('c', $list_id)
        ];
        
        // Add items
        $schema['itemListElement'] = [];
        $position = 1;
        
        foreach ($list_data as $business) {
            $item_schema = self::generate_list_item_schema($business, $position, $list_category);
            if ($item_schema) {
                $schema['itemListElement'][] = $item_schema;
                $position++;
            }
        }
        
        // Add aggregate rating if available
        $aggregate_rating = self::calculate_aggregate_rating($list_data);
        if ($aggregate_rating) {
            $schema['aggregateRating'] = $aggregate_rating;
        }
        
        return $schema;
    }
    
    /**
     * Generate schema for individual list item
     *
     * @param array $business Business data
     * @param int $position Position in list
     * @param string $list_category List category type
     * @return array Schema for list item
     */
    private static function generate_list_item_schema($business, $position, $list_category) {
        if (!isset($business['name']) || !isset($business['overall_score'])) {
            return null;
        }
        
        $item = [
            '@type' => 'ListItem',
            'position' => $position,
            'item' => [
                '@type' => 'Restaurant',
                'name' => $business['name'],
                'address' => self::format_address_schema($business),
                'aggregateRating' => [
                    '@type' => 'AggregateRating',
                    'ratingValue' => floatval($business['overall_score']),
                    'bestRating' => 10,
                    'worstRating' => 0,
                    'ratingCount' => 1,
                    'reviewCount' => 1,
                    'author' => [
                        '@type' => 'Organization',
                        'name' => 'ZipPicks Master Critic'
                    ]
                ]
            ]
        ];
        
        // Add category-specific properties
        if ($list_category === 'best_value' && isset($business['price_range'])) {
            $item['item']['priceRange'] = $business['price_range'];
        }
        
        // Add cuisine type if available
        if (isset($business['cuisine_type'])) {
            $item['item']['servesCuisine'] = $business['cuisine_type'];
        }
        
        // Add telephone if available
        if (isset($business['phone'])) {
            $item['item']['telephone'] = $business['phone'];
        }
        
        // Add must-try dish if available
        if (isset($business['must_try_dish'])) {
            $item['item']['makesOffer'] = [
                '@type' => 'Offer',
                'itemOffered' => [
                    '@type' => 'MenuItem',
                    'name' => $business['must_try_dish']
                ]
            ];
        }
        
        // Add detailed review with pillar scores
        if (isset($business['pillar_scores']) && is_array($business['pillar_scores'])) {
            $item['item']['review'] = self::generate_detailed_review($business, $list_category);
        }
        
        return $item;
    }
    
    /**
     * Generate detailed review schema with pillar scores
     *
     * @param array $business Business data
     * @param string $list_category List category
     * @return array Review schema
     */
    private static function generate_detailed_review($business, $list_category) {
        $review = [
            '@type' => 'Review',
            'author' => [
                '@type' => 'Organization',
                'name' => 'ZipPicks Master Critic'
            ],
            'datePublished' => current_time('c'),
            'reviewRating' => [
                '@type' => 'Rating',
                'ratingValue' => floatval($business['overall_score']),
                'bestRating' => 10,
                'worstRating' => 0
            ]
        ];
        
        // Build review body from pillar scores
        $review_aspects = [];
        foreach ($business['pillar_scores'] as $pillar => $score) {
            $pillar_name = self::format_pillar_name($pillar);
            $review_aspects[] = [
                '@type' => 'PropertyValue',
                'name' => $pillar_name,
                'value' => floatval($score),
                'maxValue' => 10,
                'minValue' => 0
            ];
        }
        
        if (!empty($review_aspects)) {
            $review['reviewAspect'] = $review_aspects;
        }
        
        // Add summary if available
        if (isset($business['summary'])) {
            $review['reviewBody'] = $business['summary'];
        }
        
        return $review;
    }
    
    /**
     * Format address for schema
     *
     * @param array $business Business data
     * @return array|string Address schema
     */
    private static function format_address_schema($business) {
        if (isset($business['address']) && is_array($business['address'])) {
            return [
                '@type' => 'PostalAddress',
                'streetAddress' => $business['address']['street'] ?? '',
                'addressLocality' => $business['address']['city'] ?? '',
                'addressRegion' => $business['address']['state'] ?? '',
                'postalCode' => $business['address']['zip'] ?? ''
            ];
        } elseif (isset($business['address'])) {
            return [
                '@type' => 'PostalAddress',
                'name' => $business['address']
            ];
        } elseif (isset($business['neighborhood'])) {
            return [
                '@type' => 'PostalAddress',
                'addressLocality' => $business['neighborhood']
            ];
        }
        
        return '';
    }
    
    /**
     * Calculate aggregate rating for the list
     *
     * @param array $list_data List of businesses
     * @return array|null Aggregate rating schema
     */
    private static function calculate_aggregate_rating($list_data) {
        if (empty($list_data)) {
            return null;
        }
        
        $total_score = 0;
        $count = 0;
        
        foreach ($list_data as $business) {
            if (isset($business['overall_score'])) {
                $total_score += floatval($business['overall_score']);
                $count++;
            }
        }
        
        if ($count === 0) {
            return null;
        }
        
        return [
            '@type' => 'AggregateRating',
            'ratingValue' => round($total_score / $count, 1),
            'bestRating' => 10,
            'worstRating' => 0,
            'ratingCount' => $count
        ];
    }
    
    /**
     * Generate list description
     *
     * @param WP_Post $list_post List post object
     * @param array $category_info Category information
     * @return string Description
     */
    private static function generate_list_description($list_post, $category_info) {
        if (!empty($list_post->post_excerpt)) {
            return $list_post->post_excerpt;
        }
        
        $location = get_post_meta($list_post->ID, '_mc_location', true);
        $description = sprintf(
            'Master Critic AI-curated list of the %s in %s, featuring comprehensive scoring across multiple quality dimensions.',
            $category_info['label'] ?? 'Top 10 Restaurants',
            $location ?: 'this area'
        );
        
        return $description;
    }
    
    /**
     * Get category information
     *
     * @param string $category Category slug
     * @return array Category info
     */
    private static function get_category_info($category) {
        // Load category handler if available
        $category_handler_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-category-handler.php';
        if (file_exists($category_handler_file)) {
            require_once $category_handler_file;
            return ZipPicks_Master_Critic_Category_Handler::get_category($category);
        }
        
        // Fallback category info
        $categories = [
            'best_overall' => ['label' => 'Best Overall Restaurants'],
            'best_value' => ['label' => 'Best Value Restaurants'],
            'date_night' => ['label' => 'Best Date Night Restaurants'],
            'business_lunch' => ['label' => 'Best Business Lunch Spots'],
            'family_friendly' => ['label' => 'Best Family-Friendly Restaurants'],
            'food_trucks' => ['label' => 'Best Food Trucks'],
            'fine_dining' => ['label' => 'Best Fine Dining Restaurants'],
            'brunch' => ['label' => 'Best Brunch Spots']
        ];
        
        return $categories[$category] ?? ['label' => 'Top 10 Restaurants'];
    }
    
    /**
     * Format pillar name for schema
     *
     * @param string $pillar Pillar key
     * @return string Formatted name
     */
    private static function format_pillar_name($pillar) {
        $pillar_names = [
            'food_quality' => 'Food Quality',
            'service' => 'Service',
            'atmosphere' => 'Atmosphere & Design',
            'value' => 'Value for Money',
            'consistency' => 'Consistency',
            'cultural_relevance' => 'Cultural Relevance'
        ];
        
        return $pillar_names[$pillar] ?? ucwords(str_replace('_', ' ', $pillar));
    }
    
    /**
     * Output schema in page head
     *
     * @param int $list_id List post ID
     */
    public static function output_schema($list_id) {
        $schema = self::generate_top10_schema($list_id);
        
        if (!$schema) {
            return;
        }
        
        ?>
        <script type="application/ld+json">
        <?php echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
        </script>
        <?php
    }
    
    /**
     * Hook into wp_head for schema output
     */
    public static function init() {
        add_action('wp_head', function() {
            if (is_singular('master_critic_list')) {
                self::output_schema(get_the_ID());
            }
        });
    }
}