<?php
/**
 * Single template for Master Critic Lists
 * Production-ready with SEO optimization, waitlist, and ZipPicks styling
 *
 * @package ZipPicks_Master_Critic
 */

defined('ABSPATH') || exit;

get_header();

while (have_posts()) : the_post();
    // Get meta data
    $topic = get_post_meta(get_the_ID(), '_mc_topic', true);
    $location = get_post_meta(get_the_ID(), '_mc_location', true);
    $restaurants_json = get_post_meta(get_the_ID(), '_mc_restaurants', true);
    $restaurants = $restaurants_json ? json_decode($restaurants_json, true) : [];
    $list_category = get_post_meta(get_the_ID(), '_mc_list_category', true) ?: 'best_overall';
    
    // Check if list has data
    $has_data = !empty($restaurants) && is_array($restaurants);
    
    // Generate schema.org markup if we have data
    if ($has_data && class_exists('ZipPicks_Master_Critic_Schema_Generator')) {
        $schema = ZipPicks_Master_Critic_Schema_Generator::generate_top10_schema(get_the_ID());
        if ($schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>';
        }
    }
    ?>
    
    <!-- Full-width wrapper to match ZipPicks styling -->
    <div class="zp-top10-wrapper">
        
        <!-- Hero Section with Premium Gradient -->
        <div class="zp-top10-hero" style="background: linear-gradient(135deg, #194FAD 0%, #5A9BFF 100%); position: relative; overflow: hidden;">
            <!-- Subtle texture overlay -->
            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.1) 0%, transparent 50%), radial-gradient(circle at 80% 80%, rgba(255,255,255,0.05) 0%, transparent 50%); pointer-events: none;"></div>
            
            <div class="zp-hero-content">
                <h1 class="zp-top10-hero__title">
                    <?php 
                    if ($topic && $location) {
                        echo esc_html("Top 10 {$topic} in {$location}");
                    } else {
                        the_title();
                    }
                    ?>
                </h1>
                <p class="zp-top10-hero__subtitle">Curated by ZipPicks Master Critic</p>
                <div class="zp-top10-meta">
                    <span class="posted-on">Updated <?php echo human_time_diff(get_the_modified_time('U'), current_time('timestamp')) . ' ago'; ?></span>
                </div>
            </div>
        </div>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class('zp-top10-single'); ?>>
        <header class="entry-header">
            <h1 class="entry-title">
                <?php 
                if ($topic && $location) {
                    echo esc_html("Top 10 {$topic} in {$location}");
                } else {
                    the_title();
                }
                ?>
            </h1>
        </header>

        <!-- Main Content Section -->
        <div class="zp-top10-section">
            <div class="zp-container zp-container--wide">
                <?php if ($has_data) : ?>
                    <div class="zp-top10-list">
                        <?php foreach ($restaurants as $index => $restaurant) : 
                            $rank = $restaurant['rank'] ?? ($index + 1);
                            ?>
                            <div class="zp-restaurant-card" id="restaurant-<?php echo $rank; ?>" itemscope itemtype="https://schema.org/Restaurant">
                                <div class="zp-restaurant-header">
                                    <span class="zp-rank" aria-label="Rank <?php echo $rank; ?>"><?php echo $rank; ?></span>
                                    <div class="zp-restaurant-title-wrapper">
                                        <h2 class="zp-restaurant-name" itemprop="name"><?php echo esc_html($restaurant['name']); ?></h2>
                                        <?php if (!empty($restaurant['address'])) : ?>
                                            <address class="zp-restaurant-address" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                                                <span itemprop="streetAddress"><?php echo esc_html($restaurant['address']); ?></span>
                                            </address>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($restaurant['score'])) : ?>
                                        <div class="zp-score-badge">
                                            <span class="zp-score-value"><?php echo number_format($restaurant['score'], 1); ?></span>
                                            <span class="zp-score-label">Score</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            
                                <?php if (!empty($restaurant['summary'])) : ?>
                                    <p class="zp-restaurant-summary" itemprop="description"><?php echo esc_html($restaurant['summary']); ?></p>
                                <?php endif; ?>
                                
                                <!-- Verified Sources Badge -->
                                <?php if (!empty($restaurant['review_count']) && $restaurant['review_count'] > 0) : ?>
                                    <div class="zp-verified-sources">
                                        <span class="zp-verified-icon">✓</span>
                                        <span class="zp-verified-text">Verified from <?php echo number_format($restaurant['review_count']); ?> sources</span>
                                    </div>
                                <?php endif; ?>
                            
                                <div class="zp-restaurant-meta">
                                    <?php if (!empty($restaurant['price_tier'])) : ?>
                                        <span class="zp-price-tier" itemprop="priceRange"><?php echo esc_html($restaurant['price_tier']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($restaurant['cuisine'])) : ?>
                                        <span class="zp-cuisine" itemprop="servesCuisine"><?php echo esc_html($restaurant['cuisine']); ?></span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($restaurant['neighborhood'])) : ?>
                                        <span class="zp-neighborhood"><?php echo esc_html($restaurant['neighborhood']); ?></span>
                                    <?php endif; ?>
                                </div>
                            
                                <?php if (!empty($restaurant['top_dishes']) && is_array($restaurant['top_dishes'])) : ?>
                                    <div class="zp-top-dishes">
                                        <h3 class="zp-dishes-label">Must Try</h3>
                                        <div class="zp-dishes-list">
                                            <?php foreach (array_slice($restaurant['top_dishes'], 0, 3) as $dish) : ?>
                                                <span class="zp-dish-item"><?php echo esc_html($dish); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            
                                <?php if (!empty($restaurant['pillar_scores']) && is_array($restaurant['pillar_scores'])) : ?>
                                    <div class="zp-pillar-scores">
                                        <?php 
                                        $pillars = [
                                            'taste' => ['label' => 'Taste', 'icon' => '🍽️'],
                                            'service' => ['label' => 'Service', 'icon' => '⭐'],
                                            'ambiance' => ['label' => 'Ambiance', 'icon' => '✨'],
                                            'value' => ['label' => 'Value', 'icon' => '💰']
                                        ];
                                        foreach ($pillars as $key => $pillar_info) :
                                            if (isset($restaurant['pillar_scores'][$key])) :
                                                $score = $restaurant['pillar_scores'][$key];
                                                ?>
                                                <div class="zp-pillar-item">
                                                    <span class="zp-pillar-icon"><?php echo $pillar_info['icon']; ?></span>
                                                    <span class="zp-pillar-name"><?php echo esc_html($pillar_info['label']); ?></span>
                                                    <div class="zp-pillar-bar">
                                                        <div class="zp-pillar-fill" style="width: <?php echo ($score * 10); ?>%;"></div>
                                                    </div>
                                                    <span class="zp-pillar-value"><?php echo number_format($score, 1); ?></span>
                                                </div>
                                            <?php endif;
                                        endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            
                                <?php if (!empty($restaurant['vibes']) && is_array($restaurant['vibes'])) : ?>
                                    <div class="zp-restaurant-vibes">
                                        <?php foreach ($restaurant['vibes'] as $vibe) : ?>
                                            <a href="<?php echo esc_url(home_url('/vibes/?vibe=' . sanitize_title($vibe))); ?>" class="zp-vibe-tag"><?php echo esc_html($vibe); ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <!-- Coming Soon State with Waitlist -->
                    <div class="zp-coming-soon-wrapper">
                        <div class="zp-coming-soon-card">
                            <div class="zp-coming-soon-icon">🚀</div>
                            <h2 class="zp-coming-soon-title">This List is Coming Soon!</h2>
                            <p class="zp-coming-soon-text">
                                Our Master Critic is currently researching and curating the 
                                <?php echo $topic ? "best {$topic}" : 'top spots'; ?> 
                                <?php echo $location ? "in {$location}" : 'in your area'; ?>.
                            </p>
                            
                            <!-- Waitlist Form -->
                            <div class="zp-waitlist-form-wrapper">
                                <h3 class="zp-waitlist-title">Get Notified When It's Ready</h3>
                                <p class="zp-waitlist-subtitle">Join the waitlist and be the first to know!</p>
                                
                                <form id="zp-top10-waitlist-form" class="zp-waitlist-form">
                                    <div class="zp-form-row">
                                        <div class="zp-form-group">
                                            <label for="waitlist-email">Email Address</label>
                                            <input type="email" id="waitlist-email" name="email" required 
                                                   placeholder="your@email.com" class="zp-form-input">
                                        </div>
                                        
                                        <div class="zp-form-group">
                                            <label for="waitlist-zip">ZIP Code</label>
                                            <input type="text" id="waitlist-zip" name="zip_code" required 
                                                   pattern="[0-9]{5}" placeholder="12345" class="zp-form-input" 
                                                   maxlength="5">
                                        </div>
                                    </div>
                                    
                                    <input type="hidden" name="city" value="<?php echo esc_attr($location); ?>">
                                    <input type="hidden" name="category" value="<?php echo esc_attr($topic); ?>">
                                    <input type="hidden" name="list_id" value="<?php echo get_the_ID(); ?>">
                                    <?php wp_nonce_field('zp_waitlist_nonce', 'waitlist_nonce'); ?>
                                    
                                    <button type="submit" class="zp-waitlist-submit">
                                        <span class="zp-button-text">Join Waitlist</span>
                                        <span class="zp-button-loader" style="display: none;">⏳</span>
                                    </button>
                                    
                                    <div class="zp-form-message" style="display: none;"></div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            
                <?php 
                // Display any additional content from the editor
                $content = get_the_content();
                if (!empty($content)) :
                    ?>
                    <div class="zp-additional-content">
                        <?php the_content(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer Section -->
        <footer class="zp-top10-footer">
            <div class="zp-container">
                <div class="zp-footer-content">
                    <div class="zp-footer-meta">
                        <p class="zp-footer-text">
                            This list was created by the ZipPicks Master Critic AI system, 
                            analyzing thousands of reviews and data points to bring you the most accurate rankings.
                        </p>
                        <?php if (current_user_can('edit_posts')) : ?>
                            <div class="zp-admin-actions">
                                <?php edit_post_link('Edit List', '<span class="zp-edit-link">', '</span>'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </footer>
    </article>
    </div><!-- .zp-top10-wrapper -->

    <style>
    /* ZipPicks Top 10 Template Styles - Mobile First */
    .zp-top10-wrapper {
        width: 100%;
        background: #f8f9fa;
    }
    
    /* Hero Section */
    .zp-top10-hero {
        padding: 60px 20px;
        text-align: center;
        color: white;
    }
    
    .zp-hero-content {
        position: relative;
        max-width: 800px;
        margin: 0 auto;
    }
    
    .zp-top10-hero__title {
        font-size: 2rem;
        font-weight: 700;
        margin: 0 0 10px;
        line-height: 1.2;
    }
    
    .zp-top10-hero__subtitle {
        font-size: 1.125rem;
        opacity: 0.9;
        margin: 0 0 15px;
    }
    
    .zp-top10-meta {
        font-size: 0.875rem;
        opacity: 0.8;
    }
    
    /* Container */
    .zp-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .zp-container--wide {
        max-width: 1400px;
    }
    
    /* Main Section */
    .zp-top10-section {
        padding: 40px 0;
        background: white;
    }
    
    /* Restaurant Cards */
    .zp-top10-list {
        display: grid;
        gap: 24px;
    }
    
    .zp-restaurant-card {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 24px;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .zp-restaurant-card:hover {
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    /* Restaurant Header */
    .zp-restaurant-header {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 16px;
        align-items: start;
        margin-bottom: 16px;
    }
    
    .zp-rank {
        font-size: 2.5rem;
        font-weight: 800;
        color: #194FAD;
        line-height: 1;
        min-width: 50px;
    }
    
    .zp-restaurant-title-wrapper {
        flex: 1;
    }
    
    .zp-restaurant-name {
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 0 4px;
        color: #1f2937;
    }
    
    .zp-restaurant-address {
        font-style: normal;
        font-size: 0.875rem;
        color: #6b7280;
    }
    
    /* Score Badge */
    .zp-score-badge {
        display: flex;
        flex-direction: column;
        align-items: center;
        background: #194FAD;
        color: white;
        padding: 8px 12px;
        border-radius: 8px;
        min-width: 60px;
    }
    
    .zp-score-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
    }
    
    .zp-score-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        opacity: 0.9;
    }
    
    /* Summary */
    .zp-restaurant-summary {
        font-size: 1rem;
        line-height: 1.6;
        color: #4b5563;
        margin: 0 0 16px;
    }
    
    /* Verified Badge */
    .zp-verified-sources {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.875rem;
        color: #059669;
        margin-bottom: 16px;
    }
    
    .zp-verified-icon {
        font-weight: bold;
    }
    
    /* Meta Information */
    .zp-restaurant-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.875rem;
        color: #6b7280;
        margin-bottom: 16px;
    }
    
    .zp-price-tier {
        font-weight: 600;
        color: #1f2937;
    }
    
    /* Top Dishes */
    .zp-top-dishes {
        margin-bottom: 16px;
    }
    
    .zp-dishes-label {
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #6b7280;
        margin: 0 0 8px;
    }
    
    .zp-dishes-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .zp-dish-item {
        background: #fef3c7;
        color: #92400e;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 0.875rem;
    }
    
    /* Pillar Scores */
    .zp-pillar-scores {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .zp-pillar-item {
        display: grid;
        grid-template-columns: auto auto 1fr auto;
        gap: 8px;
        align-items: center;
    }
    
    .zp-pillar-icon {
        font-size: 1.125rem;
    }
    
    .zp-pillar-name {
        font-size: 0.875rem;
        color: #6b7280;
    }
    
    .zp-pillar-bar {
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
    }
    
    .zp-pillar-fill {
        height: 100%;
        background: #194FAD;
        transition: width 0.3s ease;
    }
    
    .zp-pillar-value {
        font-weight: 600;
        font-size: 0.875rem;
    }
    
    /* Vibe Tags */
    .zp-restaurant-vibes {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .zp-vibe-tag {
        display: inline-block;
        background: #e3f2fd;
        color: #1976d2;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.875rem;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    
    .zp-vibe-tag:hover {
        background: #1976d2;
        color: white;
        transform: translateY(-1px);
    }
    
    /* Coming Soon State */
    .zp-coming-soon-wrapper {
        min-height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }
    
    .zp-coming-soon-card {
        background: white;
        border-radius: 16px;
        padding: 48px;
        max-width: 600px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    
    .zp-coming-soon-icon {
        font-size: 4rem;
        margin-bottom: 24px;
    }
    
    .zp-coming-soon-title {
        font-size: 2rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 16px;
    }
    
    .zp-coming-soon-text {
        font-size: 1.125rem;
        color: #6b7280;
        margin: 0 0 32px;
        line-height: 1.6;
    }
    
    /* Waitlist Form */
    .zp-waitlist-form-wrapper {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 32px;
        margin-top: 32px;
    }
    
    .zp-waitlist-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 8px;
    }
    
    .zp-waitlist-subtitle {
        color: #6b7280;
        margin: 0 0 24px;
    }
    
    .zp-form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .zp-form-group {
        text-align: left;
    }
    
    .zp-form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }
    
    .zp-form-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.2s ease;
    }
    
    .zp-form-input:focus {
        outline: none;
        border-color: #194FAD;
        box-shadow: 0 0 0 3px rgba(25, 79, 173, 0.1);
    }
    
    .zp-waitlist-submit {
        background: #194FAD;
        color: white;
        border: none;
        padding: 14px 32px;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .zp-waitlist-submit:hover {
        background: #0f3d8a;
        transform: translateY(-1px);
    }
    
    .zp-waitlist-submit:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .zp-form-message {
        margin-top: 16px;
        padding: 12px;
        border-radius: 8px;
        font-size: 0.875rem;
    }
    
    .zp-form-message.success {
        background: #d1fae5;
        color: #065f46;
    }
    
    .zp-form-message.error {
        background: #fee2e2;
        color: #991b1b;
    }
    
    /* Footer */
    .zp-top10-footer {
        background: #f8f9fa;
        padding: 40px 0;
        border-top: 1px solid #e5e7eb;
    }
    
    .zp-footer-text {
        color: #6b7280;
        text-align: center;
        margin: 0;
        line-height: 1.6;
    }
    
    .zp-admin-actions {
        text-align: center;
        margin-top: 16px;
    }
    
    .zp-edit-link {
        color: #194FAD;
        text-decoration: none;
        font-weight: 600;
    }
    
    .zp-edit-link:hover {
        text-decoration: underline;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .zp-top10-hero__title {
            font-size: 1.75rem;
        }
        
        .zp-restaurant-header {
            grid-template-columns: auto 1fr;
            gap: 12px;
        }
        
        .zp-score-badge {
            grid-column: 1 / -1;
            justify-self: start;
            flex-direction: row;
            gap: 8px;
        }
        
        .zp-rank {
            font-size: 2rem;
        }
        
        .zp-restaurant-name {
            font-size: 1.25rem;
        }
        
        .zp-form-row {
            grid-template-columns: 1fr;
        }
        
        .zp-pillar-scores {
            grid-template-columns: 1fr;
        }
        
        .zp-coming-soon-card {
            padding: 32px 24px;
        }
    }
    
    @media (min-width: 1024px) {
        .zp-top10-hero {
            padding: 100px 20px;
        }
        
        .zp-top10-hero__title {
            font-size: 3rem;
        }
        
        .zp-top10-section {
            padding: 60px 0;
        }
        
        .zp-restaurant-card {
            padding: 32px;
        }
    }
    
    </style>

    <!-- Waitlist JavaScript -->
    <script>
    jQuery(document).ready(function($) {
        $('#zp-top10-waitlist-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('.zp-waitlist-submit');
            var $message = $form.find('.zp-form-message');
            var $buttonText = $button.find('.zp-button-text');
            var $loader = $button.find('.zp-button-loader');
            
            // Disable button and show loader
            $button.prop('disabled', true);
            $buttonText.hide();
            $loader.show();
            $message.hide();
            
            // Prepare data
            var formData = {
                action: 'zp_add_to_waitlist',
                email: $form.find('#waitlist-email').val(),
                zip_code: $form.find('#waitlist-zip').val(),
                city: $form.find('input[name="city"]').val(),
                category: $form.find('input[name="category"]').val(),
                list_id: $form.find('input[name="list_id"]').val(),
                nonce: $form.find('#waitlist_nonce').val()
            };
            
            // Submit to waitlist
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $message.removeClass('error').addClass('success');
                        $message.html('🎉 You\'re on the list! We\'ll notify you when this Top 10 is ready.');
                        $form[0].reset();
                    } else {
                        $message.removeClass('success').addClass('error');
                        $message.html(response.data || 'Something went wrong. Please try again.');
                    }
                    $message.show();
                },
                error: function() {
                    $message.removeClass('success').addClass('error');
                    $message.html('Connection error. Please try again.');
                    $message.show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $buttonText.show();
                    $loader.hide();
                }
            });
        });
    });
    </script>

    <?php
endwhile;

get_footer();