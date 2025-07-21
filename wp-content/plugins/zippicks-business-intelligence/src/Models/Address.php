<?php
/**
 * Address Model
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Models;

class Address {
    
    /**
     * Street address line 1
     *
     * @var string
     */
    public string $street1;
    
    /**
     * Street address line 2
     *
     * @var string
     */
    public string $street2;
    
    /**
     * City
     *
     * @var string
     */
    public string $city;
    
    /**
     * State/Province
     *
     * @var string
     */
    public string $state;
    
    /**
     * ZIP/Postal code
     *
     * @var string
     */
    public string $zip;
    
    /**
     * Country
     *
     * @var string
     */
    public string $country;
    
    /**
     * Latitude
     *
     * @var float|null
     */
    public ?float $latitude;
    
    /**
     * Longitude
     *
     * @var float|null
     */
    public ?float $longitude;
    
    /**
     * Constructor
     *
     * @param array $data Address data
     */
    public function __construct(array $data = []) {
        $this->street1 = $data['street1'] ?? $data['street'] ?? '';
        $this->street2 = $data['street2'] ?? '';
        $this->city = $data['city'] ?? '';
        $this->state = $data['state'] ?? '';
        $this->zip = $data['zip'] ?? $data['postal_code'] ?? '';
        $this->country = $data['country'] ?? 'US';
        $this->latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $this->longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
    }
    
    /**
     * Convert to array
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'street1' => $this->street1,
            'street2' => $this->street2,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude
        ];
    }
    
    /**
     * Get formatted address
     *
     * @param bool $include_country Whether to include country
     * @return string
     */
    public function get_formatted(bool $include_country = false): string {
        $parts = [];
        
        if ($this->street1) {
            $parts[] = $this->street1;
        }
        
        if ($this->street2) {
            $parts[] = $this->street2;
        }
        
        $city_state_zip = [];
        if ($this->city) {
            $city_state_zip[] = $this->city;
        }
        
        if ($this->state) {
            $city_state_zip[] = $this->state;
        }
        
        if ($this->zip) {
            $city_state_zip[] = $this->zip;
        }
        
        if (!empty($city_state_zip)) {
            $parts[] = implode(', ', $city_state_zip);
        }
        
        if ($include_country && $this->country && $this->country !== 'US') {
            $parts[] = $this->country;
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Get short address (street and city only)
     *
     * @return string
     */
    public function get_short(): string {
        $parts = [];
        
        if ($this->street1) {
            $parts[] = $this->street1;
        }
        
        if ($this->city) {
            $parts[] = $this->city;
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Check if address is valid
     *
     * @return bool
     */
    public function is_valid(): bool {
        return !empty($this->street1) && !empty($this->city) && !empty($this->state);
    }
    
    /**
     * Check if coordinates are available
     *
     * @return bool
     */
    public function has_coordinates(): bool {
        return $this->latitude !== null && $this->longitude !== null;
    }
    
    /**
     * Get Google Maps URL
     *
     * @return string
     */
    public function get_google_maps_url(): string {
        if ($this->has_coordinates()) {
            return sprintf(
                'https://www.google.com/maps/search/?api=1&query=%f,%f',
                $this->latitude,
                $this->longitude
            );
        }
        
        $query = urlencode($this->get_formatted());
        return "https://www.google.com/maps/search/?api=1&query={$query}";
    }
    
    /**
     * Calculate distance to another address
     *
     * @param Address $other Other address
     * @return float|null Distance in miles, or null if coordinates missing
     */
    public function distance_to(Address $other): ?float {
        if (!$this->has_coordinates() || !$other->has_coordinates()) {
            return null;
        }
        
        $lat1 = deg2rad($this->latitude);
        $lon1 = deg2rad($this->longitude);
        $lat2 = deg2rad($other->latitude);
        $lon2 = deg2rad($other->longitude);
        
        $dlat = $lat2 - $lat1;
        $dlon = $lon2 - $lon1;
        
        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlon / 2) * sin($dlon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        // Earth's radius in miles
        $r = 3959;
        
        return $r * $c;
    }
}