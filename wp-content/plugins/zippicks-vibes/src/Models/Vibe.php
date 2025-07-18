<?php
/**
 * Vibe Model
 * 
 * Represents a Vibe entity with its properties and business logic
 * 
 * @package ZipPicksVibes\Models
 * @since 2.0.0
 */

namespace ZipPicksVibes\Models;

use JsonSerializable;

class Vibe implements JsonSerializable {
    
    /**
     * Vibe ID
     * 
     * @var int
     */
    private $id;
    
    /**
     * Vibe name
     * 
     * @var string
     */
    private $name;
    
    /**
     * Vibe slug
     * 
     * @var string
     */
    private $slug;
    
    /**
     * Vibe description
     * 
     * @var string
     */
    private $description;
    
    /**
     * Vibe color (hex code)
     * 
     * @var string
     */
    private $color;
    
    /**
     * Vibe icon filename
     * 
     * @var string
     */
    private $icon;
    
    /**
     * Whether vibe is active
     * 
     * @var bool
     */
    private $isActive;
    
    /**
     * Sort order position
     * 
     * @var int
     */
    private $orderPosition;
    
    /**
     * Created date
     * 
     * @var string
     */
    private $createdAt;
    
    /**
     * Updated date
     * 
     * @var string
     */
    private $updatedAt;
    
    /**
     * Associated categories
     * 
     * @var array
     */
    private $categories = [];
    
    /**
     * Business count
     * 
     * @var int
     */
    private $businessCount = 0;
    
    /**
     * Constructor
     * 
     * @param array $data Vibe data
     */
    public function __construct(array $data = []) {
        $this->hydrate($data);
    }
    
    /**
     * Hydrate model with data
     * 
     * @param array $data
     * @return void
     */
    public function hydrate(array $data): void {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? '';
        $this->slug = $data['slug'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->color = $data['color'] ?? '#194FAD';
        $this->icon = $data['icon'] ?? '⭐';
        $this->isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;
        $this->orderPosition = (int) ($data['order_position'] ?? 0);
        $this->createdAt = $data['created_at'] ?? '';
        $this->updatedAt = $data['updated_at'] ?? '';
        $this->categories = $data['categories'] ?? [];
        $this->businessCount = (int) ($data['business_count'] ?? 0);
    }
    
    /**
     * Get vibe ID
     * 
     * @return int|null
     */
    public function getId(): ?int {
        return $this->id;
    }
    
    /**
     * Set vibe ID
     * 
     * @param int $id
     * @return self
     */
    public function setId(int $id): self {
        $this->id = $id;
        return $this;
    }
    
    /**
     * Get vibe name
     * 
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }
    
    /**
     * Set vibe name
     * 
     * @param string $name
     * @return self
     */
    public function setName(string $name): self {
        $this->name = sanitize_text_field($name);
        return $this;
    }
    
    /**
     * Get vibe slug
     * 
     * @return string
     */
    public function getSlug(): string {
        return $this->slug;
    }
    
    /**
     * Set vibe slug
     * 
     * @param string $slug
     * @return self
     */
    public function setSlug(string $slug): self {
        $this->slug = sanitize_title($slug);
        return $this;
    }
    
    /**
     * Get vibe description
     * 
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }
    
    /**
     * Set vibe description
     * 
     * @param string $description
     * @return self
     */
    public function setDescription(string $description): self {
        $this->description = wp_kses_post($description);
        return $this;
    }
    
    /**
     * Get vibe color
     * 
     * @return string
     */
    public function getColor(): string {
        return $this->color;
    }
    
    /**
     * Set vibe color
     * 
     * @param string $color
     * @return self
     */
    public function setColor(string $color): self {
        // Validate hex color
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            $this->color = $color;
        }
        return $this;
    }
    
    /**
     * Get vibe icon
     * 
     * @return string
     */
    public function getIcon(): string {
        return $this->icon;
    }
    
