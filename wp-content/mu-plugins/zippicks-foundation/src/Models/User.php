<?php
/**
 * User Model
 * 
 * @package ZipPicks\Foundation\Models
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Models;

/**
 * User value object
 */
class User
{
    /**
     * User ID
     * 
     * @var int
     */
    protected int $id;

    /**
     * User email address
     * 
     * @var string
     */
    protected string $email;

    /**
     * User display name
     * 
     * @var string
     */
    protected string $displayName;

    /**
     * User roles
     * 
     * @var array<string>
     */
    protected array $roles;

    /**
     * Additional user metadata
     * 
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Create a new user instance
     * 
     * @param int $id User ID
     * @param string $email Email address
     * @param string $displayName Display name
     * @param array<string> $roles User roles
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        int $id,
        string $email,
        string $displayName,
        array $roles = [],
        array $metadata = []
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->displayName = $displayName;
        $this->roles = array_values($roles);
        $this->metadata = $metadata;
    }

    /**
     * Get the user ID
     * 
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the user email
     * 
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Get the user display name
     * 
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    /**
     * Get all user roles
     * 
     * @return array<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Check if the user has a specific role
     * 
     * @param string $role The role to check
     * 
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Check if the user has any of the specified roles
     * 
     * @param array<string> $roles The roles to check
     * 
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        return count(array_intersect($roles, $this->roles)) > 0;
    }

    /**
     * Check if the user has all of the specified roles
     * 
     * @param array<string> $roles The roles to check
     * 
     * @return bool
     */
    public function hasAllRoles(array $roles): bool
    {
        return count(array_diff($roles, $this->roles)) === 0;
    }

    /**
     * Get a metadata value
     * 
     * @param string $key The metadata key
     * @param mixed $default Default value if key doesn't exist
     * 
     * @return mixed
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get all metadata
     * 
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create a new user instance with updated metadata
     * 
     * @param array<string, mixed> $metadata
     * 
     * @return self
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->id,
            $this->email,
            $this->displayName,
            $this->roles,
            array_merge($this->metadata, $metadata)
        );
    }

    /**
     * Check if user is an administrator
     * 
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('administrator');
    }

    /**
     * Convert user to array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'roles' => $this->roles,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create a user from array data
     * 
     * @param array<string, mixed> $data
     * 
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['id'] ?? 0),
            (string) ($data['email'] ?? ''),
            (string) ($data['display_name'] ?? $data['displayName'] ?? ''),
            (array) ($data['roles'] ?? []),
            (array) ($data['metadata'] ?? [])
        );
    }
}