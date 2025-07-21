<?php
/**
 * Vibe Renderer Service
 * 
 * Handles client-side rendering to prevent scraping
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Services;

use ZipPicksVibes\Models\Vibe;

/**
 * Class VibeRenderer
 */
class VibeRenderer {
    
    /**
     * Vibe service instance
     * 
     * @var VibeService
     */
    private VibeService $vibeService;
    
    /**
     * Scrape protection instance
     * 
     * @var ScrapeProtection
     */
    private ScrapeProtection $scrapeProtection;
    
    /**
     * Current render strategy
     * 
     * @var RenderStrategyInterface
     */
    private RenderStrategyInterface $renderStrategy;
    
    /**
     * Template base path
     * 
     * @var string
     */
    private string $templatePath;
    
    /**
     * Constructor
     * 
     * @param VibeService $vibeService
     * @param ScrapeProtection $scrapeProtection
     */
    public function __construct(VibeService $vibeService, ScrapeProtection $scrapeProtection) {
        $this->vibeService = $vibeService;
        $this->scrapeProtection = $scrapeProtection;
        $this->templatePath = ZIPPICKS_VIBES_DIR . 'templates';
        
        // Set default render strategy
        $this->setRenderStrategy(new HtmlRenderStrategy($this->templatePath, $scrapeProtection));
    }
    
    /**
     * Set render strategy
     * 
     * @param RenderStrategyInterface $strategy
     * @return self
     */
    public function setRenderStrategy(RenderStrategyInterface $strategy): self {
        $this->renderStrategy = $strategy;
        return $this;
    }
    
    /**
     * Render preview for non-authenticated users
     * 
     * @return void
     */
    public function render_preview(): void {
        // Check if user has access
        if (!$this->shouldShowPreview()) {
            wp_redirect(home_url());
            exit;
        }
        
        // Get header
        get_header();
        
        // Load preview template
        $template = $this->get_template('preview.php');
        
        if ($template) {
            include $template;
        } else {
            // Fallback preview
            $this->renderFallbackPreview();
        }
        
        // Get footer
        get_footer();
    }
    
    /**
     * Secure content output
     * 
     * @param string $content
     * @return string
     */
    public function secure_content(string $content): string {
        // Remove any vibe-specific data from content
        $content = preg_replace('/<div[^>]*class="[^"]*vibe-data[^"]*"[^>]*>.*?<\/div>/is', '', $content);
        
        // Add loading placeholders
        $content = str_replace(
            '<div class="vibe-list">',
            '<div class="vibe-list" data-render="client">',
            $content
        );
        
        // Inject watermarks
        if ($this->scrapeProtection && method_exists($this->scrapeProtection, 'generate_watermarks')) {
            $watermarks = $this->scrapeProtection->generate_watermarks();
            $content .= $watermarks;
        }
        
        return $content;
    }
    
