<?php
/**
 * HTML Render Strategy
 * 
 * Implements HTML rendering for vibes
 * 
 * @package ZipPicksVibes\Services
 * @since 2.0.0
 */

namespace ZipPicksVibes\Services;

use ZipPicksVibes\Models\Vibe;

class HtmlRenderStrategy implements RenderStrategyInterface {
    
    /**
     * Template base path
     * 
     * @var string
     */
    private string $templatePath;
    
    /**
     * Scrape protection service
     * 
     * @var ScrapeProtection
     */
    private ScrapeProtection $scrapeProtection;
    
    /**
     * Constructor
     * 
     * @param string $templatePath Base path for templates
     * @param ScrapeProtection $scrapeProtection
     */
    public function __construct(string $templatePath, ScrapeProtection $scrapeProtection) {
        $this->templatePath = rtrim($templatePath, '/');
        $this->scrapeProtection = $scrapeProtection;
    }
    
    /**
     * Render a list of vibes
     * 
     * @param Vibe[] $vibes Array of Vibe models
     * @param array $options Rendering options
     * @return string Rendered HTML
     */
    public function renderList(array $vibes, array $options = []): string {
        $defaults = [
            'columns' => 3,
            'show_count' => false,
            'container_class' => 'zippicks-vibes-grid',
            'item_class' => 'vibe-card',
            'obfuscate' => true
        ];
        
        $options = array_merge($defaults, $options);
        
        // Load template
        $template = $this->loadTemplate('partials/vibe-list.php');
        
        if (!$template) {
            return $this->renderFallbackList($vibes, $options);
        }
        
        // Execute template with data
        $watermarks = '';
        if ($this->scrapeProtection && method_exists($this->scrapeProtection, 'generate_watermarks')) {
            $watermarks = $this->scrapeProtection->generate_watermarks();
        }
        
        return $this->executeTemplate($template, [
            'vibes' => $vibes,
            'options' => $options,
            'watermarks' => $watermarks
        ]);
    }
    
    /**
     * Render a single vibe item
     * 
     * @param Vibe $vibe Vibe model
     * @param array $options Rendering options
     * @return string Rendered HTML
     */
    public function renderItem(Vibe $vibe, array $options = []): string {
        $defaults = [
            'show_count' => false,
            'show_description' => true,
            'item_class' => 'vibe-card',
            'obfuscate' => true
        ];
        
        $options = array_merge($defaults, $options);
        
        // Load template
        $template = $this->loadTemplate('partials/vibe-item.php');
        
        if (!$template) {
            return $this->renderFallbackItem($vibe, $options);
        }
        
        // Execute template with data
        return $this->executeTemplate($template, [
            'vibe' => $vibe,
            'options' => $options,
            'hash' => $this->generateHash($vibe->getId())
        ]);
    }
    
    /**
     * Render empty state
     * 
     * @param string $message Empty state message
     * @param array $options Rendering options
     * @return string Rendered HTML
     */
    public function renderEmptyState(string $message = '', array $options = []): string {
        if (empty($message)) {
            $message = __('No vibes found.', 'zippicks-vibes');
        }
        
        $defaults = [
            'container_class' => 'vibes-empty-state',
            'show_cta' => false,
            'cta_text' => __('Browse All Vibes', 'zippicks-vibes'),
            'cta_url' => home_url('/vibes/')
        ];
        
        $options = array_merge($defaults, $options);
        
        // Load template
        $template = $this->loadTemplate('partials/vibe-empty.php');
        
        if (!$template) {
            return $this->renderFallbackEmpty($message, $options);
        }
        
        // Execute template with data
        return $this->executeTemplate($template, [
            'message' => $message,
            'options' => $options
        ]);
    }
    
    /**
     * Get content type for this strategy
     * 
     * @return string
     */
    public function getContentType(): string {
        return 'text/html';
    }
    
    /**
     * Load template file
     * 
     * @param string $template Template file path relative to template directory
     * @return string|false Template path or false if not found
     */
    private function loadTemplate(string $template): string|false {
        $fullPath = $this->templatePath . '/' . $template;
        
        if (file_exists($fullPath)) {
            return $fullPath;
        }
        
        return false;
    }
    
    /**
     * Execute template with data
     * 
     * @param string $templatePath Full template path
     * @param array $data Data to pass to template
     * @return string Rendered output
     */
    private function executeTemplate(string $templatePath, array $data): string {
        // Extract data for template
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include template
        include $templatePath;
        
        // Get output
        return ob_get_clean();
    }
    
