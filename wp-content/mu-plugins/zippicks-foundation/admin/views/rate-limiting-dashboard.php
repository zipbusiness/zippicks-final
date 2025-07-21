<?php
/**
 * Rate Limiting Dashboard
 * 
 * Admin interface for monitoring and managing rate limits
 * across the ZipPicks $100B platform.
 */

// Ensure we have necessary variables
if (!isset($manager)) {
    return;
}

$stats = $manager->getUsageStats();
$tiers = [
    'free' => $manager->getTierConfig('free'),
    'pro' => $manager->getTierConfig('pro'),
    'business' => $manager->getTierConfig('business'),
    'enterprise' => $manager->getTierConfig('enterprise'),
];

// Get current user stats if viewing specific user
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$userLimits = $userId ? get_user_rate_limits($userId) : null;
?>

<div class="wrap zippicks-rate-limiting">
    <h1>Rate Limiting Dashboard</h1>
    
    <div class="notice notice-info">
        <p><strong>Rate Limiting protects our $100B platform</strong> by preventing abuse while enabling tier-based monetization. 
        Higher tiers get increased limits and priority access to AI features.</p>
    </div>

    <!-- Overview Cards -->
    <div class="rate-limit-cards">
        <div class="card">
            <h3>Active Limiters</h3>
            <div class="card-value"><?php echo count($stats); ?></div>
            <div class="card-meta">Protecting resources</div>
        </div>
        
        <div class="card">
            <h3>Total Tiers</h3>
            <div class="card-value">4</div>
            <div class="card-meta">Free → Enterprise</div>
        </div>
        
        <div class="card">
            <h3>Revenue Impact</h3>
            <div class="card-value">$60-100M</div>
            <div class="card-meta">ARR potential</div>
        </div>
    </div>

    <!-- Tier Configuration -->
    <div class="tier-configuration">
        <h2>Tier Configuration</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Tier</th>
                    <th>Multiplier</th>
                    <th>Cost Discount</th>
                    <th>API Limit</th>
                    <th>Taste Graph</th>
                    <th>AI Scores</th>
                    <th>Email</th>
                    <th>Search</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tiers as $tierName => $config): ?>
                <tr class="tier-<?php echo esc_attr($tierName); ?>">
                    <td><strong><?php echo ucfirst($tierName); ?></strong></td>
                    <td><?php echo $config['multiplier'] == PHP_FLOAT_MAX ? '∞' : $config['multiplier'] . 'x'; ?></td>
                    <td><?php echo (int)((1 - $config['cost_multiplier']) * 100); ?>%</td>
                    <td><?php echo $config['limits']['api'] ?? '∞'; ?>/min</td>
                    <td><?php echo $config['limits']['taste_graph'] ?? '∞'; ?>/hr</td>
                    <td><?php echo $config['limits']['ai_scores'] ?? '∞'; ?>/hr</td>
                    <td><?php echo $config['limits']['email'] ?? '∞'; ?>/hr</td>
                    <td><?php echo $config['limits']['search'] ?? '∞'; ?>/min</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Active Limiters -->
    <div class="active-limiters">
        <h2>Active Rate Limiters</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Limiter</th>
                    <th>Algorithm</th>
                    <th>Store</th>
                    <th>Configuration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>API</strong></td>
                    <td>Sliding Window</td>
                    <td>Redis</td>
                    <td>60 second window</td>
                    <td><span class="status-active">Active</span></td>
                </tr>
                <tr>
                    <td><strong>Taste Graph</strong></td>
                    <td>Token Bucket</td>
                    <td>Redis</td>
                    <td>100 capacity, 1.67/sec refill</td>
                    <td><span class="status-active">Active</span></td>
                </tr>
                <tr>
                    <td><strong>AI Scores</strong></td>
                    <td>Fixed Window</td>
                    <td>Redis</td>
                    <td>3600 second window</td>
                    <td><span class="status-active">Active</span></td>
                </tr>
                <tr>
                    <td><strong>Email</strong></td>
                    <td>Leaky Bucket</td>
                    <td>Redis</td>
                    <td>1000 capacity, 16.67/sec leak</td>
                    <td><span class="status-active">Active</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php if ($userLimits): ?>
    <!-- User Rate Limits -->
    <div class="user-rate-limits">
        <h2>User Rate Limits (ID: <?php echo $userId; ?>)</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Resource</th>
                    <th>Tier</th>
                    <th>Limit</th>
                    <th>Used</th>
                    <th>Remaining</th>
                    <th>Reset Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userLimits as $resource => $limit): ?>
                <tr>
                    <td><strong><?php echo ucfirst(str_replace('_', ' ', $resource)); ?></strong></td>
                    <td><?php echo ucfirst($limit['tier']); ?></td>
                    <td><?php echo $limit['limit']; ?></td>
                    <td><?php echo $limit['used']; ?></td>
                    <td><?php echo $limit['remaining']; ?></td>
                    <td><?php echo date('H:i:s', $limit['reset_at']); ?></td>
                    <td>
                        <button class="button reset-limit" data-key="user:<?php echo $userId; ?>:<?php echo $resource; ?>">
                            Reset
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Operation Costs -->
    <div class="operation-costs">
        <h2>Operation Costs</h2>
        <p>Different operations consume different amounts of rate limit quota based on their computational cost:</p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Operation</th>
                    <th>Cost Units</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>API Read</td>
                    <td>1</td>
                    <td>Basic data retrieval</td>
                </tr>
                <tr>
                    <td>API Write</td>
                    <td>2</td>
                    <td>Data modification</td>
                </tr>
                <tr>
                    <td>Taste Graph Calculation</td>
                    <td>10</td>
                    <td>Personalization algorithm</td>
                </tr>
                <tr>
                    <td>AI Critic Score</td>
                    <td>25</td>
                    <td>Master Critic AI analysis</td>
                </tr>
                <tr>
                    <td>Vibe Matching</td>
                    <td>5</td>
                    <td>Mood-based discovery</td>
                </tr>
                <tr>
                    <td>Personalized Email</td>
                    <td>3</td>
                    <td>Taste-matched campaigns</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Search User -->
    <div class="search-user">
        <h2>View User Limits</h2>
        <form method="get" action="">
            <input type="hidden" name="page" value="zippicks-rate-limiting">
            <input type="number" name="user_id" placeholder="User ID" value="<?php echo $userId; ?>">
            <button type="submit" class="button button-primary">View Limits</button>
        </form>
    </div>
