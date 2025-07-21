<?php
/**
 * Metrics Collector for Prometheus
 * 
 * Collects and stores metrics for export
 * 
 * @package ZipPicks\Foundation\Observability\Metrics
 * @since 2.0.0
 */

namespace ZipPicks\Foundation\Observability\Metrics;

use ZipPicks\Foundation\Core\Foundation;

class MetricsCollector
{
    /**
     * @var array Registered metrics
     */
    protected array $metrics = [];
    
    /**
     * @var array Counters
     */
    protected array $counters = [];
    
    /**
     * @var array Gauges
     */
    protected array $gauges = [];
    
    /**
     * @var array Histograms
     */
    protected array $histograms = [];
    
    /**
     * @var array Summaries
     */
    protected array $summaries = [];
    
    /**
     * @var float Start time
     */
    protected float $startTime;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->startTime = microtime(true);
        
        // Register default collectors
        $this->registerDefaultCollectors();
    }
    
    /**
     * Register a counter metric
     * 
     * @param string $name
     * @param string $help
     * @param array $labels
     * @return Counter
     */
    public function registerCounter(string $name, string $help, array $labels = []): Counter
    {
        $counter = new Counter($name, $help, $labels);
        $this->counters[$name] = $counter;
        
        $this->metrics[$name] = [
            'type' => 'counter',
            'help' => $help,
            'labels' => $labels,
            'collector' => $counter
        ];
        
        return $counter;
    }
    
    /**
     * Register a gauge metric
     * 
     * @param string $name
     * @param string $help
     * @param array $labels
     * @return Gauge
     */
    public function registerGauge(string $name, string $help, array $labels = []): Gauge
    {
        $gauge = new Gauge($name, $help, $labels);
        $this->gauges[$name] = $gauge;
        
        $this->metrics[$name] = [
            'type' => 'gauge',
            'help' => $help,
            'labels' => $labels,
            'collector' => $gauge
        ];
        
        return $gauge;
    }
    
    /**
     * Register a histogram metric
     * 
     * @param string $name
     * @param string $help
     * @param array $labels
     * @param array $buckets
     * @return Histogram
     */
    public function registerHistogram(string $name, string $help, array $labels = [], array $buckets = null): Histogram
    {
        $histogram = new Histogram($name, $help, $labels, $buckets);
        $this->histograms[$name] = $histogram;
        
        $this->metrics[$name] = [
            'type' => 'histogram',
            'help' => $help,
            'labels' => $labels,
            'collector' => $histogram
        ];
        
        return $histogram;
    }
    
    /**
     * Register a summary metric
     * 
     * @param string $name
     * @param string $help
     * @param array $labels
     * @param array $quantiles
     * @return Summary
     */
    public function registerSummary(string $name, string $help, array $labels = [], array $quantiles = null): Summary
    {
        $summary = new Summary($name, $help, $labels, $quantiles);
        $this->summaries[$name] = $summary;
        
        $this->metrics[$name] = [
            'type' => 'summary',
            'help' => $help,
            'labels' => $labels,
            'collector' => $summary
        ];
        
        return $summary;
    }
    
    /**
     * Get or create a counter
     * 
     * @param string $name
     * @return Counter|null
     */
    public function counter(string $name): ?Counter
    {
        return $this->counters[$name] ?? null;
    }
    
    /**
     * Get or create a gauge
     * 
     * @param string $name
     * @return Gauge|null
     */
    public function gauge(string $name): ?Gauge
    {
        return $this->gauges[$name] ?? null;
    }
    
    /**
     * Get or create a histogram
     * 
     * @param string $name
     * @return Histogram|null
     */
    public function histogram(string $name): ?Histogram
    {
        return $this->histograms[$name] ?? null;
    }
    
    /**
     * Get or create a summary
     * 
     * @param string $name
     * @return Summary|null
     */
    public function summary(string $name): ?Summary
    {
        return $this->summaries[$name] ?? null;
    }
    
    /**
     * Collect all metrics
     * 
     * @return array
     */
    public function collect(): array
    {
        $collected = [];
        
        foreach ($this->metrics as $name => $metric) {
            $collector = $metric['collector'];
            
            $collected[] = [
                'name' => $name,
                'type' => $metric['type'],
                'help' => $metric['help'],
                'samples' => $collector->collect()
            ];
        }
        
        return $collected;
    }
    
    /**
     * Register default collectors
     */
    protected function registerDefaultCollectors(): void
    {
        // HTTP request metrics
        $this->registerCounter('http_requests_total', 'Total HTTP requests', ['method', 'endpoint', 'status']);
        $this->registerHistogram('http_request_duration_seconds', 'HTTP request duration', ['method', 'endpoint']);
        
        // Database metrics
        $this->registerCounter('database_queries_total', 'Total database queries', ['operation', 'table']);
        $this->registerHistogram('database_query_duration_seconds', 'Database query duration', ['operation', 'table']);
        
        // Cache metrics
        $this->registerCounter('cache_operations_total', 'Total cache operations', ['operation', 'result']);
        $this->registerHistogram('cache_operation_duration_seconds', 'Cache operation duration', ['operation']);
        
        // Queue metrics
        $this->registerCounter('queue_jobs_total', 'Total queue jobs', ['queue', 'status']);
        $this->registerHistogram('queue_job_duration_seconds', 'Queue job duration', ['queue', 'job_class']);
        
        // API metrics
        $this->registerCounter('api_requests_total', 'Total API requests', ['version', 'endpoint', 'method']);
        $this->registerCounter('api_errors_total', 'Total API errors', ['version', 'endpoint', 'error_code']);
        $this->registerHistogram('api_request_duration_seconds', 'API request duration', ['version', 'endpoint', 'method']);
        
        // Business metrics
        $this->registerGauge('active_users', 'Currently active users');
        $this->registerCounter('user_registrations_total', 'Total user registrations', ['source']);
        $this->registerCounter('revenue_transactions_total', 'Total revenue transactions', ['type', 'currency']);
        $this->registerHistogram('revenue_transaction_amount', 'Revenue transaction amounts', ['type', 'currency']);
    }
    
    /**
     * Reset all metrics
     */
    public function reset(): void
    {
        foreach ($this->metrics as $metric) {
            $metric['collector']->reset();
        }
    }
}

