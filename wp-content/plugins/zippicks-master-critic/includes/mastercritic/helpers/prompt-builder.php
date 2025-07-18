<?php
/**
 * Prompt Builder Helper
 *
 * Enterprise-grade prompt construction for AI-powered Top 10 list generation.
 * Ensures consistent, high-quality prompts with proper context and constraints.
 *
 * @package ZipPicks_Master_Critic
 * @subpackage MasterCritic\Helpers
 * @since 2.0.0
 */

class ZipPicks_Master_Critic_PromptBuilder {
    
    /**
     * Build prompt for Top 10 list generation
     *
     * @param string $city City name
     * @param string $vibe_name Vibe display name
     * @param array $restaurant_names Array of verified restaurant names
     * @return string Complete prompt for AI generation
     */
    public static function for_top10($city, $vibe_name, array $restaurant_names) {
        if (empty($city) || empty($vibe_name) || empty($restaurant_names)) {
            throw new InvalidArgumentException('City, vibe name, and restaurant names are required');
        }
        
        // Ensure we have at least 10 restaurants for a proper Top 10
        if (count($restaurant_names) < 10) {
            throw new InvalidArgumentException('At least 10 restaurants required for Top 10 generation');
        }
        
        // Load the enhanced master prompt template
        $template = self::load_prompt_template('enhanced-master-prompt.txt');
        
        // Build the restaurant list
        $restaurant_list = implode("\n", array_map(function($name) {
            return "- " . $name;
        }, $restaurant_names));
        
        // Replace placeholders in template
        $prompt = str_replace(
            ['{CITY}', '{VIBE}', '{RESTAURANT_LIST}'],
            [$city, $vibe_name, $restaurant_list],
            $template
        );
        
        // Add specific instructions for JSON output
        $prompt .= self::get_json_output_instructions();
        
        return $prompt;
    }
    
    /**
     * Build prompt for verified ranking
     *
     * @param string $city City name
     * @param string $vibe_name Vibe display name
     * @param array $restaurants Array of restaurant data with scores
     * @return string Complete prompt for ranking verification
     */
    public static function for_verified_ranking($city, $vibe_name, array $restaurants) {
        if (empty($restaurants)) {
            throw new InvalidArgumentException('Restaurant data required for ranking');
        }
        
        // Load the verified ranking prompt template
        $template = self::load_prompt_template('verified-ranking-prompt.txt');
        
        // Build restaurant data with scores
        $restaurant_data = array_map(function($restaurant) {
            return sprintf(
                "%s (Confidence: %.2f, Reviews: %d, Rating: %.1f)",
                $restaurant['name'],
                $restaurant['confidence_score'] ?? 0,
                $restaurant['review_count'] ?? 0,
                $restaurant['rating'] ?? 0
            );
        }, $restaurants);
        
        $restaurant_list = implode("\n", array_map(function($data) {
            return "- " . $data;
        }, $restaurant_data));
        
        // Replace placeholders
        $prompt = str_replace(
            ['{CITY}', '{VIBE}', '{RESTAURANT_DATA}'],
            [$city, $vibe_name, $restaurant_list],
            $template
        );
        
        return $prompt;
    }
    
    /**
     * Build prompt for editorial summary generation
     *
     * @param string $restaurant_name Restaurant name
     * @param string $vibe_name Vibe context
     * @param array $attributes Restaurant attributes
     * @return string Complete prompt for summary
     */
    public static function for_editorial_summary($restaurant_name, $vibe_name, array $attributes = []) {
        $prompt = "Generate a compelling 2-3 sentence editorial summary for {$restaurant_name} ";
        $prompt .= "in the context of '{$vibe_name}' dining. ";
        
        if (!empty($attributes)) {
            $prompt .= "Consider these attributes: " . implode(', ', $attributes) . ". ";
        }
        
        $prompt .= "Focus on what makes this restaurant exceptional for this particular vibe. ";
        $prompt .= "Write in an engaging, authoritative voice without clichés or generic descriptions.";
        
        return $prompt;
    }
    
