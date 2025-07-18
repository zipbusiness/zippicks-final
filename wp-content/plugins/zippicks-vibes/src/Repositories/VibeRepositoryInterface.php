<?php
/**
 * Vibe Repository Interface
 * 
 * Contract for vibe data access layer
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Repositories;

use ZipPicksVibes\Models\PaginatedResult;

/**
 * Interface VibeRepositoryInterface
 */
interface VibeRepositoryInterface {
    
    /**
     * Find vibe by ID
     * 
     * @param int $id
     * @return object|null
     */
    public function find(int $id): ?object;
    
    /**
     * Find vibe by slug
     * 
     * @param string $slug
     * @return object|null
     */
    public function findBySlug(string $slug): ?object;
    
    /**
     * Get all vibes
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function findAll(array $args = []): array;
    
    /**
     * Get paginated vibes
     * 
     * @param int $page Current page
     * @param int $perPage Items per page
     * @param array $args Query arguments
     * @return PaginatedResult
     */
    public function findPaginated(int $page = 1, int $perPage = 20, array $args = []): PaginatedResult;
    
    /**
     * Get vibes by category
     * 
     * @param int $categoryId
     * @param array $args Additional query arguments
     * @return array
     */
    public function findByCategory(int $categoryId, array $args = []): array;
    
    /**
     * Get vibes with categories
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function findAllWithCategories(array $args = []): array;
    
    /**
     * Create new vibe
     * 
     * @param array $data
     * @return int|false Insert ID or false on failure
     */
    public function create(array $data);
    
    /**
     * Update vibe
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool;
    
    /**
     * Delete vibe
     * 
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;
    
    /**
     * Update vibe order
     * 
     * @param array $order Array of vibe IDs in order
     * @return bool
     */
    public function updateOrder(array $order): bool;
    
    /**
     * Get categories for a vibe
     * 
     * @param int $vibeId
     * @return array
     */
    public function getVibeCategories(int $vibeId): array;
    
    /**
     * Assign categories to vibe
     * 
     * @param int $vibeId
     * @param array $categoryIds
     * @return bool
     */
    public function assignCategories(int $vibeId, array $categoryIds): bool;
    
    /**
     * Get all categories
     * 
     * @return array
     */
    public function getAllCategories(): array;
    
    /**
     * Create new category
     * 
     * @param array $data Category data
     * @return int Category ID
     */
    public function createCategory(array $data): int;
    
    /**
     * Update existing category
     * 
     * @param int $categoryId Category ID
     * @param array $data Category data
     * @return bool Success status
     */
    public function updateCategory(int $categoryId, array $data): bool;
    
    /**
     * Delete category
     * 
     * @param int $categoryId Category ID
     * @return bool Success status
     */
    public function deleteCategory(int $categoryId): bool;
    
    /**
     * Search vibes
     * 
     * @param string $query
     * @param array $args Additional arguments
     * @return array
     */
    public function search(string $query, array $args = []): array;
    
    /**
     * Search vibes with pagination
     * 
     * @param string $query
     * @param int $page
     * @param int $perPage
     * @param array $args Additional arguments
     * @return PaginatedResult
     */
    public function searchPaginated(string $query, int $page = 1, int $perPage = 20, array $args = []): PaginatedResult;
    
    /**
     * Get vibe count
     * 
     * @param array $args Query arguments
     * @return int
     */
    public function count(array $args = []): int;
    
    /**
     * Log waitlist entry
     * 
     * @param array $data
     * @return int|false
     */
    public function logWaitlist(array $data);
    
    /**
     * Get popular vibes
     * 
     * @param int $limit
     * @param string $zipCode Optional ZIP code filter
     * @return array
     */
    public function getPopular(int $limit = 10, string $zipCode = ''): array;
}