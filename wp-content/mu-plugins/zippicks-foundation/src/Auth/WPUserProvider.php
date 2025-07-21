<?php
/**
 * WordPress User Provider
 * 
 * @package ZipPicks\Foundation\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Auth;

use ZipPicks\Foundation\Contracts\Auth\UserProviderInterface;
use ZipPicks\Foundation\Models\User;
use WP_User;

/**
 * WordPress-based user provider implementation
 */
class WPUserProvider implements UserProviderInterface
{
    /**
     * User cache to avoid repeated lookups
     * 
     * @var array<int, User>
     */
    protected array $userCache = [];

    /**
     * {@inheritdoc}
     */
    public function getCurrentUser(): ?User
    {
        if (!function_exists('wp_get_current_user')) {
            return null;
        }

        $wpUser = wp_get_current_user();
        
        if (!$wpUser || !$wpUser->exists() || $wpUser->ID === 0) {
            return null;
        }

        return $this->mapWpUser($wpUser);
    }

    /**
     * {@inheritdoc}
     */
    public function findById(int $id): ?User
    {
        if ($id <= 0) {
            return null;
        }

        // Check cache first
        if (isset($this->userCache[$id])) {
            return $this->userCache[$id];
        }

        if (!function_exists('get_userdata')) {
            return null;
        }

        $wpUser = get_userdata($id);
        
        if (!$wpUser || !$wpUser instanceof WP_User) {
            return null;
        }

        $user = $this->mapWpUser($wpUser);
        
        if ($user) {
            $this->userCache[$id] = $user;
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function findByEmail(string $email): ?User
    {
        $email = sanitize_email($email);
        
        if (empty($email)) {
            return null;
        }

        if (!function_exists('get_user_by')) {
            return null;
        }

        $wpUser = get_user_by('email', $email);
        
        if (!$wpUser || !$wpUser instanceof WP_User) {
            return null;
        }

        return $this->mapWpUser($wpUser);
    }

    /**
     * Map a WordPress user to our User model
     * 
     * @param WP_User $wpUser
     * 
     * @return User|null
     */
    protected function mapWpUser(WP_User $wpUser): ?User
    {
        if (!$wpUser->exists() || $wpUser->ID === 0) {
            return null;
        }

        $id = (int) $wpUser->ID;
        $email = (string) $wpUser->user_email;
        $displayName = (string) ($wpUser->display_name ?: $wpUser->user_login);
        
        // Get roles from WordPress user
        $roles = $this->extractRoles($wpUser);
        
        // Get additional metadata
        $metadata = $this->extractMetadata($wpUser);

        return new User($id, $email, $displayName, $roles, $metadata);
    }

    /**
     * Extract roles from WordPress user
     * 
     * @param WP_User $wpUser
     * 
     * @return array<string>
     */
    protected function extractRoles(WP_User $wpUser): array
    {
        $roles = [];
        
        if (!empty($wpUser->roles) && is_array($wpUser->roles)) {
            $roles = array_values($wpUser->roles);
        }

        return $roles;
    }

    /**
     * Extract additional metadata from WordPress user
     * 
     * @param WP_User $wpUser
     * 
     * @return array<string, mixed>
     */
    protected function extractMetadata(WP_User $wpUser): array
    {
        $metadata = [
            'user_login' => (string) $wpUser->user_login,
            'user_nicename' => (string) $wpUser->user_nicename,
            'user_url' => (string) $wpUser->user_url,
            'user_registered' => (string) $wpUser->user_registered,
            'user_status' => (int) $wpUser->user_status,
        ];

        // Add first and last name if available
        if (!empty($wpUser->first_name)) {
            $metadata['first_name'] = (string) $wpUser->first_name;
        }
        
        if (!empty($wpUser->last_name)) {
            $metadata['last_name'] = (string) $wpUser->last_name;
        }

        // Add capabilities
        if (!empty($wpUser->allcaps) && is_array($wpUser->allcaps)) {
            $metadata['capabilities'] = array_keys(array_filter($wpUser->allcaps));
        }

        return $metadata;
    }

    /**
     * Clear the user cache
     * 
     * @return void
     */
    public function clearCache(): void
    {
        $this->userCache = [];
    }

    /**
     * Clear cache for a specific user
     * 
     * @param int $userId
     * 
     * @return void
     */
    public function clearUserCache(int $userId): void
    {
        unset($this->userCache[$userId]);
    }
}