    /**
     * Load prompt template from file
     *
     * @param string $filename Template filename
     * @return string Template content
     * @throws RuntimeException if template not found
     */
    private static function load_prompt_template($filename) {
        $template_path = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/prompts/' . $filename;
        
        if (!file_exists($template_path)) {
            // Fallback to default prompt if template missing
            return self::get_default_prompt_template($filename);
        }
        
        $content = file_get_contents($template_path);
        if ($content === false) {
            throw new RuntimeException("Failed to load prompt template: {$filename}");
        }
        
        return trim($content);
    }
    
    /**
     * Get default prompt template as fallback
     *
     * @param string $filename Template filename
     * @return string Default template content
     */
    private static function get_default_prompt_template($filename) {
        switch ($filename) {
            case 'enhanced-master-prompt.txt':
                return self::get_default_master_prompt();
                
            case 'verified-ranking-prompt.txt':
                return self::get_default_ranking_prompt();
                
            default:
                throw new RuntimeException("No default template available for: {$filename}");
        }
    }
    
    /**
     * Get default master prompt template
     *
     * @return string Default master prompt
     */
    private static function get_default_master_prompt() {
        return <<<PROMPT
You are the Master Critic for ZipPicks, an authoritative voice in restaurant curation. Generate a Top 10 list for {VIBE} restaurants in {CITY}.

Available restaurants (verified from our database):
{RESTAURANT_LIST}

Requirements:
1. Select EXACTLY 10 restaurants from the provided list
2. Rank them based on their excellence for the "{VIBE}" dining experience
3. Write a compelling 2-3 sentence summary for each restaurant
4. Focus on what makes each restaurant exceptional for this vibe
5. Use specific details and avoid generic descriptions

Consider factors like:
- Atmosphere and ambiance alignment with the vibe
- Quality and consistency of food
- Service excellence
- Value proposition
- Unique features that enhance the vibe experience

Write with authority and expertise, as if you've personally experienced each restaurant multiple times.
PROMPT;
    }
    
    /**
     * Get default ranking prompt template
     *
     * @return string Default ranking prompt
     */
    private static function get_default_ranking_prompt() {
        return <<<PROMPT
Review and verify the ranking for Top 10 {VIBE} restaurants in {CITY}.

Restaurant data with confidence scores:
{RESTAURANT_DATA}

Task:
1. Analyze the provided confidence scores and metrics
2. Adjust rankings if necessary based on vibe alignment
3. Ensure the final ranking represents the best {VIBE} experience
4. Provide brief reasoning for any ranking adjustments

Output the final verified ranking with confidence in each placement.
PROMPT;
    }
    
    /**
     * Get JSON output instructions
     *
     * @return string JSON format instructions
     */
    private static function get_json_output_instructions() {
        return <<<INSTRUCTIONS


Output your response as a JSON array with the following structure:
[
  {
    "rank": 1,
    "name": "Restaurant Name (exactly as provided)",
    "summary": "Your 2-3 sentence editorial summary",
    "reasoning": "Brief explanation of why this restaurant earned this rank"
  },
  ...
]

Ensure the JSON is valid and includes exactly 10 restaurants.
INSTRUCTIONS;
    }
    
    /**
     * Build system prompt for AI context
     *
     * @return string System prompt
     */
    public static function get_system_prompt() {
        return <<<PROMPT
You are the Master Critic for ZipPicks, a premier restaurant discovery platform. Your role is to provide authoritative, insightful, and engaging restaurant recommendations based on specific vibes and dining experiences.

Your expertise includes:
- Deep knowledge of culinary trends and traditions
- Understanding of atmosphere and ambiance
- Ability to match restaurants to specific dining moods/vibes
- Writing compelling, specific descriptions that avoid clichés

Always maintain a confident, knowledgeable tone while being helpful and specific. Your recommendations should feel personal and trustworthy, as if coming from a friend who is a food expert.
PROMPT;
    }
    
    /**
     * Validate and sanitize restaurant names
     *
     * @param array $names Raw restaurant names
     * @return array Sanitized names
     */
    public static function sanitize_restaurant_names(array $names) {
        return array_values(array_filter(array_map(function($name) {
            // Remove extra whitespace
            $name = trim($name);
            
            // Remove any HTML or special characters
            $name = wp_strip_all_tags($name);
            
            // Normalize quotes and apostrophes
            $name = str_replace(['???', '???', '???', '???'], ["'", "'", '"', '"'], $name);
            
            return $name;
        }, $names)));
    }
}