/**
 * Counter metric
 */
class Counter
{
    protected string $name;
    protected string $help;
    protected array $labelNames;
    protected array $values = [];
    
    public function __construct(string $name, string $help, array $labelNames = [])
    {
        $this->name = $name;
        $this->help = $help;
        $this->labelNames = $labelNames;
    }
    
    public function inc(array $labels = [], float $value = 1): void
    {
        $key = $this->getLabelKey($labels);
        
        if (!isset($this->values[$key])) {
            $this->values[$key] = [
                'labels' => $labels,
                'value' => 0
            ];
        }
        
        $this->values[$key]['value'] += $value;
    }
    
    public function collect(): array
    {
        return array_values($this->values);
    }
    
    public function reset(): void
    {
        $this->values = [];
    }
    
    protected function getLabelKey(array $labels): string
    {
        ksort($labels);
        return json_encode($labels);
    }
}

/**
 * Gauge metric
 */
class Gauge extends Counter
{
    public function set(array $labels = [], float $value = 0): void
    {
        $key = $this->getLabelKey($labels);
        
        $this->values[$key] = [
            'labels' => $labels,
            'value' => $value
        ];
    }
    
    public function inc(array $labels = [], float $value = 1): void
    {
        $key = $this->getLabelKey($labels);
        
        if (!isset($this->values[$key])) {
            $this->values[$key] = [
                'labels' => $labels,
                'value' => 0
            ];
        }
        
        $this->values[$key]['value'] += $value;
    }
    
    public function dec(array $labels = [], float $value = 1): void
    {
        $this->inc($labels, -$value);
    }
}

/**
 * Histogram metric
 */
class Histogram
{
    protected string $name;
    protected string $help;
    protected array $labelNames;
    protected array $buckets;
    protected array $values = [];
    
