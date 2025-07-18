<?php
/**
 * Simplified Business Model for Business Intelligence
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Models;

/**
 * Business model with only required fields for AI/Data layer
 */
class Business {
    
    /**
     * Required fields as specified
     */
    public string $zpid;
    public string $place_id;
    public string $source;
    public string $name;
    public string $address;
    public string $city;
    public string $state;
    public string $zip_code;
    public ?float $latitude;
    public ?float $longitude;
    public string $price_level;
    public string $cuisine_type;
    public array $vibe_attributes;
    public string $elite_category;
    public string $hours;
    public bool $is_closed;
    public ?float $rating_average;
    public int $rating_count;
    public string $review_summary_text;
    public array $categories;
    public string $website;
    public string $phone_number;
    public string $last_updated;
    
    /**
     * Constructor
     *
     * @param array $data Raw data
     */
    public function __construct(array $data = []) {
        $this->zpid = $data['zpid'] ?? '';
        $this->place_id = $data['place_id'] ?? '';
        $this->source = $data['source'] ?? 'zipbusiness';
        $this->name = $data['name'] ?? '';
        $this->address = $data['address'] ?? '';
        $this->city = $data['city'] ?? '';
        $this->state = $data['state'] ?? 'CA';
        $this->zip_code = $data['zip_code'] ?? '';
        $this->latitude = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $this->longitude = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $this->price_level = $data['price_level'] ?? '';
        $this->cuisine_type = $data['cuisine_type'] ?? '';
        $this->vibe_attributes = $data['vibe_attributes'] ?? [];
        $this->elite_category = $data['elite_category'] ?? '';
        $this->hours = $data['hours'] ?? '';
        $this->is_closed = isset($data['is_closed']) ? (bool) $data['is_closed'] : false;
        $this->rating_average = isset($data['rating_average']) ? (float) $data['rating_average'] : null;
        $this->rating_count = isset($data['rating_count']) ? (int) $data['rating_count'] : 0;
        $this->review_summary_text = $data['review_summary_text'] ?? '';
        $this->categories = $data['categories'] ?? [];
        $this->website = $data['website'] ?? '';
        $this->phone_number = $data['phone_number'] ?? '';
        $this->last_updated = $data['last_updated'] ?? date('Y-m-d H:i:s');
    }
    
    /**
     * Convert to array
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'zpid' => $this->zpid,
            'place_id' => $this->place_id,
            'source' => $this->source,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'price_level' => $this->price_level,
            'cuisine_type' => $this->cuisine_type,
            'vibe_attributes' => $this->vibe_attributes,
            'elite_category' => $this->elite_category,
            'hours' => $this->hours,
            'is_closed' => $this->is_closed,
            'rating_average' => $this->rating_average,
            'rating_count' => $this->rating_count,
            'review_summary_text' => $this->review_summary_text,
            'categories' => $this->categories,
            'website' => $this->website,
            'phone_number' => $this->phone_number,
            'last_updated' => $this->last_updated
        ];
    }
    
    /**
     * Create from API response
     *
     * @param array $data
     * @return self
     */
    public static function from_api(array $data): self {
        return new self($data);
    }
    
    /**
     * Check if business has required fields
     *
     * @return bool
     */
    public function is_valid(): bool {
        return !empty($this->zpid) && 
               !empty($this->name) && 
               !empty($this->city);
    }
    
    /**
     * Get formatted location string
     *
     * @return string
     */
    public function get_location_string(): string {
        $parts = array_filter([
            $this->city,
            $this->state,
            $this->zip_code
        ]);
        
        return implode(', ', $parts);
    }
    
    /**
     * Check if has coordinates
     *
     * @return bool
     */
    public function has_coordinates(): bool {
        return $this->latitude !== null && $this->longitude !== null;
    }
    
    /**
     * Get price display ($, $$, $$$, etc)
     *
     * @return string
     */
    public function get_price_display(): string {
        return str_repeat('$', strlen($this->price_level));
    }
}