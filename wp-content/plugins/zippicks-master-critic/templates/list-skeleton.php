<?php
/**
 * Skeleton template for Master Critic lists (anti-scraping)
 * This template shows a loading skeleton while actual content loads via AJAX
 *
 * @package ZipPicks_Master_Critic
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div class="zippicks-master-critic-container">
    <article class="zippicks-list-article">
        <header class="zippicks-list-header">
            <div class="zp-skeleton-title-main"></div>
            <div class="zp-skeleton-subtitle"></div>
            <div class="zp-skeleton-meta">
                <span class="zp-skeleton-date"></span>
                <span class="zp-skeleton-category"></span>
                <span class="zp-skeleton-location"></span>
            </div>
        </header>
        
        <div id="zippicks-list-content" class="zippicks-list-content">
            <!-- Loading skeleton will be replaced by AJAX content -->
            <div class="zp-skeleton-intro">
                <div class="zp-skeleton-paragraph"></div>
                <div class="zp-skeleton-paragraph short"></div>
            </div>
            
            <div class="zp-skeleton-rankings">
                <h2 class="zp-skeleton-rankings-title"></h2>
                
                <?php for ($i = 1; $i <= 10; $i++): ?>
                <div class="zp-skeleton-business-card">
                    <div class="zp-skeleton-rank-badge">
                        <div class="zp-skeleton-rank-number"></div>
                    </div>
                    
                    <div class="zp-skeleton-business-content">
                        <div class="zp-skeleton-business-header">
                            <div class="zp-skeleton-business-name"></div>
                            <div class="zp-skeleton-business-score"></div>
                        </div>
                        
                        <div class="zp-skeleton-business-details">
                            <div class="zp-skeleton-price-tier"></div>
                            <div class="zp-skeleton-review-count"></div>
                        </div>
                        
                        <div class="zp-skeleton-summary">
                            <div class="zp-skeleton-summary-line"></div>
                            <div class="zp-skeleton-summary-line"></div>
                            <div class="zp-skeleton-summary-line short"></div>
                        </div>
                        
                        <div class="zp-skeleton-pillars">
                            <?php for ($j = 1; $j <= 6; $j++): ?>
                            <div class="zp-skeleton-pillar">
                                <div class="zp-skeleton-pillar-label"></div>
                                <div class="zp-skeleton-pillar-score"></div>
                            </div>
                            <?php endfor; ?>
                        </div>
                        
                        <div class="zp-skeleton-vibes">
                            <div class="zp-skeleton-vibe-tag"></div>
                            <div class="zp-skeleton-vibe-tag"></div>
                            <div class="zp-skeleton-vibe-tag"></div>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            
            <div class="zp-loading-indicator">
                <div class="zp-spinner"></div>
                <p class="zp-loading-text">Loading ZipPicks recommendations...</p>
                <p class="zp-loading-subtext">Curating the best local spots for you</p>
            </div>
        </div>
        
        <footer class="zippicks-list-footer">
            <div class="zp-skeleton-footer-content">
                <div class="zp-skeleton-footer-line"></div>
                <div class="zp-skeleton-footer-line short"></div>
            </div>
        </footer>
    </article>
</div>

<style>
/* ZipPicks Master Critic Skeleton Styles */
.zippicks-master-critic-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.zippicks-list-article {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.zippicks-list-header {
    padding: 40px 40px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.zp-skeleton-title-main {
    height: 42px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    margin-bottom: 15px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-subtitle {
    height: 24px;
    width: 70%;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    margin-bottom: 20px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-meta {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.zp-skeleton-date,
.zp-skeleton-category,
.zp-skeleton-location {
    height: 16px;
    width: 80px;
    background: rgba(255, 255, 255, 0.25);
    border-radius: 4px;
    animation: pulse 1.5s infinite;
}

.zippicks-list-content {
    padding: 40px;
}

.zp-skeleton-intro {
    margin-bottom: 40px;
}

.zp-skeleton-paragraph {
    height: 18px;
    background: #f0f0f0;
    border-radius: 4px;
    margin-bottom: 12px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-paragraph.short {
    width: 75%;
}

.zp-skeleton-rankings-title {
    height: 32px;
    width: 300px;
    background: #f0f0f0;
    border-radius: 6px;
    margin-bottom: 30px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-business-card {
    display: flex;
    margin-bottom: 30px;
    padding: 25px;
    background: #fafafa;
    border-radius: 12px;
    border: 2px solid #f0f0f0;
    position: relative;
}

.zp-skeleton-rank-badge {
    flex-shrink: 0;
    margin-right: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.zp-skeleton-rank-number {
    width: 50px;
    height: 50px;
    background: #e0e0e0;
    border-radius: 50%;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-business-content {
    flex: 1;
}

.zp-skeleton-business-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.zp-skeleton-business-name {
    height: 28px;
    width: 250px;
    background: #f0f0f0;
    border-radius: 6px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-business-score {
    height: 32px;
    width: 60px;
    background: #e8f5e8;
    border-radius: 16px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-business-details {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.zp-skeleton-price-tier,
.zp-skeleton-review-count {
    height: 18px;
    width: 80px;
    background: #f0f0f0;
    border-radius: 4px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-summary {
    margin: 20px 0;
}

.zp-skeleton-summary-line {
    height: 16px;
    background: #f0f0f0;
    border-radius: 4px;
    margin-bottom: 8px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-summary-line.short {
    width: 80%;
}

.zp-skeleton-pillars {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin: 20px 0;
    padding: 20px;
    background: #f8f8f8;
    border-radius: 8px;
}

.zp-skeleton-pillar {
    text-align: center;
}

.zp-skeleton-pillar-label {
    height: 14px;
    background: #f0f0f0;
    border-radius: 4px;
    margin-bottom: 8px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-pillar-score {
    height: 20px;
    width: 35px;
    background: #e0e0e0;
    border-radius: 4px;
    margin: 0 auto;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-vibes {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.zp-skeleton-vibe-tag {
    height: 24px;
    width: 80px;
    background: #f0f0f0;
    border-radius: 12px;
    animation: pulse 1.5s infinite;
}

.zp-loading-indicator {
    text-align: center;
    padding: 60px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    margin: 40px 0;
}

.zp-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    margin: 0 auto 20px;
    animation: spin 1s linear infinite;
}

.zp-loading-text {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0 0 8px 0;
}

.zp-loading-subtext {
    font-size: 14px;
    color: #666;
    margin: 0;
}

.zippicks-list-footer {
    padding: 30px 40px;
    background: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.zp-skeleton-footer-line {
    height: 16px;
    background: #f0f0f0;
    border-radius: 4px;
    margin-bottom: 10px;
    animation: pulse 1.5s infinite;
}

.zp-skeleton-footer-line.short {
    width: 60%;
}

/* Animations */
@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive design */
@media (max-width: 768px) {
    .zippicks-master-critic-container {
        padding: 10px;
    }
    
    .zippicks-list-header,
    .zippicks-list-content,
    .zippicks-list-footer {
        padding: 20px;
    }
    
    .zp-skeleton-business-card {
        flex-direction: column;
        padding: 20px;
    }
    
    .zp-skeleton-rank-badge {
        margin-right: 0;
        margin-bottom: 15px;
        align-self: flex-start;
    }
    
    .zp-skeleton-business-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .zp-skeleton-pillars {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .zp-skeleton-vibes {
        flex-wrap: wrap;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .zippicks-list-article {
        background: #1a1a1a;
        color: #fff;
    }
    
    .zp-skeleton-paragraph,
    .zp-skeleton-rankings-title,
    .zp-skeleton-business-name,
    .zp-skeleton-pillar-label,
    .zp-skeleton-vibe-tag,
    .zp-skeleton-footer-line {
        background: #333;
    }
    
    .zp-skeleton-business-card {
        background: #2a2a2a;
        border-color: #333;
    }
    
    .zp-skeleton-pillars {
        background: #2a2a2a;
    }
}

/* Print styles */
@media print {
    .zp-loading-indicator {
        display: none;
    }
    
    .zp-skeleton-business-card::after {
        content: "Content loading... Please view online for full details.";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.9);
        padding: 20px;
        border-radius: 8px;
        font-weight: bold;
        color: #333;
    }
}
</style>

<?php
// Add schema.org markup for SEO (minimal, non-revealing)
$list_id = get_the_ID();
$list_title = get_the_title();
$list_category = get_post_meta($list_id, 'business_category', true);
$list_location = get_post_meta($list_id, 'location', true);
?>

<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "ItemList",
    "name": "<?php echo esc_js($list_title); ?>",
    "description": "Curated local recommendations for <?php echo esc_js($list_category); ?> in <?php echo esc_js($list_location); ?>",
    "url": "<?php echo esc_url(get_permalink()); ?>",
    "publisher": {
        "@type": "Organization",
        "name": "ZipPicks",
        "url": "https://zippicks.com"
    },
    "numberOfItems": "Loading...",
    "itemListOrder": "Ranked"
}
</script>

<?php get_footer(); ?>