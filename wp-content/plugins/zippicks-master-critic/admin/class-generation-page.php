<?php
/**
 * Generation page class
 *
 * @package ZipPicks_Master_Critic
 */

require_once plugin_dir_path(__FILE__) . '/../includes/ScoringEngine.php';
use ZP_MasterCritic_ScoringEngine;

class ZipPicks_Master_Critic_Generation_Page {
    
    /**
     * AI Service
     *
     * @var ZipPicks_Master_Critic_AI_Service
     */
    private $ai_service;
    
    /**
     * Constructor
     *
     * @param ZipPicks_Master_Critic_AI_Service $ai_service
     */
    public function __construct($ai_service) {
        $this->ai_service = $ai_service;
    }
    
    /**
     * Render the page
     */
    public function render() {
        $categories = get_option('zippicks_business_categories');
        $default_provider = get_option('zippicks_default_ai_provider', 'anthropic');
        $api_limits = $this->ai_service->get_remaining_calls();
        ?>
        <div class="wrap zippicks-master-critic-wrap">
            <h1>
                <span class="dashicons dashicons-star-filled"></span>
                ZipPicks Master Critic AI Generator
            </h1>
            
            <noscript>
                <div class="notice notice-error">
                    <p><strong>JavaScript is disabled!</strong> The Master Critic AI Generator requires JavaScript to function properly. Please enable JavaScript in your browser settings and reload this page.</p>
                </div>
            </noscript>
            
            <?php if (isset($_GET['tables-created'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>Database tables created successfully!</p>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error']) && $_GET['error'] === 'js-required'): ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>JavaScript Required:</strong> This page requires JavaScript to be enabled. Please enable JavaScript in your browser and try again.</p>
            </div>
            <?php endif; ?>
            
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <div class="notice notice-info">
                <p>Debug Info: Hook = <?php echo esc_html($GLOBALS['hook_suffix'] ?? 'unknown'); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="master-critic-container">
                <div class="main-content">
                    <form id="master-critic-form" class="master-critic-form" method="post" action="">
                        <?php wp_nonce_field('zippicks_master_critic_nonce', 'nonce'); ?>
                        
                        <div class="form-section">
                            <h2>Business Ranking Configuration</h2>
                            
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="business_category">Business Category</label>
                                    <select id="business_category" name="business_category" required>
                                        <option value="">Select a category...</option>
                                        <?php foreach ($categories as $key => $category): ?>
                                        <option value="<?php echo esc_attr($key); ?>">
                                            <?php echo esc_html($category['label']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-field">
                                    <label for="topic">Topic</label>
                                    <input type="text" 
                                           id="topic" 
                                           name="topic" 
                                           placeholder="e.g., tacos, luxury hotels, craft cocktails, day spas"
                                           required />
                                </div>
                                
                                <div class="form-field">
                                    <label for="location">Location</label>
                                    <input type="text" 
                                           id="location" 
                                           name="location" 
                                           placeholder="e.g., Los Angeles, NYC, Chicago, 90210"
                                           required />
                                </div>
                                
                                <div class="form-field">
                                    <label for="list_category">Top 10 Category</label>
                                    <select id="list_category" name="list_category" required>
                                        <option value="">Select a Top 10 category...</option>
                                        <?php 
                                        // Load category handler
                                        require_once ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-category-handler.php';
                                        $top10_categories = ZipPicks_Master_Critic_Category_Handler::get_categories_by_priority();
                                        foreach ($top10_categories as $key => $cat_info): 
                                        ?>
                                        <option value="<?php echo esc_attr($key); ?>">
                                            <?php echo esc_html($cat_info['name']); ?> - <?php echo esc_html($cat_info['description']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Choose the type of Top 10 list to generate</p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="search_type">Search Type</label>
                                    <select id="search_type" name="search_type" required>
                                        <option value="specific">Specific Item/Service</option>
                                        <option value="business">Business Type</option>
                                        <option value="experience">Experience/Vibe</option>
                                    </select>
                                </div>
                                
                                <div class="form-field">
                                    <label for="ai_provider">AI Provider</label>
                                    <select id="ai_provider" name="ai_provider" required>
                                        <option value="anthropic" <?php selected($default_provider, 'anthropic'); ?>>
                                            Claude (Anthropic)
                                        </option>
                                        <option value="openai" <?php selected($default_provider, 'openai'); ?>>
                                            GPT-4 (OpenAI)
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="form-field checkbox-field">
                                    <label>
                                        <input type="checkbox" id="test_mode" name="test_mode" value="1" />
                                        Show prompt only (do not call AI)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="button button-primary button-large" id="generate-btn">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    Generate Prompt
                                </button>
                            </div>
                        </div>
                        
                        <div id="prompt-section" class="form-section" style="display: none;">
                            <h2>Generated Prompt</h2>
                            <p class="description">Customize this prompt for your specific business ranking needs</p>
                            
                            <div class="prompt-container">
                                <textarea id="generated-prompt" 
                                          name="generated_prompt" 
                                          rows="15" 
                                          readonly
                                          class="large-text code"></textarea>
                                
                                <div class="prompt-actions">
                                    <button type="button" class="button" id="enable-editing">
                                        <span class="dashicons dashicons-edit"></span>
                                        Enable Editing
                                    </button>
                                    <button type="button" class="button" id="reset-prompt" style="display: none;">
                                        <span class="dashicons dashicons-image-rotate"></span>
                                        Reset to Generated
                                    </button>
                                    <button type="button" class="button button-primary" id="execute-prompt">
                                        <span class="dashicons dashicons-controls-play"></span>
                                        Execute AI Generation
                                    </button>
                                    <button type="button" class="button" id="save-template">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        Save as Template
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="results-section" class="form-section" style="display: none;">
                            <h2>AI Response</h2>
                            <div class="results-meta">
                                <span class="provider-badge"></span>
                                <span class="cache-badge" style="display: none;">Cached Result</span>
                            </div>
                            
                            <div id="ai-response" class="ai-response"></div>
                            
                            <div class="results-actions" style="display: none;">
                                <button type="button" class="button button-primary" id="create-businesses">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    Create Business Pages
                                </button>
                                <button type="button" class="button" id="create-list">
                                    <span class="dashicons dashicons-list-view"></span>
                                    Create List Page
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="sidebar">
                    <div class="widget api-status">
                        <h3>API Usage</h3>
                        <div class="usage-stats">
                            <div class="stat">
                                <span class="label">This Minute:</span>
                                <span class="value">
                                    <?php echo $api_limits['minute']['used']; ?> / <?php echo $api_limits['minute']['limit']; ?>
                                </span>
                            </div>
                            <div class="stat">
                                <span class="label">Today:</span>
                                <span class="value">
                                    <?php echo $api_limits['day']['used']; ?> / <?php echo $api_limits['day']['limit']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="widget tips">
                        <h3>Tips</h3>
                        <ul>
                            <li>Be specific with your topic for better results</li>
                            <li>Include ZIP codes for hyper-local results</li>
                            <li>Use "Experience/Vibe" for mood-based searches</li>
                            <li>Save successful prompts as templates for reuse</li>
                        </ul>
                    </div>
                    
                    <div class="widget category-info" style="display: none;">
                        <h3>Category Pillars</h3>
                        <div id="category-pillars"></div>
                    </div>
                </div>
            </div>
            
            <!-- Save Template Modal -->
            <div id="save-template-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <h3>Save Prompt Template</h3>
                    <form id="save-template-form">
                        <div class="form-field">
                            <label for="template_name">Template Name</label>
                            <input type="text" id="template_name" name="template_name" required />
                        </div>
                        <div class="form-field">
                            <label>
                                <input type="checkbox" name="is_default" value="1" />
                                Make this the default template for this category
                            </label>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="button button-primary">Save Template</button>
                            <button type="button" class="button cancel-modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}