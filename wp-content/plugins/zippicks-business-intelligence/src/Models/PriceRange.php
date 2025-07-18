<?php
/**
 * Price Range Model
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Models;

class PriceRange {
    
    /**
     * Price range value (e.g., '$', '$$', '$$$', '$$$$')
     *
     * @var string
     */
    public string $value;
    
    /**
     * Numeric representation (1-4)
     *
     * @var int
     */
    public int $numeric;
    
    /**
     * Price range mappings
     *
     * @var array
     */
    private const PRICE_MAPPINGS = [
        '$' => 1,
        '$$' => 2,
        '$$$' => 3,
        '$$$$' => 4
    ];
    
    /**
     * Price range descriptions
     *
     * @var array
     */
    private const PRICE_DESCRIPTIONS = [
        1 => 'Budget-friendly',
        2 => 'Moderate',
        3 => 'Upscale',
        4 => 'Fine dining'
    ];
    
    /**
     * Price range estimates
     *
     * @var array
     */
    private const PRICE_ESTIMATES = [
        1 => 'Under $15 per person',
        2 => '$15-30 per person',
        3 => '$30-60 per person',
        4 => 'Over $60 per person'
    ];
    
    /**
     * Constructor
     *
     * @param string|int $value Price range value
     */
    public function __construct($value = '') {
        if (is_numeric($value)) {
            $this->numeric = max(1, min(4, (int) $value));
            $this->value = str_repeat('$', $this->numeric);
        } else {
            $this->value = $value ?: '$';
            $this->numeric = self::PRICE_MAPPINGS[$this->value] ?? 1;
        }
    }
    
    /**
     * Get display string
     *
     * @return string
     */
    public function get_display(): string {
        return $this->value;
    }
    
    /**
     * Get description
     *
     * @return string
     */
    public function get_description(): string {
        return self::PRICE_DESCRIPTIONS[$this->numeric] ?? 'Unknown';
    }
    
    /**
     * Get price estimate
     *
     * @return string
     */
    public function get_estimate(): string {
        return self::PRICE_ESTIMATES[$this->numeric] ?? 'Price varies';
    }
    
    /**
     * Convert to array
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'value' => $this->value,
            'numeric' => $this->numeric,
            'description' => $this->get_description(),
            'estimate' => $this->get_estimate()
        ];
    }
    
    /**
     * Compare with another price range
     *
     * @param PriceRange $other
     * @return int -1 if less than, 0 if equal, 1 if greater than
     */
    public function compare(PriceRange $other): int {
        return $this->numeric <=> $other->numeric;
    }
    
    /**
     * Check if price range is in budget
     *
     * @param int $max_price Maximum price level (1-4)
     * @return bool
     */
    public function is_within_budget(int $max_price): bool {
        return $this->numeric <= $max_price;
    }
    
    /**
     * Create from string or number
     *
     * @param mixed $value
     * @return self
     */
    public static function from($value): self {
        return new self($value);
    }
}