    /**
     * Set vibe icon
     * 
     * @param string $icon
     * @return self
     */
    public function setIcon(string $icon): self {
        // Allow emojis and font-awesome classes as well as filenames
        if (preg_match('/^[\x{1F300}-\x{1F9FF}]/u', $icon) || strpos($icon, 'fa-') === 0) {
            $this->icon = $icon;
        } else {
            $this->icon = sanitize_file_name($icon);
        }
        return $this;
    }
    
    /**
     * Check if vibe is active
     * 
     * @return bool
     */
    public function isActive(): bool {
        return $this->isActive;
    }
    
    /**
     * Set vibe active status
     * 
     * @param bool $isActive
     * @return self
     */
    public function setIsActive(bool $isActive): self {
        $this->isActive = $isActive;
        return $this;
    }
    
    /**
     * Get order position
     * 
     * @return int
     */
    public function getOrderPosition(): int {
        return $this->orderPosition;
    }
    
    /**
     * Set order position
     * 
     * @param int $orderPosition
     * @return self
     */
    public function setOrderPosition(int $orderPosition): self {
        $this->orderPosition = max(0, $orderPosition);
        return $this;
    }
    
    /**
     * Get created date
     * 
     * @return string
     */
    public function getCreatedAt(): string {
        return $this->createdAt;
    }
    
    /**
     * Get updated date
     * 
     * @return string
     */
    public function getUpdatedAt(): string {
        return $this->updatedAt;
    }
    
    /**
     * Get categories
     * 
     * @return array
     */
    public function getCategories(): array {
        return $this->categories;
    }
    
    /**
     * Set categories
     * 
     * @param array $categories
     * @return self
     */
    public function setCategories(array $categories): self {
        $this->categories = array_map('intval', $categories);
        return $this;
    }
    
    /**
     * Add category
     * 
     * @param int $categoryId
     * @return self
     */
    public function addCategory(int $categoryId): self {
        if (!in_array($categoryId, $this->categories)) {
            $this->categories[] = $categoryId;
        }
        return $this;
    }
    
    /**
     * Remove category
     * 
     * @param int $categoryId
     * @return self
     */
    public function removeCategory(int $categoryId): self {
        $this->categories = array_filter($this->categories, function($id) use ($categoryId) {
            return $id !== $categoryId;
        });
        return $this;
    }
    
    /**
     * Get business count
     * 
     * @return int
     */
    public function getBusinessCount(): int {
        return $this->businessCount;
    }
    
    /**
     * Set business count
     * 
     * @param int $businessCount
     * @return self
     */
    public function setBusinessCount(int $businessCount): self {
        $this->businessCount = max(0, $businessCount);
        return $this;
    }
    
    /**
     * Get icon URL
     * 
     * @return string
     */
    public function getIconUrl(): string {
        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        return $plugin_url . 'assets/icons/vibes/' . $this->icon;
    }
    
    /**
     * Get vibe URL
     * 
     * @return string
     */
    public function getUrl(): string {
        return home_url('/vibes/' . $this->slug . '/');
    }
    
    /**
     * Check if vibe has categories
     * 
     * @return bool
     */
    public function hasCategories(): bool {
        return !empty($this->categories);
    }
    
    /**
     * Check if vibe belongs to category
     * 
     * @param int $categoryId
     * @return bool
     */
    public function belongsToCategory(int $categoryId): bool {
        return in_array($categoryId, $this->categories);
    }
    
    /**
     * Validate vibe data
     * 
     * @return array Array of validation errors
     */
    public function validate(): array {
        $errors = [];
        
        if (empty($this->name)) {
            $errors['name'] = 'Vibe name is required';
        }
        
        if (empty($this->slug)) {
            $errors['slug'] = 'Vibe slug is required';
        }
        
        if (!preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $this->color)) {
            $errors['color'] = 'Invalid color format';
        }
        
        // Icon validation is now optional since we support emojis and defaults
        
