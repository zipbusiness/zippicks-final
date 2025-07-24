<?php
/**
 * Location Detection Trait
 * 
 * Provides location detection functionality for reuse across multiple classes
 * 
 * @package ZipPicks\SmartSearch
 * @since 2.0.0
 */

namespace ZipPicks\SmartSearch\Traits;

trait LocationDetection {
    
    /**
     * Get location data
     * 
     * @param float|null $lat Latitude
     * @param float|null $lng Longitude
     * @return array
     */
    private function get_location($lat = null, $lng = null) {
        // If coordinates provided, use them
        if ($lat !== null && $lng !== null) {
            return [
                'lat' => floatval($lat),
                'lng' => floatval($lng),
                'source' => 'user_provided'
            ];
        }
        
        // Try to get from Geo Service plugin
        if (class_exists('\\ZipPicks\\Geo\\Location_Detector')) {
            try {
                $detector = new \ZipPicks\Geo\Location_Detector();
                $location = $detector->get_user_location(get_current_user_id());
                
                if ($location && isset($location['latitude']) && isset($location['longitude'])) {
                    return [
                        'lat' => floatval($location['latitude']),
                        'lng' => floatval($location['longitude']),
                        'city' => $location['city'] ?? null,
                        'state' => $location['state'] ?? null,
                        'source' => $location['source'] ?? 'geo_service'
                    ];
                }
            } catch (\Exception $e) {
                error_log('Geo Service error: ' . $e->getMessage());
            }
        }
        
        // Fallback to default location
        $default_location = get_option('zippicks_search_default_location', [
            'lat' => 34.0522,
            'lng' => -118.2437,
            'city' => 'Los Angeles',
            'state' => 'CA'
        ]);
        
        return array_merge($default_location, ['source' => 'default']);
    }
}