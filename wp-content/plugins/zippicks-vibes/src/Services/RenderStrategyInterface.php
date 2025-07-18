<?php
/**
 * Render Strategy Interface
 * 
 * Defines contract for different rendering strategies
 * 
 * @package ZipPicksVibes\Services
 * @since 2.0.0
 */

namespace ZipPicksVibes\Services;

use ZipPicksVibes\Models\Vibe;

interface RenderStrategyInterface {
    /**
     * Render a list of vibes
     * 
     * @param Vibe[] $vibes Array of Vibe models
     * @param array $options Rendering options
     * @return string Rendered output
     */
    public function renderList(array $vibes, array $options = []): string;
    
    /**
     * Render a single vibe item
     * 
     * @param Vibe $vibe Vibe model
     * @param array $options Rendering options
     * @return string Rendered output
     */
    public function renderItem(Vibe $vibe, array $options = []): string;
    
    /**
     * Render empty state
     * 
     * @param string $message Empty state message
     * @param array $options Rendering options
     * @return string Rendered output
     */
    public function renderEmptyState(string $message = '', array $options = []): string;
    
    /**
     * Get content type for this strategy
     * 
     * @return string Content type (e.g., 'text/html', 'application/json')
     */
    public function getContentType(): string;
}