</div>

<style>
.zippicks-rate-limiting .rate-limit-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.zippicks-rate-limiting .card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.zippicks-rate-limiting .card h3 {
    margin: 0 0 10px;
    color: #23282d;
}

.zippicks-rate-limiting .card-value {
    font-size: 36px;
    font-weight: 600;
    color: #0073aa;
    margin: 10px 0;
}

.zippicks-rate-limiting .card-meta {
    color: #666;
    font-size: 14px;
}

.zippicks-rate-limiting .status-active {
    color: #46b450;
    font-weight: 600;
}

.zippicks-rate-limiting .tier-free {
    background-color: #f9f9f9;
}

.zippicks-rate-limiting .tier-pro {
    background-color: #e8f4f8;
}

.zippicks-rate-limiting .tier-business {
    background-color: #e8f8e8;
}

.zippicks-rate-limiting .tier-enterprise {
    background-color: #fff8e8;
}

.zippicks-rate-limiting h2 {
    margin-top: 30px;
}

.zippicks-rate-limiting .search-user {
    margin-top: 30px;
    padding: 20px;
    background: #f9f9f9;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.zippicks-rate-limiting .search-user input[type="number"] {
    margin-right: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Reset limit button
    $('.reset-limit').on('click', function() {
        var key = $(this).data('key');
        var button = $(this);
        
        if (!confirm('Reset rate limit for ' + key + '?')) {
            return;
        }
        
        button.prop('disabled', true).text('Resetting...');
        
        $.ajax({
            url: '<?php echo rest_url('zippicks/v1/rate-limits/reset'); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            data: {
                key: key
            },
            success: function(response) {
                button.text('Reset').prop('disabled', false);
                location.reload();
            },
            error: function() {
                button.text('Error').prop('disabled', false);
            }
        });
    });
});
</script>