        return $errors;
    }
    
    /**
     * Check if vibe is valid
     * 
     * @return bool
     */
    public function isValid(): bool {
        return empty($this->validate());
    }
    
    /**
     * Convert to array for database storage
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->isActive ? 1 : 0,
            'order_position' => $this->orderPosition,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
    
    /**
     * Convert to array for API response
     * 
     * @return array
     */
    public function toApiArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,
            'icon' => $this->icon,
            'icon_url' => $this->getIconUrl(),
            'url' => $this->getUrl(),
            'is_active' => $this->isActive,
            'order_position' => $this->orderPosition,
            'categories' => $this->categories,
            'business_count' => $this->businessCount,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt
        ];
    }
    
    /**
     * Convert to array for security-protected API response
     * 
     * @return array
     */
    public function toSecureApiArray(): array {
        return [
            // Obfuscated data for anti-scraping
            'i' => $this->id,
            'n' => base64_encode($this->name), // Encoded name
            's' => $this->slug,
            'd' => base64_encode($this->description), // Encoded description
            'c' => $this->color,
            'icon' => $this->icon,
            'a' => $this->isActive ? 1 : 0,
            'o' => $this->orderPosition,
            'cats' => $this->categories,
            'bc' => $this->businessCount,
            'h' => hash('md5', $this->slug . time()) // Hash for fingerprinting
        ];
    }
    
    /**
     * Serialize to JSON
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return $this->toApiArray();
    }
    
    /**
     * Create from database row
     * 
     * @param object $row Database row
     * @return self
     */
    public static function fromDatabaseRow($row): self {
        $data = [
            'id' => (int) $row->id,
            'name' => $row->name,
            'slug' => $row->slug,
            'description' => $row->description,
            'color' => $row->color,
            'icon' => $row->icon,
            'is_active' => $row->is_active == 1 || $row->is_active === '1' || $row->is_active === true,
            'order_position' => (int) $row->order_position,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at
        ];
        return new self($data);
    }
    
    /**
     * Create from POST data
     * 
     * @param array $postData POST data array
     * @return self
     */
    public static function fromPostData(array $postData): self {
        // Support both formats: with 'vibe_' prefix (from AJAX) and without (from service)
        $data = [
            'name' => $postData['vibe_name'] ?? $postData['name'] ?? '',
            'slug' => $postData['vibe_slug'] ?? $postData['slug'] ?? '',
            'description' => $postData['vibe_description'] ?? $postData['description'] ?? '',
            'color' => $postData['vibe_color'] ?? $postData['color'] ?? '#194FAD',
            'icon' => $postData['vibe_icon'] ?? $postData['icon'] ?? '⭐',
            // Handle both vibe_status (from form) and is_active (from service)
            // Ensure we get integer 1 or 0, not boolean
            'is_active' => isset($postData['vibe_status']) ? (int)$postData['vibe_status'] : (isset($postData['is_active']) ? (int)$postData['is_active'] : 1),
            'order_position' => (int) ($postData['vibe_order_position'] ?? $postData['order_position'] ?? 0),
            'categories' => $postData['vibe_categories'] ?? $postData['categories'] ?? []
        ];
        
        return new self($data);
    }
    
    /**
     * Generate slug from name
     * 
     * @param string $name
     * @return string
     */
    public static function generateSlug(string $name): string {
        return sanitize_title($name);
    }
    
    /**
     * Get default color
     * 
     * @return string
     */
    public static function getDefaultColor(): string {
        return '#194FAD';
    }
    
    /**
     * Get default icon
     * 
     * @return string
     */
    public static function getDefaultIcon(): string {
        return '⭐';
    }
    
    /**
     * Clone vibe with new data
     * 
     * @param array $data
     * @return self
     */
    public function clone(array $data = []): self {
        $currentData = $this->toArray();
        $newData = array_merge($currentData, $data);
        unset($newData['id']); // Remove ID for cloning
        
        return new self($newData);
    }
}