    protected static array $defaultBuckets = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10];
    
    public function __construct(string $name, string $help, array $labelNames = [], ?array $buckets = null)
    {
        $this->name = $name;
        $this->help = $help;
        $this->labelNames = $labelNames;
        $this->buckets = $buckets ?? self::$defaultBuckets;
        
        // Ensure buckets are sorted
        sort($this->buckets);
    }
    
    public function observe(array $labels = [], float $value = 0): void
    {
        $key = $this->getLabelKey($labels);
        
        if (!isset($this->values[$key])) {
            $this->values[$key] = [
                'labels' => $labels,
                'buckets' => array_fill_keys(array_merge($this->buckets, ['+Inf']), 0),
                'count' => 0,
                'sum' => 0
            ];
        }
        
        // Update buckets
        foreach ($this->buckets as $bucket) {
            if ($value <= $bucket) {
                $this->values[$key]['buckets'][(string)$bucket]++;
            }
        }
        $this->values[$key]['buckets']['+Inf']++;
        
        // Update count and sum
        $this->values[$key]['count']++;
        $this->values[$key]['sum'] += $value;
    }
    
    public function collect(): array
    {
        return array_values($this->values);
    }
    
    public function reset(): void
    {
        $this->values = [];
    }
    
    protected function getLabelKey(array $labels): string
    {
        ksort($labels);
        return json_encode($labels);
    }
}

/**
 * Summary metric
 */
class Summary
{
    protected string $name;
    protected string $help;
    protected array $labelNames;
    protected array $quantiles;
    protected array $values = [];
    protected int $maxAge = 600; // 10 minutes
    protected int $ageBuckets = 5;
    
    protected static array $defaultQuantiles = [0.5, 0.9, 0.99];
    
    public function __construct(string $name, string $help, array $labelNames = [], ?array $quantiles = null)
    {
        $this->name = $name;
        $this->help = $help;
        $this->labelNames = $labelNames;
        $this->quantiles = $quantiles ?? self::$defaultQuantiles;
    }
    
    public function observe(array $labels = [], float $value = 0): void
    {
        $key = $this->getLabelKey($labels);
        $time = time();
        
        if (!isset($this->values[$key])) {
            $this->values[$key] = [
                'labels' => $labels,
                'observations' => [],
                'count' => 0,
                'sum' => 0
            ];
        }
        
        // Add observation with timestamp
        $this->values[$key]['observations'][] = [
            'value' => $value,
            'time' => $time
        ];
        
        // Clean old observations
        $this->cleanOldObservations($key, $time);
        
        // Update count and sum
        $this->values[$key]['count']++;
        $this->values[$key]['sum'] += $value;
    }
    
    public function collect(): array
    {
        $samples = [];
        
        foreach ($this->values as $data) {
            $quantileValues = $this->calculateQuantiles($data['observations']);
            
            $samples[] = [
                'labels' => $data['labels'],
                'quantiles' => $quantileValues,
                'count' => $data['count'],
                'sum' => $data['sum']
            ];
        }
        
        return $samples;
    }
    
    public function reset(): void
    {
        $this->values = [];
    }
    
    protected function getLabelKey(array $labels): string
    {
        ksort($labels);
        return json_encode($labels);
    }
    
    protected function cleanOldObservations(string $key, int $currentTime): void
    {
        $cutoff = $currentTime - $this->maxAge;
        
        $this->values[$key]['observations'] = array_filter(
            $this->values[$key]['observations'],
            function($obs) use ($cutoff) {
                return $obs['time'] > $cutoff;
            }
        );
    }
    
    protected function calculateQuantiles(array $observations): array
    {
        if (empty($observations)) {
            return array_fill_keys($this->quantiles, 0);
        }
        
        // Extract values and sort
        $values = array_column($observations, 'value');
        sort($values);
        
        $quantileValues = [];
        $count = count($values);
        
        foreach ($this->quantiles as $quantile) {
            $index = (int) ceil($quantile * $count) - 1;
            $quantileValues[(string)$quantile] = $values[$index];
        }
        
        return $quantileValues;
    }
}