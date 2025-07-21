<?php
/**
 * City-specific context for enhanced Master Critic recommendations
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_City_Context {
    
    /**
     * Get city-specific context for prompts
     *
     * @param string $city
     * @param string $category
     * @return string
     */
    public static function get_context($city, $category) {
        $city_lower = strtolower($city);
        $category_lower = strtolower($category);
        
        // City-specific dining contexts
        $contexts = array(
            'new york' => array(
                'restaurant' => "NEW YORK CONTEXT: Consider Manhattan's neighborhood distinctions (Michelin-starred fine dining in Midtown/TriBeCa, innovative Brooklyn spots, Queens ethnic enclaves). Factor in impossibly high standards - even 'good' here exceeds other cities' best. Recent openings from star chefs matter. Reservation difficulty indicates quality.",
                'pizza' => "NEW YORK PIZZA CONTEXT: Respect the NYC pizza hierarchy - classic NY slice joints, coal oven originals, artisanal Neapolitan, and Sicilian specialists. Consider DiFara's legacy, Prince Street's influence, and Brooklyn's innovation. Locals judge by crust, sauce balance, and cheese quality. Dollar slice joints don't belong on best-of lists.",
                'bagel' => "NEW YORK BAGEL CONTEXT: Hand-rolled, boiled, with perfect crust-to-chew ratio. Consider classic institutions (Russ & Daughters, H&H legacy) vs new-wave (Black Seed, Apollo). Wood-fired matters. Schmear quality essential. Real NYers have strong opinions - respect them."
            ),
            'austin' => array(
                'restaurant' => "AUSTIN CONTEXT: Balance BBQ institutions, innovative New Austin cuisine, and authentic Mexican. Consider East Austin's creative scene, South Congress staples, and Domain newcomers. Food truck culture is legitimate here. Heat tolerance assumed. Live music venues with great food get bonus points.",
                'bbq' => "AUSTIN BBQ CONTEXT: Brisket is king - look for perfect smoke rings, rendered fat, crispy bark. Consider wait times at Franklin, la Barbecue's consistency, newer spots challenging traditions. Sauce should be optional. Sides matter. BYOB culture appreciated.",
                'mexican' => "AUSTIN MEXICAN CONTEXT: Distinguish between Tex-Mex institutions, interior Mexican authenticity, breakfast taco specialists, and modern Mexican. Tortilla quality is paramount. Salsa variety expected. Consider truck gems alongside brick-and-mortars."
            ),
            'los angeles' => array(
                'restaurant' => "LOS ANGELES CONTEXT: Span from Beverly Hills power dining to K-Town authenticity, Silver Lake hipster havens to SGV Chinese excellence. Celebrity chef spots compete with strip mall gems. Dietary restrictions accommodated everywhere. Instagram-worthiness unfortunately matters.",
                'korean' => "LA KOREAN CONTEXT: Koreatown sets the standard outside Seoul. Consider KBBQ quality (meat grades, banchan variety), traditional spots, and Korean-fusion innovation. Late night culture important. Soju selection matters.",
                'taco' => "LA TACO CONTEXT: Street tacos reign supreme. Consider regional Mexican styles (Tijuana, Mexico City, Oaxacan). Truck legends vs brick-and-mortar. Handmade tortillas non-negotiable. Salsa verde vs roja camps. East LA authenticity vs Westside fusion."
            ),
            'chicago' => array(
                'restaurant' => "CHICAGO CONTEXT: From Alinea's molecular gastronomy to neighborhood Italian beef joints. Consider Michelin recognition, West Loop innovation, and ethnic neighborhoods (Chinatown, Little Village, Devon Ave). Deep dish is tourist food - locals know better.",
                'steakhouse' => "CHICAGO STEAKHOUSE CONTEXT: Legendary spots compete with modern interpretations. Dry-aging programs, local beef sourcing, classic sides perfected. Consider Gibson's scene, Bavette's innovation, RPM's energy. Power lunch spots vs celebration destinations."
            ),
            'san francisco' => array(
                'restaurant' => "SAN FRANCISCO CONTEXT: Tech wealth drives innovation but tradition persists. Consider Mission Mexican, Chinatown authenticity, Ferry Building artisans, and Michelin constellation. Ingredient obsession assumed. Wine programs crucial. Sustainability matters.",
                'seafood' => "SF SEAFOOD CONTEXT: Dungeness crab season drives menus. Consider waterfront tourist traps vs local gems. Sustainable sourcing mandatory. Raw bars, cioppino heritage, and Asian preparations. Swan Oyster Depot's legend looms large."
            )
        );
        
        // Get specific context or return generic
        if (isset($contexts[$city_lower][$category_lower])) {
            return $contexts[$city_lower][$category_lower];
        }
        
        // Check for city-level default
        if (isset($contexts[$city_lower]['restaurant'])) {
            return $contexts[$city_lower]['restaurant'];
        }
        
        // Generic context
        return "LOCAL CONTEXT: Consider this city's unique food culture, neighborhood distinctions, and what locals (not tourists) consider exceptional. Balance established institutions with exciting newcomers defining the current scene.";
    }
    
    /**
     * Get scoring adjustments for city/category combinations
     *
     * @param string $city
     * @param string $category
     * @return array
     */
    public static function get_scoring_adjustments($city, $category) {
        $adjustments = array(
            'new york' => array(
                'base_excellence_threshold' => 8.5, // Higher baseline
                'innovation_weight' => 1.2,
                'tradition_weight' => 0.9
            ),
            'austin' => array(
                'base_excellence_threshold' => 8.0,
                'authenticity_weight' => 1.3,
                'atmosphere_weight' => 1.1 // Live music, outdoor seating
            ),
            'los angeles' => array(
                'base_excellence_threshold' => 8.2,
                'diversity_weight' => 1.4,
                'trend_weight' => 1.2
            )
        );
        
        return $adjustments[strtolower($city)] ?? array(
            'base_excellence_threshold' => 8.0,
            'standard_weights' => true
        );
    }
    
    /**
     * Get neighborhood validation for cities
     *
     * @param string $city
     * @return array
     */
    public static function get_valid_neighborhoods($city) {
        $neighborhoods = array(
            'new york' => array(
                'Manhattan' => ['TriBeCa', 'SoHo', 'Greenwich Village', 'East Village', 'Lower East Side', 
                               'Chelsea', 'Midtown', 'Upper East Side', 'Upper West Side', 'Harlem', 
                               'Financial District', 'Chinatown', 'Little Italy', 'NoHo', 'Flatiron'],
                'Brooklyn' => ['Williamsburg', 'DUMBO', 'Brooklyn Heights', 'Park Slope', 'Prospect Heights',
                              'Fort Greene', 'Bushwick', 'Greenpoint', 'Red Hook', 'Sunset Park'],
                'Queens' => ['Astoria', 'Long Island City', 'Flushing', 'Jackson Heights', 'Elmhurst'],
                'Bronx' => ['Riverdale', 'Fordham', 'Belmont'],
                'Staten Island' => ['St. George', 'Stapleton']
            ),
            'los angeles' => array(
                'Central LA' => ['Downtown', 'Koreatown', 'Little Tokyo', 'Chinatown', 'Arts District'],
                'Westside' => ['Santa Monica', 'Venice', 'Brentwood', 'Westwood', 'Sawtelle'],
                'Hollywood' => ['Hollywood', 'West Hollywood', 'Los Feliz', 'Silver Lake', 'Echo Park'],
                'Valley' => ['Studio City', 'Sherman Oaks', 'Encino', 'North Hollywood'],
                'South LA' => ['Inglewood', 'Crenshaw', 'Leimert Park'],
                'East LA' => ['Boyle Heights', 'El Sereno']
            ),
            'austin' => array(
                'Central' => ['Downtown', 'Rainey Street', '6th Street', 'Red River'],
                'South' => ['South Congress', 'South Lamar', 'South First', 'Zilker'],
                'East' => ['East Austin', 'East Cesar Chavez', 'East 6th'],
                'North' => ['Hyde Park', 'North Loop', 'Crestview', 'Domain'],
                'West' => ['West Campus', 'Clarksville', 'Tarrytown']
            )
        );
        
        return $neighborhoods[strtolower($city)] ?? array();
    }
}