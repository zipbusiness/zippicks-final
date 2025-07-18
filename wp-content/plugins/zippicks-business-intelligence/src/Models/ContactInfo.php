<?php
/**
 * Contact Information Model
 *
 * @package ZipPicks\BusinessIntelligence
 */

namespace ZipPicks\BusinessIntelligence\Models;

class ContactInfo {
    
    /**
     * Phone number
     *
     * @var string
     */
    public string $phone;
    
    /**
     * Website URL
     *
     * @var string
     */
    public string $website;
    
    /**
     * Email address
     *
     * @var string
     */
    public string $email;
    
    /**
     * Social media handles
     *
     * @var array
     */
    public array $social_media;
    
    /**
     * Reservation system info
     *
     * @var array
     */
    public array $reservations;
    
    /**
     * Constructor
     *
     * @param array $data Contact data
     */
    public function __construct(array $data = []) {
        $this->phone = $data['phone'] ?? '';
        $this->website = $data['website'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->social_media = $data['social_media'] ?? [];
        $this->reservations = $data['reservations'] ?? [];
    }
    
    /**
     * Convert to array
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'phone' => $this->phone,
            'website' => $this->website,
            'email' => $this->email,
            'social_media' => $this->social_media,
            'reservations' => $this->reservations
        ];
    }
    
    /**
     * Get formatted phone number
     *
     * @return string
     */
    public function get_formatted_phone(): string {
        if (empty($this->phone)) {
            return '';
        }
        
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $this->phone);
        
        // Format as (XXX) XXX-XXXX for US numbers
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }
        
        return $this->phone;
    }
    
    /**
     * Get clickable phone link
     *
     * @return string
     */
    public function get_phone_link(): string {
        if (empty($this->phone)) {
            return '';
        }
        
        $phone = preg_replace('/[^0-9+]/', '', $this->phone);
        return "tel:{$phone}";
    }
    
    /**
     * Check if website is valid
     *
     * @return bool
     */
    public function has_valid_website(): bool {
        return !empty($this->website) && filter_var($this->website, FILTER_VALIDATE_URL);
    }
    
    /**
     * Get domain from website
     *
     * @return string
     */
    public function get_website_domain(): string {
        if (!$this->has_valid_website()) {
            return '';
        }
        
        $parsed = parse_url($this->website);
        return $parsed['host'] ?? '';
    }
    
    /**
     * Get social media link
     *
     * @param string $platform Platform name (facebook, instagram, twitter, etc.)
     * @return string
     */
    public function get_social_link(string $platform): string {
        $platform = strtolower($platform);
        
        if (!isset($this->social_media[$platform])) {
            return '';
        }
        
        $handle = $this->social_media[$platform];
        
        // Return full URL if already provided
        if (filter_var($handle, FILTER_VALIDATE_URL)) {
            return $handle;
        }
        
        // Build URL based on platform
        switch ($platform) {
            case 'facebook':
                return "https://facebook.com/{$handle}";
            case 'instagram':
                return "https://instagram.com/{$handle}";
            case 'twitter':
            case 'x':
                return "https://x.com/{$handle}";
            case 'linkedin':
                return "https://linkedin.com/company/{$handle}";
            case 'youtube':
                return "https://youtube.com/@{$handle}";
            case 'tiktok':
                return "https://tiktok.com/@{$handle}";
            default:
                return $handle;
        }
    }
    
    /**
     * Check if reservation system is available
     *
     * @param string $system System name (opentable, resy, yelp, etc.)
     * @return bool
     */
    public function has_reservation_system(string $system = ''): bool {
        if (empty($system)) {
            return !empty($this->reservations);
        }
        
        return isset($this->reservations[strtolower($system)]);
    }
    
    /**
     * Get reservation link
     *
     * @param string $system System name
     * @return string
     */
    public function get_reservation_link(string $system = ''): string {
        if (empty($this->reservations)) {
            return '';
        }
        
        if (empty($system)) {
            // Return first available reservation link
            return reset($this->reservations) ?: '';
        }
        
        return $this->reservations[strtolower($system)] ?? '';
    }
    
    /**
     * Check if any contact method is available
     *
     * @return bool
     */
    public function has_any_contact(): bool {
        return !empty($this->phone) || 
               !empty($this->website) || 
               !empty($this->email) || 
               !empty($this->social_media);
    }
}