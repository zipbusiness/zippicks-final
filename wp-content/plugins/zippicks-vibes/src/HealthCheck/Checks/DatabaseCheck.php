<?php
/**
 * Database Health Check
 * 
 * Checks database connectivity and table integrity
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\HealthCheck\Checks;

use ZipPicksVibes\HealthCheck\HealthCheckInterface;
use ZipPicksVibes\HealthCheck\HealthCheckResult;
use ZipPicksVibes\Database\Installer;

/**
 * Class DatabaseCheck
 * 
 * Enhanced database health check with missing table detection, repair guidance,
 * and comprehensive database analytics for enterprise monitoring
 */
class DatabaseCheck implements HealthCheckInterface {
    
    /**
     * Execute health check (legacy method)
     * 
     * @return HealthCheckResult
     */
    public function check(): HealthCheckResult {
        return $this->run();
    }
    
    /**
     * Execute enhanced database health check
     * 
     * @return HealthCheckResult
     */
    public function run(): HealthCheckResult {
        global $wpdb;
        
        $startTime = microtime(true);
        $issues = [];
        
        try {
            // Enhanced database connectivity test with detailed diagnostics
            $connectionStart = microtime(true);
            $testQuery = $wpdb->get_var("SELECT 1");
            $connectionTime = (microtime(true) - $connectionStart) * 1000;
            
            if ($testQuery !== '1') {
                return HealthCheckResult::fail(
                    $this->getName(),
                    'Database connectivity failure - cannot establish connection',
                    [
                        'status' => HealthCheckResult::FAIL,
                        'last_error' => $wpdb->last_error,
                        'last_query' => $wpdb->last_query,
                        'connection_time_ms' => $connectionTime,
                        'database_host' => DB_HOST,
                        'database_name' => DB_NAME,
                        'fallback_guidance' => 'Critical database connectivity issue requires immediate attention',
                        'emergency_steps' => [
                            'Verify database server is running',
                            'Check database credentials in wp-config.php',
                            'Verify network connectivity to database server',
                            'Check database server resource availability'
                        ],
                        'check_category' => 'database',
                        'system_impact' => 'critical'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            // Enhanced table existence check with detailed repair guidance
            if (!Installer::tables_exist()) {
                $missingTablesAnalysis = $this->analyzeMissingTables();
                return HealthCheckResult::fail(
                    $this->getName(),
                    sprintf('Critical: %d required database tables missing', count($missingTablesAnalysis['missing_tables'])),
                    [
                        'status' => HealthCheckResult::FAIL,
                        'missing_tables' => $missingTablesAnalysis['missing_tables'],
                        'existing_tables' => $missingTablesAnalysis['existing_tables'],
                        'total_required' => $missingTablesAnalysis['total_required'],
                        'completion_percentage' => $missingTablesAnalysis['completion_percentage'],
                        'fallback_guidance' => 'Database schema incomplete. Auto-repair available via table creation.',
                        'repair_options' => [
                            'automatic' => 'Run Installer::install() to create missing tables',
                            'manual' => 'Use create-tables.php interface for manual creation',
                            'sql_direct' => 'Execute SQL directly via phpMyAdmin or CLI'
                        ],
                        'repair_commands' => [
                            'wp_cli' => 'wp eval "ZipPicksVibes\\Database\\Installer::install();"',
                            'url' => admin_url('admin.php?page=zippicks-vibes-create-tables')
                        ],
                        'check_category' => 'database',
                        'data_integrity_risk' => 'critical'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            // Enhanced table structure validation
            $structureAnalysis = $this->analyzeTableStructure();
            if (!empty($structureAnalysis['issues'])) {
                $issues = array_merge($issues, $structureAnalysis['issues']);
            }
            
            // Comprehensive database performance analysis
            $performanceMetrics = $this->analyzePerformance();
            $healthScore = $this->calculateDatabaseHealthScore($connectionTime, $performanceMetrics, $structureAnalysis);
            
            // Database analytics and optimization recommendations
            $analyticsData = $this->generateDatabaseAnalytics();
            
            // Enhanced status determination with detailed analytics
            if ($healthScore < 70) {
                return HealthCheckResult::fail(
                    $this->getName(),
                    sprintf('Database health score critical (%d/100)', $healthScore),
                    [
                        'status' => HealthCheckResult::FAIL,
                        'health_score' => $healthScore,
                        'critical_issues' => $issues,
                        'performance_metrics' => $performanceMetrics,
                        'structure_analysis' => $structureAnalysis,
                        'database_analytics' => $analyticsData,
                        'fallback_guidance' => 'Database requires immediate optimization and issue resolution',
                        'optimization_priority' => $this->prioritizeOptimizations($issues, $performanceMetrics),
                        'check_category' => 'database'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            } elseif (!empty($issues) || $healthScore < 85) {
                return HealthCheckResult::warn(
                    $this->getName(),
                    sprintf('Database performance needs attention (score: %d/100)', $healthScore),
                    [
                        'status' => HealthCheckResult::WARN,
                        'health_score' => $healthScore,
                        'optimization_opportunities' => $issues,
                        'performance_metrics' => $performanceMetrics,
                        'structure_analysis' => $structureAnalysis,
                        'database_analytics' => $analyticsData,
                        'fallback_guidance' => 'Database performance can be improved with optimization',
                        'recommended_actions' => $this->getOptimizationRecommendations($performanceMetrics),
                        'check_category' => 'database'
                    ],
                    (microtime(true) - $startTime) * 1000
                );
            }
            
            return HealthCheckResult::pass(
                $this->getName(),
                sprintf('Database is optimal (health score: %d/100, %.2fms connection)', $healthScore, $connectionTime),
                [
                    'status' => HealthCheckResult::PASS,
                    'health_score' => $healthScore,
                    'connection_time_ms' => $connectionTime,
                    'table_statistics' => $this->getEnhancedTableStats(),
                    'performance_metrics' => $performanceMetrics,
                    'structure_analysis' => $structureAnalysis,
                    'database_analytics' => $analyticsData,
                    'optimization_status' => 'optimal',
                    'last_health_check' => date('Y-m-d H:i:s'),
                    'check_category' => 'database',
                    'maintenance_recommendations' => $this->getMaintenanceRecommendations()
                ],
                (microtime(true) - $startTime) * 1000
            );
            
        } catch (\Exception $e) {
            return HealthCheckResult::fail(
                $this->getName(),
                'Database health check failed with exception: ' . $e->getMessage(),
                [
                    'status' => HealthCheckResult::FAIL,
                    'exception' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'fallback_guidance' => 'Database system error requires immediate technical investigation',
                    'emergency_response' => [
                        'Check database server status',
                        'Review database error logs',
                        'Verify database connection parameters',
                        'Contact database administrator if needed'
                    ],
                    'check_category' => 'database',
                    'system_stability_risk' => 'critical'
                ],
                (microtime(true) - $startTime) * 1000
            );
        }
    }
    
    /**
     * Get check name
     * 
     * @return string
     */
    public function getName(): string {
        return 'database';
    }
    
    /**
     * Get check description
     * 
     * @return string
     */
    public function getDescription(): string {
        return 'Checks database connectivity and table integrity';
    }
    
    /**
     * Get check priority
     * 
     * @return int
     */
    public function getPriority(): int {
        return 100; // High priority
    }
    
    /**
     * Whether this check is critical
     * 
     * @return bool
     */
    public function isCritical(): bool {
        return true;
    }
    
    /**
     * Get check category for aggregation
     * 
     * @return string
     */
    public function getCategory(): string {
        return 'database';
    }
    
    /**
     * Get estimated execution duration
     * 
     * @return int
     */
    public function getEstimatedDuration(): int {
        return 1000; // 1000ms estimated (includes queries)
    }
    
    /**
     * Check if monitoring is enabled
     * 
     * @return bool
     */
    public function isMonitoringEnabled(): bool {
        return true;
    }
    
    /**
     * Analyze missing tables with comprehensive details
     * 
     * @return array
     */
    private function analyzeMissingTables(): array {
        $missingTables = $this->getMissingTables();
        $existingTables = $this->getExistingTables();
        $totalRequired = count($this->getRequiredTables());
        
        return [
            'missing_tables' => $missingTables,
            'existing_tables' => $existingTables,
            'total_required' => $totalRequired,
            'total_existing' => count($existingTables),
            'completion_percentage' => (count($existingTables) / $totalRequired) * 100
        ];
    }
    
    /**
     * Get missing tables
     * 
     * @return array
     */
    private function getMissingTables(): array {
        global $wpdb;
        
        $requiredTables = $this->getRequiredTables();
        
        $missingTables = [];
        
        foreach ($requiredTables as $table) {
            $fullTableName = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$fullTableName'") !== $fullTableName) {
                $missingTables[] = $fullTableName;
            }
        }
        
        return $missingTables;
    }
    
    /**
     * Get required tables list
     * 
     * @return array
     */
    private function getRequiredTables(): array {
        return [
            'zippicks_vibes',
            'zippicks_vibe_categories',
            'zippicks_vibe_category_assignments',
            'zippicks_waitlist',
            'zippicks_scrape_log',
            'zippicks_security_log',
            'zippicks_rate_limit_log',
            'zippicks_security_events'
        ];
    }
    
    /**
     * Get existing tables
     * 
     * @return array
     */
    private function getExistingTables(): array {
        global $wpdb;
        
        $existingTables = [];
        foreach ($this->getRequiredTables() as $table) {
            $fullTableName = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$fullTableName'") === $fullTableName) {
                $existingTables[] = $fullTableName;
            }
        }
        
        return $existingTables;
    }
    
    /**
     * Analyze table structure with detailed diagnostics
     * 
     * @return array
     */
    private function analyzeTableStructure(): array {
        $issues = $this->checkTableStructure();
        $indexAnalysis = $this->analyzeIndexes();
        $constraintAnalysis = $this->analyzeConstraints();
        
        return [
            'issues' => $issues,
            'index_analysis' => $indexAnalysis,
            'constraint_analysis' => $constraintAnalysis,
            'optimization_score' => $this->calculateStructureScore($issues, $indexAnalysis)
        ];
    }
    
    /**
     * Check table structure
     * 
     * @return array
     */
    private function checkTableStructure(): array {
        global $wpdb;
        
        $issues = [];
        
        // Check for missing indexes
        $vibesTable = $wpdb->prefix . 'zippicks_vibes';
        $indexes = $wpdb->get_results("SHOW INDEX FROM $vibesTable");
        
        $requiredIndexes = ['slug', 'is_active', 'order_position'];
        $existingIndexes = array_column($indexes, 'Column_name');
        
        foreach ($requiredIndexes as $index) {
            if (!in_array($index, $existingIndexes)) {
                $issues[] = "Missing index on $index column in vibes table";
            }
        }
        
        return $issues;
    }
    
    /**
     * Analyze database performance with comprehensive metrics
     * 
     * @return array
     */
    private function analyzePerformance(): array {
        $basicMetrics = $this->checkPerformance();
        $queryAnalysis = $this->analyzeQueryPerformance();
        $storageAnalysis = $this->analyzeStorageMetrics();
        
        return array_merge($basicMetrics, [
            'query_analysis' => $queryAnalysis,
            'storage_analysis' => $storageAnalysis,
            'performance_score' => $this->calculatePerformanceScore($basicMetrics, $queryAnalysis)
        ]);
    }
    
    /**
     * Check database performance
     * 
     * @return array
     */
    private function checkPerformance(): array {
        global $wpdb;
        
        $metrics = [];
        
        // Test query performance
        $startTime = microtime(true);
        $wpdb->get_results("SELECT id FROM {$wpdb->prefix}zippicks_vibes LIMIT 100");
        $metrics['query_time_ms'] = (microtime(true) - $startTime) * 1000;
        
        // Get table sizes
        $tableInfo = $wpdb->get_results("
            SELECT 
                TABLE_NAME as table_name,
                ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS size_mb,
                TABLE_ROWS as rows
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME LIKE '{$wpdb->prefix}zippicks_%'
        ");
        
        $metrics['tables'] = [];
        foreach ($tableInfo as $info) {
            $metrics['tables'][$info->table_name] = [
                'size_mb' => $info->size_mb,
                'rows' => $info->rows
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Get enhanced table statistics
     * 
     * @return array
     */
    private function getEnhancedTableStats(): array {
        $basicStats = $this->getTableStats();
        $detailedStats = $this->getDetailedTableAnalytics();
        
        return array_merge($basicStats, [
            'detailed_analytics' => $detailedStats,
            'growth_trends' => $this->calculateGrowthTrends($basicStats),
            'optimization_opportunities' => $this->identifyOptimizationOpportunities($detailedStats)
        ]);
    }
    
    /**
     * Get table statistics
     * 
     * @return array
     */
    private function getTableStats(): array {
        global $wpdb;
        
        return [
            'vibes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_vibes"),
            'categories' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_vibe_categories"),
            'waitlist' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_waitlist")
        ];
    }
    
    /**
     * Analyze database indexes
     * 
     * @return array
     */
    private function analyzeIndexes(): array {
        global $wpdb;
        
        $analysis = [];
        $requiredTables = $this->getRequiredTables();
        
        foreach ($requiredTables as $table) {
            $fullTableName = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$fullTableName'") === $fullTableName) {
                $indexes = $wpdb->get_results("SHOW INDEX FROM $fullTableName");
                $analysis[$table] = [
                    'total_indexes' => count($indexes),
                    'unique_indexes' => count(array_filter($indexes, fn($idx) => $idx->Non_unique == 0)),
                    'index_details' => $indexes
                ];
            }
        }
        
        return $analysis;
    }
    
    /**
     * Analyze database constraints
     * 
     * @return array
     */
    private function analyzeConstraints(): array {
        global $wpdb;
        
        $constraints = $wpdb->get_results("
            SELECT 
                TABLE_NAME,
                CONSTRAINT_NAME,
                CONSTRAINT_TYPE
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME LIKE '{$wpdb->prefix}zippicks_%'
        ");
        
        return [
            'total_constraints' => count($constraints),
            'foreign_keys' => count(array_filter($constraints, fn($c) => $c->CONSTRAINT_TYPE === 'FOREIGN KEY')),
            'unique_constraints' => count(array_filter($constraints, fn($c) => $c->CONSTRAINT_TYPE === 'UNIQUE')),
            'constraint_details' => $constraints
        ];
    }
    
    /**
     * Analyze query performance
     * 
     * @return array
     */
    private function analyzeQueryPerformance(): array {
        global $wpdb;
        
        $metrics = [];
        
        // Test different query types
        $queries = [
            'simple_select' => "SELECT id FROM {$wpdb->prefix}zippicks_vibes LIMIT 1",
            'count_query' => "SELECT COUNT(*) FROM {$wpdb->prefix}zippicks_vibes",
            'join_query' => "SELECT v.id, c.name FROM {$wpdb->prefix}zippicks_vibes v LEFT JOIN {$wpdb->prefix}zippicks_vibe_categories c ON v.id = c.vibe_id LIMIT 10"
        ];
        
        foreach ($queries as $type => $query) {
            $startTime = microtime(true);
            $wpdb->get_results($query);
            $metrics[$type . '_time_ms'] = (microtime(true) - $startTime) * 1000;
        }
        
        return $metrics;
    }
    
    /**
     * Analyze storage metrics
     * 
     * @return array
     */
    private function analyzeStorageMetrics(): array {
        global $wpdb;
        
        $storage = $wpdb->get_results("
            SELECT 
                SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 AS total_size_mb,
                SUM(DATA_LENGTH) / 1024 / 1024 AS data_size_mb,
                SUM(INDEX_LENGTH) / 1024 / 1024 AS index_size_mb,
                COUNT(*) AS table_count
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME LIKE '{$wpdb->prefix}zippicks_%'
        ");
        
        $result = $storage[0] ?? null;
        
        return [
            'total_size_mb' => $result ? (float)$result->total_size_mb : 0,
            'data_size_mb' => $result ? (float)$result->data_size_mb : 0,
            'index_size_mb' => $result ? (float)$result->index_size_mb : 0,
            'table_count' => $result ? (int)$result->table_count : 0,
            'index_to_data_ratio' => $result && $result->data_size_mb > 0 
                ? ($result->index_size_mb / $result->data_size_mb) * 100 
                : 0
        ];
    }
    
    /**
     * Generate comprehensive database analytics
     * 
     * @return array
     */
    private function generateDatabaseAnalytics(): array {
        global $wpdb;
        
        return [
            'database_version' => $wpdb->get_var('SELECT VERSION()'),
            'character_set' => $wpdb->get_var('SELECT @@character_set_database'),
            'collation' => $wpdb->get_var('SELECT @@collation_database'),
            'max_connections' => $wpdb->get_var('SELECT @@max_connections'),
            'innodb_buffer_pool_size' => $wpdb->get_var('SELECT @@innodb_buffer_pool_size'),
            'query_cache_size' => $wpdb->get_var('SELECT @@query_cache_size'),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Calculate database health score
     * 
     * @param float $connectionTime
     * @param array $performanceMetrics
     * @param array $structureAnalysis
     * @return int
     */
    private function calculateDatabaseHealthScore(float $connectionTime, array $performanceMetrics, array $structureAnalysis): int {
        $score = 100;
        
        // Connection time scoring
        if ($connectionTime > 100) $score -= 20;
        elseif ($connectionTime > 50) $score -= 10;
        elseif ($connectionTime > 25) $score -= 5;
        
        // Query performance scoring
        if (isset($performanceMetrics['query_time_ms'])) {
            if ($performanceMetrics['query_time_ms'] > 100) $score -= 25;
            elseif ($performanceMetrics['query_time_ms'] > 50) $score -= 15;
            elseif ($performanceMetrics['query_time_ms'] > 25) $score -= 5;
        }
        
        // Structure issues scoring
        $structureIssues = count($structureAnalysis['issues'] ?? []);
        $score -= $structureIssues * 10;
        
        return max(0, min(100, $score));
    }
    
    /**
     * Calculate structure optimization score
     * 
     * @param array $issues
     * @param array $indexAnalysis
     * @return int
     */
    private function calculateStructureScore(array $issues, array $indexAnalysis): int {
        $score = 100;
        
        $score -= count($issues) * 15;
        
        // Index optimization scoring
        foreach ($indexAnalysis as $table => $analysis) {
            if ($analysis['total_indexes'] < 3) $score -= 10;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Calculate performance score
     * 
     * @param array $basicMetrics
     * @param array $queryAnalysis
     * @return int
     */
    private function calculatePerformanceScore(array $basicMetrics, array $queryAnalysis): int {
        $score = 100;
        
        if (isset($basicMetrics['query_time_ms'])) {
            if ($basicMetrics['query_time_ms'] > 100) $score -= 30;
            elseif ($basicMetrics['query_time_ms'] > 50) $score -= 20;
            elseif ($basicMetrics['query_time_ms'] > 25) $score -= 10;
        }
        
        // Analyze individual query types
        foreach ($queryAnalysis as $metric => $time) {
            if (strpos($metric, '_time_ms') !== false && $time > 50) {
                $score -= 5;
            }
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Get optimization recommendations
     * 
     * @param array $performanceMetrics
     * @return array
     */
    private function getOptimizationRecommendations(array $performanceMetrics): array {
        $recommendations = [];
        
        if (isset($performanceMetrics['query_time_ms']) && $performanceMetrics['query_time_ms'] > 50) {
            $recommendations[] = 'Consider adding database indexes for frequently queried columns';
            $recommendations[] = 'Review and optimize slow queries';
        }
        
        if (isset($performanceMetrics['storage_analysis']['total_size_mb']) 
            && $performanceMetrics['storage_analysis']['total_size_mb'] > 100) {
            $recommendations[] = 'Consider database cleanup and archiving old data';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Database performance is optimal';
        }
        
        return $recommendations;
    }
    
    /**
     * Prioritize optimizations
     * 
     * @param array $issues
     * @param array $performanceMetrics
     * @return array
     */
    private function prioritizeOptimizations(array $issues, array $performanceMetrics): array {
        $priorities = [];
        
        if (!empty($issues)) {
            $priorities['high'][] = 'Fix database structure issues';
        }
        
        if (isset($performanceMetrics['query_time_ms']) && $performanceMetrics['query_time_ms'] > 100) {
            $priorities['high'][] = 'Optimize slow queries immediately';
        }
        
        return $priorities;
    }
    
    /**
     * Get detailed table analytics
     * 
     * @return array
     */
    private function getDetailedTableAnalytics(): array {
        global $wpdb;
        
        $analytics = [];
        
        foreach ($this->getRequiredTables() as $table) {
            $fullTableName = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$fullTableName'") === $fullTableName) {
                $analytics[$table] = [
                    'row_count' => (int)$wpdb->get_var("SELECT COUNT(*) FROM $fullTableName"),
                    'avg_row_length' => $wpdb->get_var("SELECT AVG_ROW_LENGTH FROM information_schema.TABLES WHERE TABLE_NAME = '$fullTableName'"),
                    'auto_increment' => $wpdb->get_var("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_NAME = '$fullTableName'")
                ];
            }
        }
        
        return $analytics;
    }
    
    /**
     * Calculate growth trends
     * 
     * @param array $stats
     * @return array
     */
    private function calculateGrowthTrends(array $stats): array {
        // This would typically compare with historical data
        // For now, we'll provide structure for future implementation
        return [
            'trend_analysis' => 'Growth trend analysis requires historical data',
            'recommendation' => 'Implement periodic data collection for trend analysis'
        ];
    }
    
    /**
     * Identify optimization opportunities
     * 
     * @param array $detailedStats
     * @return array
     */
    private function identifyOptimizationOpportunities(array $detailedStats): array {
        $opportunities = [];
        
        foreach ($detailedStats as $table => $stats) {
            if (isset($stats['row_count']) && $stats['row_count'] > 10000) {
                $opportunities[] = "Consider partitioning large table: $table ({$stats['row_count']} rows)";
            }
        }
        
        return $opportunities;
    }
    
    /**
     * Get maintenance recommendations
     * 
     * @return array
     */
    private function getMaintenanceRecommendations(): array {
        return [
            'regular_tasks' => [
                'Run OPTIMIZE TABLE monthly',
                'Monitor index usage and performance',
                'Review query slow log',
                'Archive old data periodically'
            ],
            'monitoring' => [
                'Set up database performance monitoring',
                'Track table size growth',
                'Monitor connection pool usage',
                'Watch for deadlocks and long queries'
            ]
        ];
    }
}