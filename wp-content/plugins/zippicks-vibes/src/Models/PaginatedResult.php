<?php
/**
 * Paginated Result Model
 * 
 * Represents a paginated result set with metadata
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Models;

/**
 * Class PaginatedResult
 */
class PaginatedResult {
    
    /**
     * Result items
     * 
     * @var array
     */
    private array $items;
    
    /**
     * Total number of items
     * 
     * @var int
     */
    private int $total;
    
    /**
     * Items per page
     * 
     * @var int
     */
    private int $perPage;
    
    /**
     * Current page
     * 
     * @var int
     */
    private int $currentPage;
    
    /**
     * Total pages
     * 
     * @var int
     */
    private int $totalPages;
    
    /**
     * Offset
     * 
     * @var int
     */
    private int $offset;
    
    /**
     * Additional metadata
     * 
     * @var array
     */
    private array $meta;
    
    /**
     * Constructor
     * 
     * @param array $items Result items
     * @param int $total Total count
     * @param int $perPage Items per page
     * @param int $currentPage Current page number
     * @param array $meta Additional metadata
     */
    public function __construct(
        array $items, 
        int $total, 
        int $perPage, 
        int $currentPage, 
        array $meta = []
    ) {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->totalPages = (int) ceil($total / $this->perPage);
        $this->offset = ($this->currentPage - 1) * $this->perPage;
        $this->meta = $meta;
    }
    
    /**
     * Get items
     * 
     * @return array
     */
    public function getItems(): array {
        return $this->items;
    }
    
    /**
     * Get total count
     * 
     * @return int
     */
    public function getTotal(): int {
        return $this->total;
    }
    
    /**
     * Get items per page
     * 
     * @return int
     */
    public function getPerPage(): int {
        return $this->perPage;
    }
    
    /**
     * Get current page
     * 
     * @return int
     */
    public function getCurrentPage(): int {
        return $this->currentPage;
    }
    
    /**
     * Get total pages
     * 
     * @return int
     */
    public function getTotalPages(): int {
        return $this->totalPages;
    }
    
    /**
     * Get offset
     * 
     * @return int
     */
    public function getOffset(): int {
        return $this->offset;
    }
    
    /**
     * Check if has previous page
     * 
     * @return bool
     */
    public function hasPreviousPage(): bool {
        return $this->currentPage > 1;
    }
    
    /**
     * Check if has next page
     * 
     * @return bool
     */
    public function hasNextPage(): bool {
        return $this->currentPage < $this->totalPages;
    }
    
    /**
     * Get previous page number
     * 
     * @return int|null
     */
    public function getPreviousPage(): ?int {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }
    
    /**
     * Get next page number
     * 
     * @return int|null
     */
    public function getNextPage(): ?int {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }
    
    /**
     * Get from item number
     * 
     * @return int
     */
    public function getFrom(): int {
        if ($this->total === 0) {
            return 0;
        }
        
        return $this->offset + 1;
    }
    
    /**
     * Get to item number
     * 
     * @return int
     */
    public function getTo(): int {
        if ($this->total === 0) {
            return 0;
        }
        
        return min($this->offset + $this->perPage, $this->total);
    }
    
    /**
     * Get metadata
     * 
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public function getMeta(?string $key = null, $default = null) {
        if ($key === null) {
            return $this->meta;
        }
        
        return $this->meta[$key] ?? $default;
    }
    
    /**
     * Set metadata
     * 
     * @param string $key
     * @param mixed $value
     */
    public function setMeta(string $key, $value): void {
        $this->meta[$key] = $value;
    }
    
    /**
     * Convert to array
     * 
     * @return array
     */
    public function toArray(): array {
        return [
            'items' => $this->items,
            'pagination' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'total_pages' => $this->totalPages,
                'from' => $this->getFrom(),
                'to' => $this->getTo(),
                'has_previous' => $this->hasPreviousPage(),
                'has_next' => $this->hasNextPage(),
                'previous_page' => $this->getPreviousPage(),
                'next_page' => $this->getNextPage()
            ],
            'meta' => $this->meta
        ];
    }
    
    /**
     * Convert to JSON
     * 
     * @return string
     */
    public function toJson(): string {
        return json_encode($this->toArray());
    }
    
    /**
     * Get page numbers for pagination display
     * 
     * @param int $delta Number of pages to show on each side
     * @return array
     */
    public function getPageNumbers(int $delta = 2): array {
        $pages = [];
        
        // Always include first page
        $pages[] = 1;
        
        // Calculate range around current page
        $rangeStart = max(2, $this->currentPage - $delta);
        $rangeEnd = min($this->totalPages - 1, $this->currentPage + $delta);
        
        // Add ellipsis if needed
        if ($rangeStart > 2) {
            $pages[] = '...';
        }
        
        // Add pages in range
        for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
            $pages[] = $i;
        }
        
        // Add ellipsis if needed
        if ($rangeEnd < $this->totalPages - 1) {
            $pages[] = '...';
        }
        
        // Always include last page if more than one page
        if ($this->totalPages > 1) {
            $pages[] = $this->totalPages;
        }
        
        return $pages;
    }
    
    /**
     * Check if empty
     * 
     * @return bool
     */
    public function isEmpty(): bool {
        return empty($this->items);
    }
    
    /**
     * Count items on current page
     * 
     * @return int
     */
    public function count(): int {
        return count($this->items);
    }
}