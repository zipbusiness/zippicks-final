<?php
/**
 * Category Handler for Master Critic Top 10 Lists
 *
 * @package ZipPicks_Master_Critic
 * @since 1.0.0
 */

class ZipPicks_Master_Critic_Category_Handler {
    
    /**
     * Available Top 10 categories with metadata
     */
    const CATEGORIES = [
        'best_overall' => [
            'name' => 'Best Overall',
            'slug' => 'best-overall',
            'description' => 'The absolute best restaurants in the city',
            'prompt_focus' => 'overall excellence, considering food quality, service, atmosphere, and value',
            'icon' => 'dashicons-star-filled',
            'priority' => 1
        ],
        'best_value' => [
            'name' => 'Best Value',
            'slug' => 'best-value',
            'description' => 'Amazing food at reasonable prices',
            'prompt_focus' => 'exceptional value for money, great portions, quality ingredients at fair prices',
            'icon' => 'dashicons-tag',
            'priority' => 2
        ],
        'date_night' => [
            'name' => 'Date Night',
            'slug' => 'date-night',
            'description' => 'Perfect spots for romantic evenings',
            'prompt_focus' => 'romantic atmosphere, intimate settings, exceptional service, memorable experiences',
            'icon' => 'dashicons-heart',
            'priority' => 3
        ],
        'business_lunch' => [
            'name' => 'Business Lunch',
            'slug' => 'business-lunch',
            'description' => 'Ideal for professional meetings and power lunches',
            'prompt_focus' => 'professional atmosphere, quick service, quiet environment, convenient location',
            'icon' => 'dashicons-businessperson',
            'priority' => 4
        ],
        'family_friendly' => [
            'name' => 'Family Friendly',
            'slug' => 'family-friendly',
            'description' => 'Great for dining with kids',
            'prompt_focus' => 'kid-friendly menu, spacious seating, welcoming to families, good value for groups',
            'icon' => 'dashicons-groups',
            'priority' => 5
        ],
        'food_truck' => [
            'name' => 'Food Trucks',
            'slug' => 'food-trucks',
            'description' => 'Best mobile food vendors',
            'prompt_focus' => 'unique offerings, consistent quality, popular trucks, great street food',
            'icon' => 'dashicons-car',
            'priority' => 6
        ],
        'fine_dining' => [
            'name' => 'Fine Dining',
            'slug' => 'fine-dining',
            'description' => 'Upscale culinary experiences',
            'prompt_focus' => 'exceptional cuisine, premium ingredients, expert service, sophisticated atmosphere',
            'icon' => 'dashicons-awards',
            'priority' => 7
        ],
        'brunch' => [
            'name' => 'Brunch',
            'slug' => 'brunch',
            'description' => 'Best weekend brunch spots',
            'prompt_focus' => 'creative brunch menu, great cocktails, weekend atmosphere, unique dishes',
            'icon' => 'dashicons-coffee',
            'priority' => 8
        ]
    ];
    
    /**
     * Get all categories
     *
     * @return array
     */
    public static function get_all_categories() {
        return self::CATEGORIES;
    }
    
    /**
     * Get category by key
     *
     * @param string $key Category key
     * @return array|null
     */
    public static function get_category($key) {
        return isset(self::CATEGORIES[$key]) ? self::CATEGORIES[$key] : null;
    }
    
    /**
     * Get category names for dropdown
     *
     * @return array
     */
    public static function get_category_options() {
        $options = [];
        foreach (self::CATEGORIES as $key => $category) {
            $options[$key] = $category['name'];
        }
        return $options;
    }
    