    /**
     * Render fallback list when template is missing
     * 
     * @param Vibe[] $vibes
     * @param array $options
     * @return string
     */
    private function renderFallbackList(array $vibes, array $options): string {
        $containerId = 'vibes-grid-' . wp_generate_password(8, false);
        $containerClass = esc_attr($options['container_class']) . ' zp-v-' . $this->generateHash($containerId);
        
        $html = sprintf(
            '<div id="%s" class="%s" data-columns="%d">',
            esc_attr($containerId),
            $containerClass,
            intval($options['columns'])
        );
        
        if ($options['obfuscate']) {
            // Render loading skeleton
            $html .= '<div class="vibes-loading">';
            for ($i = 0; $i < min(count($vibes), 6); $i++) {
                $html .= sprintf(
                    '<div class="vibe-skeleton zp-sk-%s">
                        <div class="skeleton-icon"></div>
                        <div class="skeleton-title"></div>
                        <div class="skeleton-desc"></div>
                    </div>',
                    $this->generateHash($i)
                );
            }
            $html .= '</div>';
            
            // Add hidden data for client-side rendering
            $html .= '<script type="application/json" class="vibes-data">';
            $html .= json_encode($this->obfuscateVibes($vibes));
            $html .= '</script>';
        } else {
            // Direct rendering (admin or authenticated context)
            $html .= '<div class="vibes-grid-inner">';
            foreach ($vibes as $vibe) {
                $html .= $this->renderFallbackItem($vibe, $options);
            }
            $html .= '</div>';
        }
        
        // Add watermarks
        if ($options['obfuscate'] && $this->scrapeProtection && method_exists($this->scrapeProtection, 'generate_watermarks')) {
            $html .= $this->scrapeProtection->generate_watermarks();
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render fallback item when template is missing
     * 
     * @param Vibe $vibe
     * @param array $options
     * @return string
     */
    private function renderFallbackItem(Vibe $vibe, array $options): string {
        $itemClass = esc_attr($options['item_class']) . ' zp-v-' . $this->generateHash($vibe->getId());
        
        $html = sprintf(
            '<div class="%s" data-vibe="%s">',
            $itemClass,
            $options['obfuscate'] ? $this->obfuscateId($vibe->getId()) : $vibe->getId()
        );
        
        // Icon
        $html .= sprintf(
            '<div class="vibe-icon">
                <img src="%s" alt="%s" loading="lazy">
            </div>',
            esc_url($vibe->getIconUrl()),
            esc_attr($vibe->getName())
        );
        
        // Name
        $html .= sprintf(
            '<h3 class="vibe-name">%s</h3>',
            esc_html($vibe->getName())
        );
        
        // Description
        if ($options['show_description'] && $vibe->getDescription()) {
            $html .= sprintf(
                '<p class="vibe-desc">%s</p>',
                esc_html($vibe->getDescription())
            );
        }
        
        // Business count
        if ($options['show_count'] && $vibe->getBusinessCount() > 0) {
            $html .= sprintf(
                '<span class="vibe-count">%d %s</span>',
                $vibe->getBusinessCount(),
                _n('business', 'businesses', $vibe->getBusinessCount(), 'zippicks-vibes')
            );
        }
        
        // Hidden fingerprint
        if ($options['obfuscate']) {
            $html .= sprintf(
                '<span class="zp-fp" data-hash="%s" style="display:none;"></span>',
                $this->generateHash($vibe->getId())
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render fallback empty state when template is missing
     * 
     * @param string $message
     * @param array $options
     * @return string
     */
    private function renderFallbackEmpty(string $message, array $options): string {
        $html = sprintf(
            '<div class="%s">',
            esc_attr($options['container_class'])
        );
        
        $html .= sprintf('<p>%s</p>', esc_html($message));
        
        if ($options['show_cta']) {
            $html .= sprintf(
                '<a href="%s" class="button">%s</a>',
                esc_url($options['cta_url']),
                esc_html($options['cta_text'])
            );
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Obfuscate vibes for client-side rendering
     * 
     * @param Vibe[] $vibes
     * @return array
     */
    private function obfuscateVibes(array $vibes): array {
        $obfuscated = [];
        
        foreach ($vibes as $vibe) {
            $obfuscated[] = [
                'id' => $this->obfuscateId($vibe->getId()),
                'n' => base64_encode($vibe->getName()),
                'd' => base64_encode($vibe->getDescription()),
                'i' => $vibe->getIcon(),
                'c' => $vibe->getColor(),
                'u' => base64_encode($vibe->getUrl()),
                'bc' => $vibe->getBusinessCount(),
                'h' => $this->generateHash($vibe->getId())
            ];
        }
        
        return $obfuscated;
    }
    
    /**
     * Generate hash for fingerprinting
     * 
     * @param mixed $input
     * @return string
     */
    private function generateHash($input): string {
        return substr(md5($input . wp_salt()), 0, 6);
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
}