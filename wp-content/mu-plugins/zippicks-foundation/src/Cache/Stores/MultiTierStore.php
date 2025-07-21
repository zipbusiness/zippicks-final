<?php
/**
 * Multi-Tier Cache Store
 * 
 * @package ZipPicks\Foundation\Cache\Stores
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Cache\Stores;

use ZipPicks\Foundation\Contracts\Cache\CacheStoreInterface;

/**
 * Multi-tier cache store for enterprise-grade performance
 * 
 * Implements a waterfall caching strategy:
 * - L1: In-memory (ArrayStore) - Sub-microsecond
 * - L2: WordPress Object Cache - Microseconds
 * - L3: Redis - Milliseconds
 * - L4: Database - Fallback
 */
class MultiTierStore implements CacheStoreInterface
{
    private array $tiers;
    private array $metrics = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
        'tier_hits' => [],
    ];

    public function __construct(array $tiers)
    {
        $this->tiers = $tiers;
        
        foreach ($tiers as $index => $tier) {
            $this->metrics['tier_hits'][$index] = 0;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // Try each tier in order
        foreach ($this->tiers as $index => $tier) {
            if (!$tier->isHealthy()) {
                continue;
            }

            $value = $tier->get($key);
            
            if ($value !== null) {
                $this->metrics['hits']++;
                $this->metrics['tier_hits'][$index]++;
                
                // Backfill to faster tiers
                $this->backfillTiers($key, $value, $index);
                
                return $value;
            }
        }

        $this->metrics['misses']++;
        return $default;
    }

    public function many(array $keys): array
    {
        $results = array_fill_keys($keys, null);
        $remaining = $keys;

        // Try each tier for remaining keys
        foreach ($this->tiers as $tierIndex => $tier) {
            if (empty($remaining) || !$tier->isHealthy()) {
                continue;
            }

            $tierResults = $tier->many($remaining);
            
            foreach ($tierResults as $key => $value) {
                if ($value !== null) {
                    $results[$key] = $value;
                    $this->metrics['tier_hits'][$tierIndex]++;
                    
                    // Remove found keys from remaining
                    $remaining = array_diff($remaining, [$key]);
                }
            }
        }

        $foundCount = count(array_filter($results, fn($v) => $v !== null));
        $this->metrics['hits'] += $foundCount;
        $this->metrics['misses'] += count($keys) - $foundCount;

        return $results;
    }

    public function put(string $key, mixed $value, ?int $seconds = null): bool
    {
        $success = false;

        // Write to all healthy tiers
        foreach ($this->tiers as $tier) {
            if ($tier->isHealthy()) {
                if ($tier->put($key, $value, $seconds)) {
                    $success = true;
                }
            }
        }

        if ($success) {
            $this->metrics['writes']++;
        }

        return $success;
    }

    public function putMany(array $values, ?int $seconds = null): bool
    {
        $success = false;

        // Write to all healthy tiers
        foreach ($this->tiers as $tier) {
            if ($tier->isHealthy()) {
                if ($tier->putMany($values, $seconds)) {
                    $success = true;
                }
            }
        }

        if ($success) {
            $this->metrics['writes'] += count($values);
        }

        return $success;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        // Use the first tier that supports increment
        foreach ($this->tiers as $tier) {
            if ($tier->isHealthy()) {
                $result = $tier->increment($key, $value);
                
                if ($result !== false) {
                    // Sync to other tiers
                    foreach ($this->tiers as $otherTier) {
                        if ($otherTier !== $tier && $otherTier->isHealthy()) {
                            $otherTier->put($key, $result);
                        }
                    }
                    
                    return $result;
                }
            }
        }

        return false;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value);
    }

    public function forget(string $key): bool
    {
        $success = false;

        // Delete from all tiers
        foreach ($this->tiers as $tier) {
            if ($tier->isHealthy() && $tier->forget($key)) {
                $success = true;
            }
        }

        if ($success) {
            $this->metrics['deletes']++;
        }

        return $success;
    }

    public function flush(): bool
    {
        $success = true;

        // Flush all tiers
        foreach ($this->tiers as $tier) {
            if ($tier->isHealthy() && !$tier->flush()) {
                $success = false;
            }
        }

        return $success;
    }

    public function getPrefix(): string
    {
        // Return prefix from first tier
        return $this->tiers[0]->getPrefix();
    }

    public function getMetrics(): array
    {
        $tierMetrics = [];
        
        foreach ($this->tiers as $index => $tier) {
            $tierMetrics[$tier->getName()] = array_merge(
                $tier->getMetrics(),
                ['tier_position' => $index]
            );
        }

        return array_merge($this->metrics, [
            'tiers' => $tierMetrics,
            'efficiency' => $this->calculateEfficiency(),
        ]);
    }

    public function isHealthy(): bool
    {
        // Healthy if at least one tier is healthy
        foreach ($this->tiers as $tier) {
            if ($tier->isHealthy()) {
                return true;
            }
        }

        return false;
    }

    public function getName(): string
    {
        return 'multi_tier';
    }

    /**
     * Backfill value to faster tiers
     */
    private function backfillTiers(string $key, mixed $value, int $foundAt): void
    {
        // Skip if found in fastest tier
        if ($foundAt === 0) {
            return;
        }

        // Backfill to all faster tiers
        for ($i = 0; $i < $foundAt; $i++) {
            if ($this->tiers[$i]->isHealthy()) {
                // Use shorter TTL for faster tiers
                $ttl = $this->calculateBackfillTtl($i, $foundAt);
                $this->tiers[$i]->put($key, $value, $ttl);
            }
        }
    }

    /**
     * Calculate appropriate TTL for backfilled data
     */
    private function calculateBackfillTtl(int $tierIndex, int $foundAt): int
    {
        // Faster tiers get shorter TTLs
        $baseTtl = 3600; // 1 hour
        $reduction = ($foundAt - $tierIndex) * 0.25;
        
        return (int) ($baseTtl * (1 - $reduction));
    }

    /**
     * Calculate cache efficiency metrics
     */
    private function calculateEfficiency(): array
    {
        $totalHits = array_sum($this->metrics['tier_hits']);
        
        if ($totalHits === 0) {
            return ['hit_rate' => 0, 'tier_distribution' => []];
        }

        $distribution = [];
        foreach ($this->metrics['tier_hits'] as $tier => $hits) {
            $distribution["tier_{$tier}"] = round(($hits / $totalHits) * 100, 2);
        }

        $hitRate = $totalHits / ($totalHits + $this->metrics['misses']) * 100;

        return [
            'hit_rate' => round($hitRate, 2),
            'tier_distribution' => $distribution,
        ];
    }
}