    /**
     * Build category-specific prompt enhancement
     *
     * @param string $category_key
     * @param array $params
     * @return string
     */
    public static function build_category_prompt($category_key, $params) {
        $category = self::get_category($category_key);
        if (!$category) {
            return '';
        }
        
        $location = $params['location'] ?? '';
        $business_type = $params['business_category'] ?? 'restaurant';
        
        $prompt = "\n\n🎯 CATEGORY-SPECIFIC FOCUS: {$category['name']}\n";
        $prompt .= "This is a '{$category['name']}' list, so prioritize: {$category['prompt_focus']}\n";
        
        // Add category-specific criteria
        switch ($category_key) {
            case 'best_value':
                $prompt .= "- Focus on restaurants with excellent price-to-quality ratio\n";
                $prompt .= "- Highlight specific dishes that offer great value\n";
                $prompt .= "- Include price ranges and what makes each a good deal\n";
                break;
                
            case 'date_night':
                $prompt .= "- Emphasize romantic ambiance and intimate settings\n";
                $prompt .= "- Mention special features like candlelit tables, views, or live music\n";
                $prompt .= "- Note reservation requirements and dress codes\n";
                break;
                
            case 'business_lunch':
                $prompt .= "- Highlight quick service times (under 45 minutes)\n";
                $prompt .= "- Note private dining areas or quiet sections\n";
                $prompt .= "- Mention parking availability and proximity to business districts\n";
                break;
                
            case 'family_friendly':
                $prompt .= "- Emphasize kids menu options and high chairs availability\n";
                $prompt .= "- Note entertainment for children or play areas\n";
                $prompt .= "- Highlight spacious seating and accommodating staff\n";
                break;
                
            case 'food_truck':
                $prompt .= "- Include typical locations and operating hours\n";
                $prompt .= "- Highlight signature dishes and specialties\n";
                $prompt .= "- Note social media handles for location updates\n";
                break;
                
            case 'fine_dining':
                $prompt .= "- Focus on tasting menus and chef's specialties\n";
                $prompt .= "- Mention wine programs and sommelier services\n";
                $prompt .= "- Note dress codes and reservation policies\n";
                break;
                
            case 'brunch':
                $prompt .= "- Highlight weekend hours and wait times\n";
                $prompt .= "- Emphasize signature brunch cocktails and dishes\n";
                $prompt .= "- Note if reservations are accepted for brunch\n";
                break;
        }
        
        $prompt .= "\nRemember: This list is specifically for the '{$category['name']}' category in {$location}.\n";
        
        return $prompt;
    }
    
    /**
     * Get category-specific scoring adjustments
     *
     * @param string $category_key
     * @return array
     */
    public static function get_scoring_weights($category_key) {
        $default_weights = [
            'food_quality' => 1.0,
            'service' => 1.0,
            'atmosphere' => 1.0,
            'value' => 1.0,
            'consistency' => 1.0,
            'cultural_relevance' => 1.0
        ];
        
        // Adjust weights based on category
        switch ($category_key) {
            case 'best_value':
                return array_merge($default_weights, [
                    'value' => 2.0,
                    'food_quality' => 1.5,
                    'atmosphere' => 0.7
                ]);
                
            case 'date_night':
                return array_merge($default_weights, [
                    'atmosphere' => 2.0,
                    'service' => 1.5,
                    'value' => 0.8
                ]);
                
            case 'business_lunch':
                return array_merge($default_weights, [
                    'service' => 2.0,
                    'value' => 1.5,
                    'atmosphere' => 1.2
                ]);
                
            case 'family_friendly':
                return array_merge($default_weights, [
                    'value' => 1.5,
                    'service' => 1.5,
                    'atmosphere' => 1.3
                ]);
                
            case 'fine_dining':
                return array_merge($default_weights, [
                    'food_quality' => 2.0,
                    'service' => 1.8,
                    'atmosphere' => 1.5,
                    'value' => 0.5
                ]);
                
            default:
                return $default_weights;
        }
    }
    
    /**
     * Validate category key
     *
     * @param string $category_key
     * @return bool
     */
    public static function is_valid_category($category_key) {
        return isset(self::CATEGORIES[$category_key]);
    }
    
    /**
     * Get category slug from key
     *
     * @param string $category_key
     * @return string
     */
    public static function get_category_slug($category_key) {
        $category = self::get_category($category_key);
        return $category ? $category['slug'] : '';
    }
    
    /**
     * Get category key from slug
     *
     * @param string $slug
     * @return string|null
     */
    public static function get_category_by_slug($slug) {
        foreach (self::CATEGORIES as $key => $category) {
            if ($category['slug'] === $slug) {
                return $key;
            }
        }
        return null;
    }
    
    /**
     * Get categories sorted by priority
     *
     * @return array
     */
    public static function get_categories_by_priority() {
        $categories = self::CATEGORIES;
        uasort($categories, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });
        return $categories;
    }
}