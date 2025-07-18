<?php
/**
 * Tagged Cache Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Cache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Cache;

/**
 * Contract for tagged cache operations enabling precise invalidation
 */
interface TaggedCacheInterface extends CacheStoreInterface
{
    /**
     * Begin executing a new tags operation
     */
    public function tags(string|array $names): static;

    /**
     * Flush cache entries with specified tags
     */
    public function flushTags(): bool;

    /**
     * Get current tag set
     */
    public function getTags(): array;
}