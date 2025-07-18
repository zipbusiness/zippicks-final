<?php
/**
 * Business Profile Model
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Models;

class BusinessProfile {
    
    /**
     * ZipPicks unique identifier
     *
     * @var string
     */
    public string $zpid;
    
    /**
     * Business name
     *
     * @var string
     */
    public string $name;
    
    /**
     * Address object
     *
     * @var Address
     */
    public Address $address;
    
    /**
     * Cuisine types
     *
     * @var array
     */
    public array $cuisine_types;
    
    /**
     * Price range
     *
     * @var PriceRange
     */
    public PriceRange $price_range;
    
    /**
     * Aggregated rating score
     *
     * @var float|null
     */
    public ?float $rating;
    
    /**
     * Total review count
     *
     * @var int
     */
    public int $review_count;
    
    /**
     * Business hours
     *
     * @var array
     */
    public array $business_hours;
    
    /**
     * Contact information
     *
     * @var ContactInfo
     */
    public ContactInfo $contact_info;
    
    /**
     * ZipPicks enrichment metadata
     *
     * @var array
     */
    public array $metadata;
    
    /**
     * Constructor
     *
     * @param array $data Raw data from API
     */
    public function __construct(array $data = []) {
        $this->zpid = $data['zpid'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->address = new Address($data['address'] ?? []);
        $this->cuisine_types = $data['cuisine_types'] ?? [];
        $this->price_range = new PriceRange($data['price_range'] ?? '');
        $this->rating = isset($data['rating']) ? (float) $data['rating'] : null;
        $this->review_count = (int) ($data['review_count'] ?? 0);
        $this->business_hours = $data['business_hours'] ?? [];
        $this->contact_info = new ContactInfo($data['contact_info'] ?? []);
        $this->metadata = $data['metadata'] ?? [];
    }
    
    /**
     * Create from API response
     *
     * @param array $data API response data
     * @return self
     */
    public static function from_api_response(array $data): self {
        return new self($data);
    }
    
    /**
     * Convert to array
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'zpid' => $this->zpid,
            'name' => $this->name,
            'address' => $this->address->to_array(),
            'cuisine_types' => $this->cuisine_types,
            'price_range' => $this->price_range->to_array(),
            'rating' => $this->rating,
            'review_count' => $this->review_count,
            'business_hours' => $this->business_hours,
            'contact_info' => $this->contact_info->to_array(),
            'metadata' => $this->metadata
        ];
    }
    
    /**
     * Convert to WordPress post meta format
     *
     * @return array
     */
    public function to_post_meta(): array {
        return [
            '_zippicks_zpid' => $this->zpid,
            '_zippicks_address' => json_encode($this->address->to_array()),
            '_zippicks_cuisine_types' => $this->cuisine_types,
            '_zippicks_price_range' => $this->price_range->value,
            '_zippicks_rating' => $this->rating,
            '_zippicks_review_count' => $this->review_count,
            '_zippicks_business_hours' => json_encode($this->business_hours),
            '_zippicks_contact_info' => json_encode($this->contact_info->to_array()),
            '_zippicks_metadata' => json_encode($this->metadata)
        ];
    }
    
    /**
     * Validate the business profile
     *
     * @return bool
     */
    public function is_valid(): bool {
        return !empty($this->zpid) && 
               !empty($this->name) && 
               $this->address->is_valid();
    }
    
    /**
     * Get formatted price range display
     *
     * @return string
     */
    public function get_price_display(): string {
        return $this->price_range->get_display();
    }
    
    /**
     * Get formatted rating display
     *
     * @return string
     */
    public function get_rating_display(): string {
        if ($this->rating === null) {
            return 'Not rated';
        }
        
        return sprintf('%.1f', $this->rating);
    }
    
    /**
     * Check if business is open now
     *
     * @return bool|null Returns null if hours unknown
     */
    public function is_open_now(): ?bool {
        if (empty($this->business_hours)) {
            return null;
        }
        
        $current_day = strtolower(date('l'));
        $current_time = date('Hi');
        
        if (!isset($this->business_hours[$current_day])) {
            return false;
        }
        
        $hours = $this->business_hours[$current_day];
        
        if ($hours === 'closed' || empty($hours)) {
            return false;
        }
        
        // Parse hours format (e.g., "11:00-22:00")
        if (preg_match('/(\d{2}):(\d{2})-(\d{2}):(\d{2})/', $hours, $matches)) {
            $open_time = $matches[1] . $matches[2];
            $close_time = $matches[3] . $matches[4];
            
            return $current_time >= $open_time && $current_time <= $close_time;
        }
        
        return null;
    }
    
    /**
     * Get primary cuisine type
     *
     * @return string
     */
    public function get_primary_cuisine(): string {
        return $this->cuisine_types[0] ?? 'Business';
    }
    
    /**
     * Get formatted address
     *
     * @return string
     */
    public function get_formatted_address(): string {
        return $this->address->get_formatted();
    }
}