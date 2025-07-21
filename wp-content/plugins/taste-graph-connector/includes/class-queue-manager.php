<?php
/**
 * Queue Manager Class
 * 
 * Manages failed API calls with Redis and database fallback
 * Ensures no tracking data is lost during API downtime
 * 
 * @package TasteGraphConnector
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TGC_Queue_Manager class
 */
class TGC_Queue_Manager {
    
    /**
     * Redis instance
     */
    private $redis = null;
    
    /**
     * Whether Redis is available
     */
    private $use_redis = false;
    
    /**
     * Redis queue key
     */
    const REDIS_QUEUE_KEY = 'tgc_api_queue';
    
    /**
     * Max retry attempts
     */
    const MAX_RETRIES = 5;
    
    /**
     * Retry delay multiplier (exponential backoff)
     */
    const RETRY_DELAY_BASE = 60; // 1 minute base
    
    /**
     * Batch processing size
     */
    const BATCH_SIZE = 10;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_redis();
    }
    
    /**
     * Initialize Redis connection
     */
    private function init_redis() {
        if (!defined('TGC_REDIS_ENABLED') || !TGC_REDIS_ENABLED) {
            return;
        }
        
        try {
            $this->redis = new Redis();
            $connected = $this->redis->connect(
                TGC_REDIS_HOST,
                TGC_REDIS_PORT,
                2.0 // 2 second timeout
            );
            
            if ($connected) {
                // Test connection
                $this->redis->ping();
                $this->use_redis = true;
                
                // Set Redis options
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
            }
        } catch (Exception $e) {
            $this->use_redis = false;
            error_log('TGC Queue: Redis connection failed - ' . $e->getMessage());
        }
    }
    
    /**
     * Queue a failed API call
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $payload Request payload
     * @param array $headers Optional headers
     * @return bool Success status
     */
    public function queue_failed_call($endpoint, $method, $payload, $headers = array()) {
        $queue_item = array(
            'id' => $this->generate_queue_id(),
            'endpoint' => $endpoint,
            'method' => $method,
            'payload' => $payload,
            'headers' => $headers,
            'retry_count' => 0,
            'created_at' => time(),
            'next_retry_at' => time() + self::RETRY_DELAY_BASE,
            'error_message' => ''
        );
        
        if ($this->use_redis) {
            return $this->queue_to_redis($queue_item);
        } else {
            return $this->queue_to_database($queue_item);
        }
    }
    
    /**
     * Queue item to Redis
     * 
     * @param array $item Queue item
     * @return bool Success status
     */
    private function queue_to_redis($item) {
        try {
            return $this->redis->lpush(self::REDIS_QUEUE_KEY, $item);
        } catch (Exception $e) {
            error_log('TGC Queue: Redis queue failed - ' . $e->getMessage());
            // Fallback to database
            return $this->queue_to_database($item);
        }
    }
    
    /**
     * Queue item to database
     * 
     * @param array $item Queue item
     * @return bool Success status
     */
    private function queue_to_database($item) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tgc_api_queue';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'endpoint' => $item['endpoint'],
                'method' => $item['method'],
                'payload' => json_encode($item['payload']),
                'headers' => json_encode($item['headers']),
                'status' => 'pending',
                'retry_count' => $item['retry_count'],
                'created_at' => gmdate('Y-m-d H:i:s', $item['created_at']),
                'next_retry_at' => gmdate('Y-m-d H:i:s', $item['next_retry_at']),
                'error_message' => $item['error_message']
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * Process queue items
     * 
     * @return array Processing results
     */
    public function process_queue() {
        $results = array(
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'requeued' => 0
        );
        
        // Process Redis queue first
        if ($this->use_redis) {
            $redis_results = $this->process_redis_queue();
            $results = $this->merge_results($results, $redis_results);
        }
        
        // Process database queue
        $db_results = $this->process_database_queue();
        $results = $this->merge_results($results, $db_results);
        
        // Log results if in debug mode
        if (get_option('tgc_debug_mode', 'no') === 'yes') {
            error_log(sprintf(
                'TGC Queue: Processed %d items - Success: %d, Failed: %d, Requeued: %d',
                $results['processed'],
                $results['success'],
                $results['failed'],
                $results['requeued']
            ));
        }
        
        return $results;
    }
    
    /**
     * Process Redis queue
     * 
     * @return array Processing results
     */
    private function process_redis_queue() {
        $results = array(
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'requeued' => 0
        );
        
        if (!$this->use_redis) {
            return $results;
        }
        
        try {
            $queue_length = $this->redis->llen(self::REDIS_QUEUE_KEY);
            $items_to_process = min($queue_length, self::BATCH_SIZE);
            
            for ($i = 0; $i < $items_to_process; $i++) {
                $item = $this->redis->rpop(self::REDIS_QUEUE_KEY);
                if (!$item) {
                    break;
                }
                
                $result = $this->process_queue_item($item);
                $results['processed']++;
                
                if ($result === 'success') {
                    $results['success']++;
                } elseif ($result === 'requeued') {
                    $results['requeued']++;
                    // Re-add to queue with updated retry info
                    $this->redis->lpush(self::REDIS_QUEUE_KEY, $item);
                } else {
                    $results['failed']++;
                }
            }
        } catch (Exception $e) {
            error_log('TGC Queue: Redis processing failed - ' . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Process database queue
     * 
     * @return array Processing results
     */
    private function process_database_queue() {
        global $wpdb;
        
        $results = array(
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'requeued' => 0
        );
        
        $table_name = $wpdb->prefix . 'tgc_api_queue';
        
        // Get pending items that are due for retry
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE status = 'pending' 
             AND next_retry_at <= %s 
             ORDER BY created_at ASC 
             LIMIT %d",
            current_time('mysql'),
            self::BATCH_SIZE
        ));
        
        foreach ($items as $item) {
            // Convert database row to queue item array
            $queue_item = array(
                'id' => $item->id,
                'endpoint' => $item->endpoint,
                'method' => $item->method,
                'payload' => json_decode($item->payload, true),
                'headers' => json_decode($item->headers, true),
                'retry_count' => $item->retry_count,
                'created_at' => strtotime($item->created_at),
                'error_message' => $item->error_message
            );
            
            $result = $this->process_queue_item($queue_item);
            $results['processed']++;
            
            if ($result === 'success') {
                $results['success']++;
                // Mark as completed
                $wpdb->update(
                    $table_name,
                    array('status' => 'completed'),
                    array('id' => $item->id),
                    array('%s'),
                    array('%d')
                );
            } elseif ($result === 'requeued') {
                $results['requeued']++;
                // Update retry info
                $wpdb->update(
                    $table_name,
                    array(
                        'retry_count' => $queue_item['retry_count'],
                        'next_retry_at' => gmdate('Y-m-d H:i:s', $queue_item['next_retry_at']),
                        'error_message' => $queue_item['error_message']
                    ),
                    array('id' => $item->id),
                    array('%d', '%s', '%s'),
                    array('%d')
                );
            } else {
                $results['failed']++;
                // Mark as failed
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'failed',
                        'error_message' => $queue_item['error_message']
                    ),
                    array('id' => $item->id),
                    array('%s', '%s'),
                    array('%d')
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Process single queue item
     * 
     * @param array $item Queue item
     * @return string Processing result: 'success', 'requeued', or 'failed'
     */
    private function process_queue_item(&$item) {
        // Skip if too old (7 days)
        if (time() - $item['created_at'] > 604800) {
            $item['error_message'] = 'Expired after 7 days';
            return 'failed';
        }
        
        // Make API request
        $api_client = new TGC_API_Client();
        
        // Use reflection to call private method (not ideal but necessary here)
        $reflection = new ReflectionMethod($api_client, 'make_request');
        $reflection->setAccessible(true);
        
        $response = $reflection->invoke(
            $api_client,
            $item['endpoint'],
            $item['method'],
            $item['payload']
        );
        
        if ($response !== false) {
            return 'success';
        }
        
        // Increment retry count
        $item['retry_count']++;
        
        // Check if we should retry
        if ($item['retry_count'] >= self::MAX_RETRIES) {
            $item['error_message'] = 'Max retries exceeded';
            return 'failed';
        }
        
        // Calculate next retry time (exponential backoff)
        $delay = self::RETRY_DELAY_BASE * pow(2, $item['retry_count'] - 1);
        $item['next_retry_at'] = time() + $delay;
        
        return 'requeued';
    }
    
    /**
     * Get queue statistics
     * 
     * @return array Queue statistics
     */
    public function get_queue_stats() {
        $stats = array(
            'redis_count' => 0,
            'database_pending' => 0,
            'database_failed' => 0,
            'database_completed' => 0,
            'total_pending' => 0
        );
        
        // Redis stats
        if ($this->use_redis) {
            try {
                $stats['redis_count'] = $this->redis->llen(self::REDIS_QUEUE_KEY);
            } catch (Exception $e) {
                // Ignore Redis errors
            }
        }
        
        // Database stats
        global $wpdb;
        $table_name = $wpdb->prefix . 'tgc_api_queue';
        
        $db_stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
             FROM {$table_name} 
             GROUP BY status"
        );
        
        foreach ($db_stats as $stat) {
            switch ($stat->status) {
                case 'pending':
                    $stats['database_pending'] = (int)$stat->count;
                    break;
                case 'failed':
                    $stats['database_failed'] = (int)$stat->count;
                    break;
                case 'completed':
                    $stats['database_completed'] = (int)$stat->count;
                    break;
            }
        }
        
        $stats['total_pending'] = $stats['redis_count'] + $stats['database_pending'];
        
        return $stats;
    }
    
    /**
     * Get queue count
     * 
     * @return int Total pending items
     */
    public function get_queue_count() {
        $stats = $this->get_queue_stats();
        return $stats['total_pending'];
    }
    
    /**
     * Clear old completed/failed items
     * 
     * @param int $days_old Days to keep
     * @return int Number of items cleared
     */
    public function clear_old_items($days_old = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tgc_api_queue';
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE status IN ('completed', 'failed') 
             AND created_at < %s",
            $cutoff_date
        ));
        
        return $deleted;
    }
    
    /**
     * Generate unique queue ID
     * 
     * @return string Queue ID
     */
    private function generate_queue_id() {
        return 'tgc_' . time() . '_' . wp_generate_password(8, false);
    }
    
    /**
     * Merge result arrays
     * 
     * @param array $results1 First results
     * @param array $results2 Second results
     * @return array Merged results
     */
    private function merge_results($results1, $results2) {
        foreach ($results2 as $key => $value) {
            $results1[$key] += $value;
        }
        return $results1;
    }
}