<?php
/**
 * Vibe Service
 * 
 * Business logic layer for vibes management
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Services;

use ZipPicksVibes\Repositories\VibeRepositoryInterface;
use ZipPicksVibes\Models\PaginatedResult;
use ZipPicksVibes\Models\Vibe;
use ZipPicksVibes\Exceptions\VibeNotFoundException;
use ZipPicksVibes\Exceptions\InvalidVibeDataException;
use Exception;
use WP_Error;

/**
 * Class VibeService
 */
class VibeService {
    
    /**
     * Repository instance
     * 
     * @var VibeRepositoryInterface
     */
    private VibeRepositoryInterface $repository;
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Cache instance
     * 
     * @var mixed
     */
    private $cache;
    
    /**
     * Constructor
     * 
     * @param VibeRepositoryInterface $repository
     * @param mixed $logger
     * @param mixed $cache
     */
    public function __construct(VibeRepositoryInterface $repository, $logger = null, $cache = null) {
        $this->repository = $repository;
        $this->logger = $logger;
        $this->cache = $cache;
    }
    
    /**
     * Get vibe by ID
     * 
     * @param int $id Vibe ID
     * @return Vibe
     * @throws VibeNotFoundException If vibe not found
     * @throws Exception For other errors
     */
    public function getVibe(int $id): Vibe {
        try {
            // Validate input
            if ($id <= 0) {
                throw new InvalidVibeDataException('Invalid vibe ID: must be a positive integer');
            }
            
            // Try to get from cache first
            $cacheKey = 'vibe_' . $id;
            if ($this->cache) {
                $cachedVibe = $this->cache->get($cacheKey);
                if ($cachedVibe instanceof Vibe) {
                    // Ensure cached vibe has categories loaded
                    $this->enhanceVibeModel($cachedVibe);
                    $this->logDebug('Vibe retrieved from cache', ['id' => $id]);
                    return $cachedVibe;
                }
            }
            
            // Get from repository
            $vibeData = $this->repository->find($id);
            
            if (!$vibeData) {
                throw new VibeNotFoundException($id);
            }
            
            // Convert to Vibe model
            $vibe = Vibe::fromDatabaseRow($vibeData);
            
            // Enhance vibe data
            $this->enhanceVibeModel($vibe);
            
            // Cache the result
            if ($this->cache) {
                $this->cache->set($cacheKey, $vibe, 3600); // 1 hour cache
            }
            
            $this->logInfo('Vibe retrieved successfully', ['id' => $id]);
            
            return $vibe;
        } catch (VibeNotFoundException $e) {
            $this->logWarning('Vibe not found', ['id' => $id]);
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to get vibe', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get vibe by slug
     * 
     * @param string $slug Vibe slug
     * @return Vibe
     * @throws VibeNotFoundException If vibe not found
     * @throws InvalidVibeDataException If slug is invalid
     * @throws Exception For other errors
     */
    public function getVibeBySlug(string $slug): Vibe {
        try {
            // Validate and sanitize input
            $slug = trim($slug);
            if (empty($slug)) {
                throw new InvalidVibeDataException('Vibe slug cannot be empty');
            }
            
            $slug = sanitize_title($slug);
            
            // Try to get from cache first
            $cacheKey = 'vibe_slug_' . $slug;
            if ($this->cache) {
                $cachedVibe = $this->cache->get($cacheKey);
                if ($cachedVibe instanceof Vibe) {
                    $this->logDebug('Vibe retrieved from cache by slug', ['slug' => $slug]);
                    return $cachedVibe;
                }
            }
            
            // Get from repository
            $vibeData = $this->repository->findBySlug($slug);
            
            if (!$vibeData) {
                throw new VibeNotFoundException($slug);
            }
            
            // Convert to Vibe model
            $vibe = Vibe::fromDatabaseRow($vibeData);
            
            // Enhance vibe data
            $this->enhanceVibeModel($vibe);
            
            // Log access for analytics
            $this->logVibeAccess($vibe);
            
            // Cache the result
            if ($this->cache) {
                $this->cache->set($cacheKey, $vibe, 3600); // 1 hour cache
            }
            
            return $vibe;
        } catch (VibeNotFoundException $e) {
            $this->logWarning('Vibe not found by slug', ['slug' => $slug]);
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to get vibe by slug', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all vibes
     * 
     * @param array $args Query arguments
     * @return Vibe[] Array of Vibe models
     * @throws Exception For errors
     */
    public function getAllVibes(array $args = []): array {
        try {
            // Sanitize arguments
            $args = $this->sanitizeQueryArgs($args);
            
            // Try to get from cache
            $cacheKey = 'all_vibes_' . md5(serialize($args));
            if ($this->cache) {
                $cachedVibes = $this->cache->get($cacheKey);
                if (is_array($cachedVibes)) {
                    $this->logDebug('All vibes retrieved from cache');
                    return $cachedVibes;
                }
            }
            
            // Get from repository
            $vibesData = $this->repository->findAll($args);
            
            // Convert to Vibe models
            $vibes = [];
            foreach ($vibesData as $vibeData) {
                try {
                    $vibe = Vibe::fromDatabaseRow($vibeData);
                    $this->enhanceVibeModel($vibe);
                    $vibes[] = $vibe;
                } catch (Exception $e) {
                    $this->logWarning('Failed to enhance vibe data', [
                        'vibe_id' => $vibeData->id ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    // Still include the vibe, just without enhancements
                    $vibes[] = Vibe::fromDatabaseRow($vibeData);
                }
            }
            
            // Cache the result
            if ($this->cache) {
                $this->cache->set($cacheKey, $vibes, 1800); // 30 minutes cache
            }
            
            $this->logInfo('Retrieved all vibes', ['count' => count($vibes)]);
            
            return $vibes;
        } catch (Exception $e) {
            $this->logError('Failed to get all vibes', [
                'args' => $args,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all active vibes
     * 
     * Simple method to retrieve all active vibes with caching
     * 
     * @return array Array of Vibe objects
     * @throws Exception For errors
     */
    public function getAll(): array {
        try {
            // Try to get from cache first
            $cacheKey = 'vibes_all';
            if ($this->cache) {
                $cachedVibes = $this->cache->get($cacheKey);
                if (is_array($cachedVibes)) {
                    $this->logDebug('All active vibes retrieved from cache');
                    return $cachedVibes;
                }
            }
            
            // Get all active vibes from repository
            $args = ['status' => 'active'];
            $vibesData = $this->repository->findAll($args);
            
            // Convert to Vibe models
            $vibes = [];
            foreach ($vibesData as $vibeData) {
                try {
                    $vibe = Vibe::fromDatabaseRow($vibeData);
                    $this->enhanceVibeModel($vibe);
                    $vibes[] = $vibe;
                } catch (Exception $e) {
                    $this->logWarning('Failed to process vibe', [
                        'vibe_id' => $vibeData->id ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    // Still include the vibe, just without enhancements
                    $vibes[] = Vibe::fromDatabaseRow($vibeData);
                }
            }
            
            // Cache the result
            if ($this->cache) {
                $this->cache->set($cacheKey, $vibes, 1800); // 30 minutes cache
            }
            
            $this->logInfo('Retrieved all active vibes', ['count' => count($vibes)]);
            
            return $vibes;
        } catch (Exception $e) {
            $this->logError('Failed to get all vibes', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get vibes with categories
     * 
     * @param array $args Query arguments
     * @return Vibe[] Array of Vibe models with categories loaded
     * @throws Exception For errors
     */
    public function getAllWithCategories(array $args = []): array {
        try {
            $args = $this->sanitizeQueryArgs($args);
            $vibesData = $this->repository->findAllWithCategories($args);
            
            $vibes = [];
            foreach ($vibesData as $vibeData) {
                $vibe = Vibe::fromDatabaseRow($vibeData);
                
                // Load categories if available
                if (isset($vibeData->categories)) {
                    $vibe->setCategories($vibeData->categories);
                }
                
                $this->enhanceVibeModel($vibe);
                $vibes[] = $vibe;
            }
            
            return $vibes;
        } catch (Exception $e) {
            $this->logError('Failed to get vibes with categories', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get paginated vibes
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $args Query arguments
     * @return PaginatedResult
     * @throws InvalidVibeDataException For invalid pagination parameters
     * @throws Exception For other errors
     */
    public function getVibesPaginated(int $page = 1, int $perPage = 20, array $args = []): PaginatedResult {
        try {
            // Validate pagination parameters
            if ($page < 1) {
                throw new InvalidVibeDataException('Page number must be 1 or greater');
            }
            if ($perPage < 1 || $perPage > 100) {
                throw new InvalidVibeDataException('Items per page must be between 1 and 100');
            }
            
            $args = $this->sanitizeQueryArgs($args);
            
            $paginatedResult = $this->repository->findPaginated($page, $perPage, $args);
            
            // Convert items to Vibe models
            $vibes = [];
            foreach ($paginatedResult->getItems() as $vibeData) {
                try {
                    $vibe = Vibe::fromDatabaseRow($vibeData);
                    $this->enhanceVibeModel($vibe);
                    $vibes[] = $vibe;
                } catch (Exception $e) {
                    $this->logWarning('Failed to process vibe in pagination', [
                        'vibe_id' => $vibeData->id ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $vibes[] = Vibe::fromDatabaseRow($vibeData);
                }
            }
            
            // Create new paginated result with Vibe models
            return new PaginatedResult(
                $vibes,
                $paginatedResult->getTotal(),
                $paginatedResult->getPerPage(),
                $paginatedResult->getCurrentPage(),
                $paginatedResult->getMeta()
            );
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to get paginated vibes', [
                'page' => $page,
                'per_page' => $perPage,
                'error' => $e->getMessage()
            ]);
            // Return empty result on error
            return new PaginatedResult([], 0, $perPage, $page);
        }
    }
    
    /**
     * Get vibes by category
     * 
     * @param int $categoryId Category ID
     * @param array $args Query arguments
     * @return Vibe[] Array of Vibe models
     * @throws InvalidVibeDataException For invalid category ID
     * @throws Exception For other errors
     */
    public function getVibesByCategory(int $categoryId, array $args = []): array {
        try {
            if ($categoryId <= 0) {
                throw new InvalidVibeDataException('Invalid category ID: must be a positive integer');
            }
            
            $args = $this->sanitizeQueryArgs($args);
            $vibesData = $this->repository->findByCategory($categoryId, $args);
            
            $vibes = [];
            foreach ($vibesData as $vibeData) {
                $vibe = Vibe::fromDatabaseRow($vibeData);
                $this->enhanceVibeModel($vibe);
                $vibes[] = $vibe;
            }
            
            return $vibes;
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to get vibes by category', [
                'category_id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Search vibes
     * 
     * @param string $query Search query
     * @param array $args Additional search arguments
     * @return Vibe[] Array of Vibe models matching the search
     * @throws InvalidVibeDataException For invalid search query
     * @throws Exception For other errors
     */
    public function searchVibes(string $query, array $args = []): array {
        try {
            // Validate and sanitize search query
            $query = trim($query);
            if (empty($query)) {
                throw new InvalidVibeDataException('Search query cannot be empty');
            }
            
            // Sanitize search query to prevent SQL injection
            $query = sanitize_text_field($query);
            if (strlen($query) < 2) {
                throw new InvalidVibeDataException('Search query must be at least 2 characters');
            }
            
            $args = $this->sanitizeQueryArgs($args);
            
            // Log search query for analytics
            $this->logSearch($query);
            
            // Perform search
            $vibesData = $this->repository->search($query, $args);
            
            // Convert to Vibe models
            $vibes = [];
            foreach ($vibesData as $vibeData) {
                try {
                    $vibe = Vibe::fromDatabaseRow($vibeData);
                    $this->enhanceVibeModel($vibe);
                    $vibes[] = $vibe;
                } catch (Exception $e) {
                    $this->logWarning('Failed to enhance search result', [
                        'vibe_id' => $vibeData->id ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $vibes[] = Vibe::fromDatabaseRow($vibeData);
                }
            }
            
            $this->logInfo('Search completed', [
                'query' => $query,
                'results' => count($vibes)
            ]);
            
            return $vibes;
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }
    
    /**
     * Search vibes with pagination
     * 
     * @param string $query Search query
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $args Additional search arguments
     * @return PaginatedResult
     * @throws InvalidVibeDataException For invalid parameters
     * @throws Exception For other errors
     */
    public function searchVibesPaginated(string $query, int $page = 1, int $perPage = 20, array $args = []): PaginatedResult {
        try {
            // Validate search query
            $query = trim($query);
            if (empty($query)) {
                throw new InvalidVibeDataException('Search query cannot be empty');
            }
            
            $query = sanitize_text_field($query);
            if (strlen($query) < 2) {
                throw new InvalidVibeDataException('Search query must be at least 2 characters');
            }
            
            // Validate pagination
            if ($page < 1) {
                throw new InvalidVibeDataException('Page number must be 1 or greater');
            }
            if ($perPage < 1 || $perPage > 100) {
                throw new InvalidVibeDataException('Items per page must be between 1 and 100');
            }
            
            $args = $this->sanitizeQueryArgs($args);
            
            // Log search query for analytics
            $this->logSearch($query);
            
            // Perform paginated search
            $paginatedResult = $this->repository->searchPaginated($query, $page, $perPage, $args);
            
            // Convert items to Vibe models
            $vibes = [];
            foreach ($paginatedResult->getItems() as $vibeData) {
                try {
                    $vibe = Vibe::fromDatabaseRow($vibeData);
                    $this->enhanceVibeModel($vibe);
                    $vibes[] = $vibe;
                } catch (Exception $e) {
                    $this->logWarning('Failed to enhance search result', [
                        'vibe_id' => $vibeData->id ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    $vibes[] = Vibe::fromDatabaseRow($vibeData);
                }
            }
            
            // Create new paginated result with enhanced items
            return new PaginatedResult(
                $vibes,
                $paginatedResult->getTotal(),
                $paginatedResult->getPerPage(),
                $paginatedResult->getCurrentPage(),
                array_merge($paginatedResult->getMeta(), ['query' => $query])
            );
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Paginated search failed', [
                'query' => $query,
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            // Return empty result on error
            return new PaginatedResult([], 0, $perPage, $page, ['query' => $query]);
        }
    }
    
    /**
     * Get popular vibes
     * 
     * @param int $limit Number of vibes to return
     * @param string $zipCode ZIP code for location-based popularity
     * @return Vibe[] Array of popular Vibe models
     * @throws InvalidVibeDataException For invalid parameters
     * @throws Exception For other errors
     */
    public function getPopularVibes(int $limit = 10, string $zipCode = ''): array {
        try {
            if ($limit < 1 || $limit > 50) {
                throw new InvalidVibeDataException('Limit must be between 1 and 50');
            }
            
            if (!empty($zipCode)) {
                $zipCode = sanitize_text_field($zipCode);
                if (!preg_match('/^\d{5}(-\d{4})?$/', $zipCode)) {
                    throw new InvalidVibeDataException('Invalid ZIP code format');
                }
            }
            
            $vibesData = $this->repository->getPopular($limit, $zipCode);
            
            $vibes = [];
            foreach ($vibesData as $vibeData) {
                $vibe = Vibe::fromDatabaseRow($vibeData);
                $this->enhanceVibeModel($vibe);
                $vibes[] = $vibe;
            }
            
            return $vibes;
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to get popular vibes', [
                'limit' => $limit,
                'zip_code' => $zipCode,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create new vibe
     * 
     * @param array $data Vibe data
     * @return int Created vibe ID
     * @throws InvalidVibeDataException For validation errors
     * @throws Exception For other errors
     */
    public function createVibe(array $data): int {
        try {
            // Create Vibe model from data
            $vibe = Vibe::fromPostData($data);
            
            // Validate vibe data
            $errors = $vibe->validate();
            if (!empty($errors)) {
                throw new InvalidVibeDataException('Vibe validation failed', $errors);
            }
            
            // Generate unique slug if not provided
            if (empty($vibe->getSlug())) {
                $slug = $this->generateUniqueSlug($vibe->getName());
                $vibe->setSlug($slug);
            }
            
            // Verify slug uniqueness
            try {
                $existingVibe = $this->repository->findBySlug($vibe->getSlug());
                if ($existingVibe) {
                    throw new InvalidVibeDataException('A vibe with this slug already exists');
                }
            } catch (Exception $e) {
                // Ignore if not found - that's what we want
            }
            
            // Create vibe in repository
            $vibeId = $this->repository->create($vibe->toArray());
            
            if (!$vibeId) {
                throw new Exception('Failed to create vibe in database');
            }
            
            // Assign categories if provided
            if ($vibe->hasCategories()) {
                try {
                    $this->repository->assignCategories($vibeId, $vibe->getCategories());
                } catch (Exception $e) {
                    $this->logWarning('Failed to assign categories to vibe', [
                        'vibe_id' => $vibeId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Clear caches
            $this->clearRelatedCaches();
            
            $this->logInfo('Vibe created successfully', [
                'id' => $vibeId,
                'name' => $vibe->getName(),
                'slug' => $vibe->getSlug()
            ]);
            
            return $vibeId;
        } catch (InvalidVibeDataException $e) {
            $this->logWarning('Vibe creation failed - validation error', [
                'errors' => $e->getErrors(),
                'data' => $data
            ]);
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to create vibe', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update vibe
     * 
     * @param int $id Vibe ID
     * @param array $data Updated vibe data
     * @return bool True if successful
     * @throws VibeNotFoundException If vibe not found
     * @throws InvalidVibeDataException For validation errors
     * @throws Exception For other errors
     */
    public function updateVibe(int $id, array $data): bool {
        try {
            if ($id <= 0) {
                throw new InvalidVibeDataException('Invalid vibe ID: must be a positive integer');
            }
            
            // Get existing vibe to ensure it exists
            $existingVibe = $this->getVibe($id);
            
            // Update ONLY provided fields on the existing vibe object
            foreach ($data as $field => $value) {
                switch ($field) {
                    case 'name':
                        if (isset($value) && $value !== '') {
                            $existingVibe->setName($value);
                        }
                        break;
                    case 'slug':
                        if (isset($value) && $value !== '') {
                            $existingVibe->setSlug($value);
                        }
                        break;
                    case 'description':
                        if (isset($value)) {
                            $existingVibe->setDescription($value);
                        }
                        break;
                    case 'color':
                        if (isset($value) && $value !== '') {
                            $existingVibe->setColor($value);
                        }
                        break;
                    case 'icon':
                        if (isset($value) && $value !== '') {
                            $existingVibe->setIcon($value);
                        }
                        break;
                    case 'is_active':
                    case 'vibe_status': // Support both field names
                        if (isset($value)) {
                            $existingVibe->setIsActive((bool) $value);
                        }
                        break;
                    case 'order_position':
                        if (isset($value)) {
                            $existingVibe->setOrderPosition((int) $value);
                        }
                        break;
                    case 'categories':
                        if (array_key_exists('categories', $data)) {
                            // Always update categories when provided, even if empty array
                            $existingVibe->setCategories(is_array($value) ? $value : []);
                        }
                        break;
                }
            }
            
            // Validate updated data
            $errors = $existingVibe->validate();
            if (!empty($errors)) {
                throw new InvalidVibeDataException('Vibe validation failed', $errors);
            }
            
            // Check slug uniqueness if changed
            if (isset($data['slug']) && $data['slug'] !== $existingVibe->getSlug()) {
                try {
                    $slugVibe = $this->repository->findBySlug($existingVibe->getSlug());
                    if ($slugVibe && $slugVibe->id != $id) {
                        throw new InvalidVibeDataException('A vibe with this slug already exists');
                    }
                } catch (VibeNotFoundException $e) {
                    // Good - slug is available
                }
            }
            
            // Build update data array with only the fields that were provided
            $updateData = [];
            if (isset($data['name'])) $updateData['name'] = $existingVibe->getName();
            if (isset($data['slug'])) $updateData['slug'] = $existingVibe->getSlug();
            if (isset($data['description'])) $updateData['description'] = $existingVibe->getDescription();
            if (isset($data['color'])) $updateData['color'] = $existingVibe->getColor();
            if (isset($data['icon'])) $updateData['icon'] = $existingVibe->getIcon();
            if (isset($data['is_active']) || isset($data['vibe_status'])) {
                $updateData['is_active'] = $existingVibe->isActive() ? 1 : 0;
            }
            if (isset($data['order_position'])) $updateData['order_position'] = $existingVibe->getOrderPosition();
            
            // Update vibe in repository
            $result = $this->repository->update($id, $updateData);
            
            if ($result && array_key_exists('categories', $data)) {
                // Update categories only if provided in the data
                $this->repository->assignCategories($id, $existingVibe->getCategories());
            }
            
            // Clear caches
            $this->clearRelatedCaches();
            
            $this->logInfo('Vibe updated successfully', [
                'id' => $id,
                'changes' => array_keys($data)
            ]);
            
            return $result;
        } catch (VibeNotFoundException $e) {
            throw $e;
        } catch (InvalidVibeDataException $e) {
            $this->logWarning('Vibe update failed - validation error', [
                'id' => $id,
                'errors' => $e->getErrors()
            ]);
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to update vibe', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete vibe
     * 
     * @param int $id Vibe ID
     * @return bool True if successful
     * @throws VibeNotFoundException If vibe not found
     * @throws Exception For other errors
     */
    public function deleteVibe(int $id): bool {
        try {
            if ($id <= 0) {
                throw new InvalidVibeDataException('Invalid vibe ID: must be a positive integer');
            }
            
            // Ensure vibe exists
            $this->getVibe($id);
            
            // Check if vibe is in use
            if ($this->isVibeInUse($id)) {
                $this->logWarning('Attempted to delete vibe in use', ['id' => $id]);
                throw new Exception('Cannot delete vibe that is in use');
            }
            
            $result = $this->repository->delete($id);
            
            if ($result) {
                $this->clearRelatedCaches();
                $this->logInfo('Vibe deleted successfully', ['id' => $id]);
            }
            
            return $result;
        } catch (VibeNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to delete vibe', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update vibe order
     * 
     * @param array $order Array of vibe IDs in desired order
     * @return bool True if successful
     * @throws InvalidVibeDataException For invalid order data
     * @throws Exception For other errors
     */
    public function updateVibeOrder(array $order): bool {
        try {
            // Validate order array
            if (empty($order)) {
                throw new InvalidVibeDataException('Order array cannot be empty');
            }
            
            foreach ($order as $position => $vibeId) {
                if (!is_numeric($vibeId) || $vibeId <= 0) {
                    throw new InvalidVibeDataException('Invalid vibe ID in order array: ' . $vibeId);
                }
            }
            
            $result = $this->repository->updateOrder($order);
            
            if ($result) {
                $this->clearRelatedCaches();
                $this->logInfo('Vibe order updated', ['count' => count($order)]);
            }
            
            return $result;
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to update vibe order', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get all categories
     * 
     * @return array Array of category data
     * @throws Exception For errors
     */
    public function getAllCategories(): array {
        try {
            return $this->repository->getAllCategories();
        } catch (Exception $e) {
            $this->logError('Failed to get categories', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Log waitlist entry
     * 
     * @param array $data Waitlist data
     * @return int Entry ID
     * @throws InvalidVibeDataException For validation errors
     * @throws Exception For other errors
     */
    public function logWaitlistEntry(array $data): int {
        try {
            // Validate required fields
            if (empty($data['zip_code']) || empty($data['vibe_id'])) {
                throw new InvalidVibeDataException('ZIP code and vibe ID are required');
            }
            
            // Validate ZIP code format
            $zipCode = sanitize_text_field($data['zip_code']);
            if (!preg_match('/^\d{5}(-\d{4})?$/', $zipCode)) {
                throw new InvalidVibeDataException('Invalid ZIP code format');
            }
            
            // Validate vibe exists
            $vibe = $this->getVibe((int)$data['vibe_id']);
            
            // Add user data if logged in
            if (is_user_logged_in()) {
                $data['user_id'] = get_current_user_id();
                $user = wp_get_current_user();
                $data['email'] = $user->user_email;
            }
            
            // Add IP address
            $data['ip_address'] = $this->getUserIP();
            
            // Add vibe details
            $data['vibe_slug'] = $vibe->getSlug();
            $data['vibe_name'] = $vibe->getName();
            
            $entryId = $this->repository->logWaitlist($data);
            
            if (!$entryId) {
                throw new Exception('Failed to log waitlist entry');
            }
            
            $this->logInfo('Waitlist entry logged', [
                'entry_id' => $entryId,
                'vibe_id' => $data['vibe_id'],
                'zip_code' => $data['zip_code']
            ]);
            
            return $entryId;
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to log waitlist entry', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get obfuscated vibes for frontend
     * 
     * @param array $vibes Optional array of Vibe models to obfuscate
     * @return array Array of obfuscated vibe data
     * @throws Exception For errors
     */
    public function getObfuscatedVibes(array $vibes = []): array {
        try {
            if (empty($vibes)) {
                $vibes = $this->getAllVibes(['status' => 'active']);
            }
            
            $obfuscated = [];
            
            foreach ($vibes as $vibe) {
                if (!$vibe instanceof Vibe) {
                    $this->logWarning('Invalid vibe object in obfuscation');
                    continue;
                }
                
                $obfuscated[] = [
                    'id' => $this->obfuscateId($vibe->getId()),
                    'n' => base64_encode($vibe->getName()),
                    'd' => base64_encode($vibe->getDescription()),
                    'i' => $vibe->getIcon(),
                    'c' => $vibe->getColor(),
                    'h' => $this->generateHash($vibe->getId())
                ];
            }
            
            return $obfuscated;
        } catch (Exception $e) {
            $this->logError('Failed to obfuscate vibes', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get categories for a vibe
     * 
     * @param int $vibeId Vibe ID
     * @return array Array of category IDs
     * @throws InvalidVibeDataException For invalid vibe ID
     * @throws Exception For other errors
     */
    public function get_vibe_categories(int $vibeId): array {
        try {
            if ($vibeId <= 0) {
                throw new InvalidVibeDataException('Invalid vibe ID: must be a positive integer');
            }
            
            return $this->repository->getVibeCategories($vibeId);
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to get vibe categories', [
                'vibe_id' => $vibeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update vibe status (activate/deactivate)
     * 
     * @param int $vibeId Vibe ID
     * @param bool $isActive New active status
     * @return bool True if successful
     * @throws VibeNotFoundException If vibe not found
     * @throws Exception For other errors
     */
    public function update_vibe_status(int $vibeId, bool $isActive): bool {
        try {
            if ($vibeId <= 0) {
                throw new InvalidVibeDataException('Invalid vibe ID: must be a positive integer');
            }
            
            // Ensure vibe exists
            $this->getVibe($vibeId);
            
            $result = $this->repository->update($vibeId, ['is_active' => $isActive ? 1 : 0]);
            
            if ($result) {
                $this->clearRelatedCaches();
                $this->logInfo('Vibe status updated', [
                    'vibe_id' => $vibeId,
                    'is_active' => $isActive
                ]);
            }
            
            return $result;
        } catch (VibeNotFoundException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Failed to update vibe status', [
                'vibe_id' => $vibeId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Perform bulk action on multiple vibes
     * 
     * @param string $action Action to perform (activate, deactivate, delete)
     * @param array $vibeIds Array of vibe IDs
     * @return bool True if at least one action succeeded
     * @throws InvalidVibeDataException For invalid action or IDs
     * @throws Exception For other errors
     */
    public function bulk_action(string $action, array $vibeIds): bool {
        try {
            // Validate action
            $validActions = ['activate', 'deactivate', 'delete'];
            if (!in_array($action, $validActions)) {
                throw new InvalidVibeDataException('Invalid bulk action: ' . $action);
            }
            
            if (empty($vibeIds)) {
                throw new InvalidVibeDataException('No vibe IDs provided');
            }
            
            $success_count = 0;
            $total_count = count($vibeIds);
            $errors = [];
            
            foreach ($vibeIds as $vibeId) {
                $vibeId = (int) $vibeId;
                if ($vibeId <= 0) {
                    $errors[] = 'Invalid vibe ID: ' . $vibeId;
                    continue;
                }
                
                try {
                    switch ($action) {
                        case 'activate':
                            if ($this->repository->update($vibeId, ['is_active' => 1])) {
                                $success_count++;
                            }
                            break;
                            
                        case 'deactivate':
                            if ($this->repository->update($vibeId, ['is_active' => 0])) {
                                $success_count++;
                            }
                            break;
                            
                        case 'delete':
                            if (!$this->isVibeInUse($vibeId) && $this->repository->delete($vibeId)) {
                                $success_count++;
                            }
                            break;
                    }
                } catch (Exception $e) {
                    $errors[] = sprintf('Failed to %s vibe %d: %s', $action, $vibeId, $e->getMessage());
                }
            }
            
            // Clear caches after bulk operations
            $this->clearRelatedCaches();
            
            // Log bulk action
            $this->logInfo('Bulk action completed', [
                'action' => $action,
                'total' => $total_count,
                'successful' => $success_count,
                'errors' => $errors
            ]);
            
            if ($success_count === 0 && !empty($errors)) {
                throw new Exception('All bulk actions failed: ' . implode('; ', $errors));
            }
            
            return $success_count > 0;
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Bulk action failed', [
                'action' => $action,
                'vibe_ids' => $vibeIds,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Create new category
     * 
     * @param array $data Category data
     * @return int Category ID
     * @throws InvalidVibeDataException For validation errors
     * @throws Exception For other errors
     */
    public function create_category(array $data): int {
        try {
            // Validate required fields
            if (empty($data['name'])) {
                throw new InvalidVibeDataException('Category name is required');
            }
            
            // Prepare insert data
            $insert_data = [
                'name' => sanitize_text_field(wp_unslash($data['name'])),
                'slug' => sanitize_title($data['slug'] ?? $data['name']),
                'description' => wp_kses_post(wp_unslash($data['description'] ?? '')),
                'parent_id' => absint($data['parent_id'] ?? 0),
                'order_position' => absint($data['order_position'] ?? 0),
                'created_at' => current_time('mysql')
            ];
            
            // Use repository to create category
            $category_id = $this->repository->createCategory($insert_data);
            
            // Log creation
            $this->logInfo('Category created', [
                'id' => $category_id,
                'name' => $insert_data['name']
            ]);
            
            return $category_id;
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Category creation failed', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Update existing category
     * 
     * @param int $categoryId Category ID
     * @param array $data Updated category data
     * @return bool True if successful
     * @throws InvalidVibeDataException For validation errors
     * @throws Exception For other errors
     */
    public function update_category(int $categoryId, array $data): bool {
        try {
            if ($categoryId <= 0) {
                throw new InvalidVibeDataException('Invalid category ID: must be a positive integer');
            }
            
            // Prepare update data
            $update_data = [];
            
            if (isset($data['name']) && !empty($data['name'])) {
                $update_data['name'] = sanitize_text_field($data['name']);
            }
            
            if (isset($data['slug'])) {
                $update_data['slug'] = sanitize_title($data['slug']);
            }
            
            if (isset($data['description'])) {
                $update_data['description'] = wp_kses_post($data['description']);
            }
            
            if (isset($data['parent_id'])) {
                $update_data['parent_id'] = absint($data['parent_id']);
            }
            
            if (isset($data['order_position'])) {
                $update_data['order_position'] = absint($data['order_position']);
            }
            
            if (empty($update_data)) {
                return true; // Nothing to update
            }
            
            // Use repository to update category
            $result = $this->repository->updateCategory($categoryId, $update_data);
            
            // Log update
            $this->logInfo('Category updated', [
                'id' => $categoryId,
                'changes' => array_keys($update_data)
            ]);
            
            return $result;
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Category update failed', [
                'id' => $categoryId,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete category and all its assignments
     * 
     * @param int $categoryId Category ID
     * @return bool True if successful
     * @throws InvalidVibeDataException For invalid category ID
     * @throws Exception For other errors
     */
    public function delete_category(int $categoryId): bool {
        try {
            if ($categoryId <= 0) {
                throw new InvalidVibeDataException('Invalid category ID: must be a positive integer');
            }
            
            // Use repository to delete category
            $result = $this->repository->deleteCategory($categoryId);
            
            // Log deletion
            $this->logInfo('Category deleted', [
                'id' => $categoryId
            ]);
            
            return $result;
        } catch (InvalidVibeDataException $e) {
            throw $e;
        } catch (Exception $e) {
            $this->logError('Category deletion failed', [
                'id' => $categoryId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Enhance vibe model with additional data
     * 
     * @param Vibe $vibe
     */
    private function enhanceVibeModel(Vibe $vibe): void {
        // Add business count if enabled
        if (defined('ZIPPICKS_SHOW_VIBE_COUNTS') && ZIPPICKS_SHOW_VIBE_COUNTS) {
            $businessCount = $this->getVibeBusinessCount($vibe->getId());
            $vibe->setBusinessCount($businessCount);
        }
        
        // ALWAYS load categories, not just when empty
        if ($vibe->getId()) {
            try {
                $categories = $this->repository->getVibeCategories($vibe->getId());
                // Extract category IDs from the category objects
                $categoryIds = array_map(function($cat) {
                    return is_object($cat) ? (int)$cat->id : (int)$cat;
                }, $categories);
                $vibe->setCategories($categoryIds);
            } catch (Exception $e) {
                $this->logWarning('Failed to load vibe categories', [
                    'vibe_id' => $vibe->getId(),
                    'error' => $e->getMessage()
                ]);
                // Set empty array on error to avoid null issues
                $vibe->setCategories([]);
            }
        }
    }
    
    /**
     * Sanitize query arguments
     * 
     * @param array $args
     * @return array
     */
    private function sanitizeQueryArgs(array $args): array {
        $sanitized = [];
        
        // Whitelist of allowed arguments
        $allowed = ['status', 'orderby', 'order', 'limit', 'offset', 'category', 'exclude'];
        
        foreach ($allowed as $key) {
            if (isset($args[$key])) {
                switch ($key) {
                    case 'status':
                        $sanitized[$key] = in_array($args[$key], ['active', 'inactive', 'all']) 
                            ? $args[$key] : 'active';
                        break;
                        
                    case 'orderby':
                        $sanitized[$key] = in_array($args[$key], ['name', 'order_position', 'created_at', 'updated_at'])
                            ? $args[$key] : 'order_position';
                        break;
                        
                    case 'order':
                        $sanitized[$key] = strtoupper($args[$key]) === 'DESC' ? 'DESC' : 'ASC';
                        break;
                        
                    case 'limit':
                    case 'offset':
                        $sanitized[$key] = absint($args[$key]);
                        break;
                        
                    case 'category':
                        $sanitized[$key] = absint($args[$key]);
                        break;
                        
                    case 'exclude':
                        $sanitized[$key] = array_map('absint', (array) $args[$key]);
                        break;
                        
                    default:
                        $sanitized[$key] = sanitize_text_field($args[$key]);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Generate unique slug
     * 
     * @param string $name
     * @return string
     */
    private function generateUniqueSlug(string $name): string {
        $slug = sanitize_title($name);
        $original_slug = $slug;
        $counter = 1;
        
        while ($this->repository->findBySlug($slug)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Check if vibe is in use
     * 
     * @param int $vibeId
     * @return bool
     */
    private function isVibeInUse(int $vibeId): bool {
        // Check if vibe is assigned to any businesses
        // This would integrate with the business service when available
        // For now, we can check if there are any references in the database
        
        global $wpdb;
        
        // Check for business assignments (when business plugin is active)
        $business_table = $wpdb->prefix . 'zippicks_business_vibes';
        if ($wpdb->get_var("SHOW TABLES LIKE '$business_table'") === $business_table) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $business_table WHERE vibe_id = %d",
                $vibeId
            ));
            if ($count > 0) {
                return true;
            }
        }
        
        // Add other checks as needed
        
        return false;
    }
    
    /**
     * Get vibe business count
     * 
     * @param int $vibeId
     * @return int
     */
    private function getVibeBusinessCount(int $vibeId): int {
        // This would integrate with the business service when available
        if ($this->cache) {
            $cacheKey = 'vibe_business_count_' . $vibeId;
            $count = $this->cache->get($cacheKey);
            if ($count !== false) {
                return (int) $count;
            }
        }
        
        // For now, check directly in database if table exists
        global $wpdb;
        $business_table = $wpdb->prefix . 'zippicks_business_vibes';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$business_table'") === $business_table) {
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $business_table WHERE vibe_id = %d",
                $vibeId
            ));
            
            if ($this->cache) {
                $this->cache->set('vibe_business_count_' . $vibeId, $count, 3600);
            }
            
            return $count;
        }
        
        return 0;
    }
    
    /**
     * Log vibe access
     * 
     * @param Vibe $vibe
     */
    private function logVibeAccess(Vibe $vibe): void {
        $this->logInfo('Vibe accessed', [
            'vibe_id' => $vibe->getId(),
            'vibe_slug' => $vibe->getSlug(),
            'user_id' => get_current_user_id(),
            'ip' => $this->getUserIP()
        ]);
    }
    
    /**
     * Log search query
     * 
     * @param string $query
     */
    private function logSearch(string $query): void {
        $this->logInfo('Vibe search', [
            'query' => $query,
            'user_id' => get_current_user_id(),
            'ip' => $this->getUserIP()
        ]);
    }
    
    /**
     * Get user IP address
     * 
     * @return string
     */
    private function getUserIP(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = filter_var($_SERVER[$key], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                if ($ip !== false) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Clear related caches
     */
    private function clearRelatedCaches(): void {
        if ($this->cache) {
            // Clear vibe-related caches - check if method exists first
            if (method_exists($this->cache, 'clearGroup')) {
                $this->cache->clearGroup('zippicks_vibes');
            } elseif (method_exists($this->cache, 'clearByGroup')) {
                $this->cache->clearByGroup('zippicks_vibes');
            } elseif (method_exists($this->cache, 'flush')) {
                // Fallback to flush if group clearing not available
                $this->cache->flush();
            }
            
            // Clear any page caches
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
        }
    }
    
    /**
     * Obfuscate ID
     * 
     * @param int $id
     * @return string
     */
    private function obfuscateId(int $id): string {
        return base64_encode(hash('sha256', $id . wp_salt(), true));
    }
    
    /**
     * Generate hash
     * 
     * @param int $id
     * @return string
     */
    private function generateHash(int $id): string {
        return substr(md5($id . wp_salt('auth')), 0, 8);
    }
    
    /**
     * Log debug message
     * 
     * @param string $message
     * @param array $context
     */
    private function logDebug(string $message, array $context = []): void {
        if ($this->logger && method_exists($this->logger, 'debug')) {
            $this->logger->debug('[VibeService] ' . $message, $context);
        }
    }
    
    /**
     * Log info message
     * 
     * @param string $message
     * @param array $context
     */
    private function logInfo(string $message, array $context = []): void {
        if ($this->logger && method_exists($this->logger, 'info')) {
            $this->logger->info('[VibeService] ' . $message, $context);
        }
    }
    
    /**
     * Log warning message
     * 
     * @param string $message
     * @param array $context
     */
    private function logWarning(string $message, array $context = []): void {
        if ($this->logger && method_exists($this->logger, 'warning')) {
            $this->logger->warning('[VibeService] ' . $message, $context);
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message
     * @param array $context
     */
    private function logError(string $message, array $context = []): void {
        if ($this->logger && method_exists($this->logger, 'error')) {
            $this->logger->error('[VibeService] ' . $message, $context);
        }
    }
}