    /**
     * Render vibes grid
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_vibes_grid(array $atts): string {
        try {
            // Parse attributes
            $defaults = [
                'category' => '',
                'limit' => 10,
                'columns' => 3,
                'show_count' => false,
                'show_empty' => true,
                'order_by' => 'order_position',
                'order' => 'ASC'
            ];
            
            $atts = array_merge($defaults, $atts);
            
            // Validate limit
            $limit = max(1, min(50, intval($atts['limit'])));
            
            // Build query args
            $args = [
                'status' => 'active',
                'orderby' => $atts['order_by'],
                'order' => $atts['order'],
                'limit' => $limit
            ];
            
            if (!empty($atts['category'])) {
                $args['category'] = intval($atts['category']);
            }
            
            // Get vibes
            $vibes = $this->vibeService->getAllVibes($args);
            
            // Check if empty
            if (empty($vibes) && !$atts['show_empty']) {
                return '';
            }
            
            // Prepare rendering options
            $renderOptions = [
                'columns' => intval($atts['columns']),
                'show_count' => filter_var($atts['show_count'], FILTER_VALIDATE_BOOLEAN),
                'container_class' => 'zippicks-vibes-grid',
                'item_class' => 'vibe-card',
                'obfuscate' => !is_user_logged_in() || !current_user_can('edit_posts')
            ];
            
            // Render based on result
            if (empty($vibes)) {
                return $this->renderStrategy->renderEmptyState(
                    __('No vibes found matching your criteria.', 'zippicks-vibes'),
                    ['show_cta' => true]
                );
            }
            
            return $this->renderStrategy->renderList($vibes, $renderOptions);
        } catch (\Exception $e) {
            // Log error
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                zippicks()->get('logger')->error('Failed to render vibes grid', [
                    'error' => $e->getMessage(),
                    'atts' => $atts
                ]);
            }
            
            // Return error for admins, empty for others
            if (current_user_can('manage_options')) {
                return sprintf(
                    '<div class="vibes-error">%s</div>',
                    esc_html__('Error loading vibes. Please check the logs.', 'zippicks-vibes')
                );
            }
            
            return '';
        }
    }
    
    /**
     * Render search form
     * 
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render_search_form(array $atts): string {
        $defaults = [
            'placeholder' => __('Search vibes...', 'zippicks-vibes'),
            'ajax' => true,
            'min_chars' => 2,
            'delay' => 300
        ];
        
        $atts = array_merge($defaults, $atts);
        
        // Load search form template
        $template = $this->get_template('partials/search-form.php');
        
        if ($template) {
            ob_start();
            include $template;
            return ob_get_clean();
        }
        
        // Fallback search form
        return $this->renderFallbackSearchForm($atts);
    }
    
    /**
     * Get vibe template
     * 
     * @param string $template Template file name
     * @param array $data Data to pass to template
     * @return string
     */
    public function get_vibe_template(string $template, array $data = []): string {
        $template_file = $this->get_template($template);
        
        if (!$template_file) {
            return '';
        }
        
        // Extract data for template
        extract($data);
        
        ob_start();
        include $template_file;
        return ob_get_clean();
    }
    
