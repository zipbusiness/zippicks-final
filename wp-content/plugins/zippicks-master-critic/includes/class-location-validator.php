<?php
/**
 * Location Validator for Master Critic
 * 
 * Ensures businesses are actually in the specified location
 */

class ZipPicks_Master_Critic_Location_Validator {
    
    /**
     * Known city boundaries and neighborhoods
     */
    private static $city_data = array(
        'los_angeles' => array(
            'aliases' => array('LA', 'Los Angeles', 'L.A.'),
            'exclude_cities' => array('Santa Monica', 'Beverly Hills', 'West Hollywood', 'Culver City', 'Burbank', 'Glendale'),
            'neighborhoods' => array(
                'Downtown', 'Hollywood', 'Silver Lake', 'Los Feliz', 'Echo Park',
                'Venice', 'Mar Vista', 'Brentwood', 'Westwood', 'Century City',
                'Koreatown', 'Little Tokyo', 'Arts District', 'Boyle Heights'
            )
        ),
        'new_york' => array(
            'aliases' => array('NYC', 'New York', 'NY', 'New York City'),
            'exclude_cities' => array('Jersey City', 'Hoboken', 'Newark', 'Yonkers'),
            'boroughs' => array('Manhattan', 'Brooklyn', 'Queens', 'Bronx', 'Staten Island')
        ),
        'chicago' => array(
            'aliases' => array('Chicago', 'CHI'),
            'exclude_cities' => array('Evanston', 'Oak Park', 'Cicero', 'Skokie'),
            'neighborhoods' => array(
                'Loop', 'River North', 'Gold Coast', 'Lincoln Park', 'Lakeview',
                'Wicker Park', 'Bucktown', 'Logan Square', 'West Loop'
            )
        )
    );
    
    /**
     * Validate if a business is in the specified location
     */
    public static function validate_business_location($business, $requested_location) {
        $location_lower = strtolower($requested_location);
        $business_name_lower = strtolower($business['name']);
        
        // Check for excluded cities in business name
        foreach (self::$city_data as $city => $data) {
            if (self::location_matches($requested_location, $city, $data['aliases'])) {
                // Check if business name contains excluded city
                foreach ($data['exclude_cities'] as $excluded) {
                    if (stripos($business_name_lower, strtolower($excluded)) !== false) {
                        return false; // Business is in excluded neighboring city
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Enhance prompt with location-specific instructions
     */
    public static function enhance_location_prompt($location) {
        $enhanced = '';
        
        foreach (self::$city_data as $city => $data) {
            if (self::location_matches($location, $city, $data['aliases'])) {
                $enhanced .= "\n\nIMPORTANT LOCATION BOUNDARIES:\n";
                $enhanced .= "You are looking for businesses in {$location} ONLY.\n";
                $enhanced .= "DO NOT include businesses from these neighboring cities:\n";
                foreach ($data['exclude_cities'] as $excluded) {
                    $enhanced .= "- {$excluded}\n";
                }
                
                if (isset($data['neighborhoods'])) {
                    $enhanced .= "\nValid {$location} neighborhoods include:\n";
                    foreach (array_slice($data['neighborhoods'], 0, 10) as $neighborhood) {
                        $enhanced .= "- {$neighborhood}\n";
                    }
                }
                
                break;
            }
        }
        
        return $enhanced;
    }
    
    /**
     * Check if location matches
     */
    private static function location_matches($input, $city, $aliases) {
        $input_lower = strtolower(trim($input));
        
        if (stripos($input_lower, $city) !== false) {
            return true;
        }
        
        foreach ($aliases as $alias) {
            if ($input_lower === strtolower($alias)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Filter businesses by location
     */
    public static function filter_businesses_by_location($businesses, $location) {
        return array_filter($businesses, function($business) use ($location) {
            return self::validate_business_location($business, $location);
        });
    }
}