    /**
     * Render single vibe
     * 
     * @param int|string $vibe_id Vibe ID or slug
     * @param array $options Rendering options
     * @return string
     */
    public function render_single_vibe($vibe_id, array $options = []): string {
        try {
            // Get vibe
            if (is_numeric($vibe_id)) {
                $vibe = $this->vibeService->getVibe((int)$vibe_id);
            } else {
                $vibe = $this->vibeService->getVibeBySlug($vibe_id);
            }
            
            // Default options
            $defaults = [
                'show_count' => true,
                'show_description' => true,
                'obfuscate' => !is_user_logged_in()
            ];
            
            $options = array_merge($defaults, $options);
            
            return $this->renderStrategy->renderItem($vibe, $options);
        } catch (\Exception $e) {
            // Log error
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                zippicks()->get('logger')->error('Failed to render single vibe', [
                    'vibe_id' => $vibe_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            return '';
        }
    }
    
    /**
     * Render vibes for API response
     * 
     * @param array $vibes Array of Vibe models
     * @param array $options Options including pagination data
     * @return array
     */
    public function render_api_response(array $vibes, array $options = []): array {
        // Switch to JSON strategy
        $originalStrategy = $this->renderStrategy;
        $this->setRenderStrategy(new JsonRenderStrategy(!empty($options['secure'])));
        
        // Get JSON output
        $json = $this->renderStrategy->renderList($vibes, $options);
        
        // Restore original strategy
        $this->renderStrategy = $originalStrategy;
        
        // Decode and return as array
        return json_decode($json, true);
    }
    
    /**
     * Render vibe list
     * 
     * @param array $vibes Array of vibes to render
     * @return void
     */
    public function renderVibeList(array $vibes): void {
        echo $this->renderStrategy->renderList($vibes);
    }
    
    /**
     * Render vibes as JSON
     * 
     * @param array $vibes Array of vibes to render
     * @return void
     */
    public function renderJson(array $vibes): void {
        // Set JSON strategy if not already set
        $originalStrategy = $this->renderStrategy;
        $isJsonStrategy = $this->renderStrategy instanceof JsonRenderStrategy;
        
        if (!$isJsonStrategy) {
            $this->setRenderStrategy(new JsonRenderStrategy(true));
        }
        
        // Get JSON output
        $json = $this->renderStrategy->renderJson($vibes);
        
        // Restore original strategy if it was changed
        if (!$isJsonStrategy) {
            $this->renderStrategy = $originalStrategy;
        }
        
        // Send JSON response
        wp_send_json_success(json_decode($json, true));
    }
    
    /**
     * Check if should show preview
     * 
     * @return bool
     */
    private function shouldShowPreview(): bool {
        return is_user_logged_in() || current_user_can('read');
    }
    
    /**
     * Get template file path
     * 
     * @param string $template Template file name
     * @return string|false
     */
    private function get_template(string $template): string|false {
        $paths = [
            // Theme override
            get_stylesheet_directory() . '/zippicks-vibes/' . $template,
            get_template_directory() . '/zippicks-vibes/' . $template,
            // Plugin default
            $this->templatePath . '/' . $template
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Render fallback preview
     * 
     * @return void
     */
    private function renderFallbackPreview(): void {
        ?>
        <div class="zippicks-vibes-preview">
            <div class="container">
                <h1><?php _e('Discover Amazing Vibes', 'zippicks-vibes'); ?></h1>
                <p><?php _e('Sign in to explore our full collection of curated vibes and discover your perfect matches.', 'zippicks-vibes'); ?></p>
                
                <div class="preview-cards">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <div class="preview-card zp-v-<?php echo $this->generateHash($i); ?>" data-preview="true">
                        <div class="skeleton-loader">
                            <div class="skeleton-icon"></div>
                            <div class="skeleton-text"></div>
                            <div class="skeleton-text short"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                
                <div class="cta-section">
                    <a href="<?php echo wp_login_url(get_permalink()); ?>" class="btn btn-primary">
                        <?php _e('Sign In to Continue', 'zippicks-vibes'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render fallback search form
     * 
     * @param array $atts
     * @return string
     */
    private function renderFallbackSearchForm(array $atts): string {
        $form_id = 'vibes-search-' . wp_generate_password(6, false);
        
        ob_start();
        ?>
        <div class="zippicks-vibes-search-wrapper">
            <form id="<?php echo esc_attr($form_id); ?>" 
                  class="vibes-search-form zp-sf-<?php echo $this->generateHash($form_id); ?>"
                  method="get"
                  action="<?php echo esc_url(home_url('/vibes/')); ?>">
                
                <div class="search-input-wrapper">
                    <input type="text" 
                           name="vibe_search" 
                           class="vibes-search-input"
                           placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                           autocomplete="off"
                           data-ajax="<?php echo $atts['ajax'] ? 'true' : 'false'; ?>"
                           data-min-chars="<?php echo esc_attr($atts['min_chars']); ?>"
                           data-delay="<?php echo esc_attr($atts['delay']); ?>">
                    
                    <button type="submit" class="vibes-search-submit">
                        <span class="screen-reader-text"><?php _e('Search', 'zippicks-vibes'); ?></span>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M19 19l-4.35-4.35m0 0A7.5 7.5 0 1 0 4.35 4.35a7.5 7.5 0 0 0 10.3 10.3z" 
                                  stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
                
                <div class="vibes-search-results" style="display:none;"></div>
                
                <?php wp_nonce_field('vibes_search', 'vibes_search_nonce'); ?>
            </form>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Generate hash
     * 
     * @param mixed $input
     * @return string
     */
    private function generateHash($input): string {
        return substr(md5($input . wp_salt()), 0, 6);
    }
}