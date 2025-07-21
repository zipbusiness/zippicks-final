# Enterprise Rate Limiting Deployment Guide

**ZipPicks Foundation - Phase 4**  
**Target Scale: 10M+ requests/second for $100B platform**

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Infrastructure Requirements](#infrastructure-requirements)
4. [Redis Deployment](#redis-deployment)
5. [Database Configuration](#database-configuration)
6. [Load Balancing](#load-balancing)
7. [Monitoring & Alerting](#monitoring--alerting)
8. [Performance Tuning](#performance-tuning)
9. [Disaster Recovery](#disaster-recovery)
10. [Scaling Strategies](#scaling-strategies)

## Overview

The ZipPicks rate limiting system is designed to protect our $100B platform while enabling tier-based monetization. It supports:

- **10M+ operations/second** across distributed infrastructure
- **<1ms latency** at 99th percentile
- **Multi-region** deployment with automatic failover
- **Cost-based metering** for AI operations
- **Tier-based limits** driving $60-100M ARR

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                   Global Load Balancer                       │
│                    (Cloudflare/AWS ALB)                     │
└─────────────────┬───────────────────────┬──────────────────┘
                  │                       │
     ┌────────────▼──────────┐ ┌─────────▼──────────────┐
     │   US-East Region      │ │   US-West Region       │
     │                       │ │                        │
     │ ┌─────────────────┐  │ │ ┌─────────────────┐   │
     │ │ Redis Cluster    │  │ │ │ Redis Cluster    │   │
     │ │ (Primary)        │  │ │ │ (Secondary)      │   │
     │ └────────┬────────┘  │ │ └────────┬────────┘   │
     │          │           │ │          │             │
     │ ┌────────▼────────┐  │ │ ┌────────▼────────┐   │
     │ │ App Servers     │  │ │ │ App Servers     │   │
     │ │ (Auto-scaling)  │  │ │ │ (Auto-scaling)  │   │
     │ └────────┬────────┘  │ │ └────────┬────────┘   │
     │          │           │ │          │             │
     │ ┌────────▼────────┐  │ │ ┌────────▼────────┐   │
     │ │ MySQL Cluster   │  │ │ │ MySQL Cluster   │   │
     │ │ (Fallback)      │  │ │ │ (Fallback)      │   │
     │ └─────────────────┘  │ │ └─────────────────┘   │
     └───────────────────────┘ └───────────────────────┘
```

## Infrastructure Requirements

### Minimum Production Requirements

```yaml
# Per Region
redis_nodes: 6  # 3 primary, 3 replica
app_servers: 10 # Auto-scaling 10-100
database_nodes: 3 # Primary + 2 replicas
load_balancers: 2 # Active/passive

# Resources per Node
redis:
  cpu: 16 cores
  memory: 64GB
  network: 10Gbps
  storage: 500GB NVMe SSD

app_server:
  cpu: 8 cores
  memory: 32GB
  network: 10Gbps

database:
  cpu: 32 cores
  memory: 128GB
  network: 10Gbps
  storage: 2TB NVMe SSD
```

## Redis Deployment

### Redis Cluster Configuration

```bash
# /etc/redis/redis.conf
bind 0.0.0.0
protected-mode yes
port 6379
tcp-backlog 511
timeout 0
tcp-keepalive 300

# Performance
maxmemory 50gb
maxmemory-policy allkeys-lru
hz 100

# Persistence (disable for pure cache)
save ""
appendonly no

# Cluster
cluster-enabled yes
cluster-config-file nodes.conf
cluster-node-timeout 5000
cluster-require-full-coverage no
```

### Redis Lua Scripts

Deploy these Lua scripts for atomic operations:

```lua
-- sliding_window.lua
local key = KEYS[1]
local limit = tonumber(ARGV[1])
local window = tonumber(ARGV[2])
local now = tonumber(ARGV[3])

-- Remove old entries
redis.call('ZREMRANGEBYSCORE', key, 0, now - window)

-- Count current
local current = redis.call('ZCARD', key)

if current < limit then
    redis.call('ZADD', key, now, now)
    redis.call('EXPIRE', key, window)
    return {1, limit - current - 1}
else
    return {0, 0}
end
```

Load scripts on startup:

```bash
redis-cli SCRIPT LOAD "$(cat sliding_window.lua)"
```

### Redis Sentinel Setup

```yaml
# sentinel.conf
port 26379
sentinel monitor mymaster 10.0.1.10 6379 2
sentinel down-after-milliseconds mymaster 5000
sentinel parallel-syncs mymaster 1
sentinel failover-timeout mymaster 10000
```

## Database Configuration

### Schema

```sql
CREATE TABLE `wp_zippicks_rate_limits` (
  `key` VARCHAR(255) NOT NULL,
  `value` INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` INT UNSIGNED NOT NULL,
  `metadata` TEXT NULL,
  PRIMARY KEY (`key`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Partition by date for easy cleanup
ALTER TABLE wp_zippicks_rate_limits 
PARTITION BY RANGE (expires_at) (
    PARTITION p0 VALUES LESS THAN (UNIX_TIMESTAMP('2025-01-01')),
    PARTITION p1 VALUES LESS THAN (UNIX_TIMESTAMP('2025-02-01')),
    PARTITION p2 VALUES LESS THAN (UNIX_TIMESTAMP('2025-03-01')),
    PARTITION p3 VALUES LESS THAN MAXVALUE
);
```

### MySQL Optimization

```sql
-- my.cnf optimizations
[mysqld]
innodb_buffer_pool_size = 96G
innodb_log_file_size = 2G
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_file_per_table = 1

# Rate limit specific
innodb_lock_wait_timeout = 5
innodb_rollback_on_timeout = 1
```

## Load Balancing

### HAProxy Configuration

```nginx
global
    maxconn 100000
    tune.ssl.default-dh-param 2048

defaults
    mode http
    timeout connect 5000ms
    timeout client 30000ms
    timeout server 30000ms
    option httplog

frontend rate_limit_frontend
    bind *:443 ssl crt /etc/ssl/certs/zippicks.pem
    
    # Rate limit headers
    http-response set-header X-RateLimit-Node %[env(HOSTNAME)]
    
    # Sticky sessions for consistent limits
    stick-table type ip size 100k expire 30m
    stick on src
    
    default_backend app_servers

backend app_servers
    balance leastconn
    option httpchk GET /health/rate-limit
    
    server app1 10.0.1.20:80 check
    server app2 10.0.1.21:80 check
    server app3 10.0.1.22:80 check
```

## Monitoring & Alerting

### Prometheus Configuration

```yaml
# prometheus.yml
global:
  scrape_interval: 15s

scrape_configs:
  - job_name: 'rate_limiting'
    static_configs:
      - targets: 
        - 'localhost:9090'
        - 'redis-exporter:9121'
        - 'mysql-exporter:9104'
    
    metric_relabel_configs:
      - source_labels: [__name__]
        regex: 'rate_limit_.*'
        action: keep
```

### Key Metrics to Monitor

```yaml
# Critical Metrics
rate_limit_checks_per_second:
  warning: < 1000000
  critical: < 500000

rate_limit_latency_p99:
  warning: > 1ms
  critical: > 5ms

redis_memory_usage:
  warning: > 80%
  critical: > 90%

circuit_breaker_open:
  critical: true

# Business Metrics
tier_upgrade_rate:
  target: > 5%

api_limit_exceeded_rate:
  warning: > 10%
  
revenue_impact_hourly:
  track: true
  alert_on_drop: 20%
```

### Grafana Dashboard

```json
{
  "dashboard": {
    "title": "ZipPicks Rate Limiting",
    "panels": [
      {
        "title": "Requests per Second",
        "targets": [
          {
            "expr": "rate(rate_limit_checks_total[1m])"
          }
        ]
      },
      {
        "title": "Latency (p50, p95, p99)",
        "targets": [
          {
            "expr": "histogram_quantile(0.5, rate_limit_latency_bucket)"
          }
        ]
      },
      {
        "title": "Tier Distribution",
        "targets": [
          {
            "expr": "rate_limit_tier_count"
          }
        ]
      }
    ]
  }
}
```

## Performance Tuning

### PHP-FPM Configuration

```ini
; /etc/php/8.1/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 500
pm.start_servers = 50
pm.min_spare_servers = 50
pm.max_spare_servers = 200
pm.max_requests = 10000

; OPcache for performance
opcache.enable = 1
opcache.memory_consumption = 512
opcache.max_accelerated_files = 100000
opcache.validate_timestamps = 0
```

### WordPress Optimization

```php
// wp-config.php
define('WP_REDIS_HOST', 'redis-cluster.zippicks.internal');
define('WP_REDIS_CLUSTER', [
    'tcp://10.0.1.10:6379',
    'tcp://10.0.1.11:6379',
    'tcp://10.0.1.12:6379',
]);

// Disable unnecessary features
define('WP_POST_REVISIONS', false);
define('EMPTY_TRASH_DAYS', 7);
define('WP_CRON_LOCK_TIMEOUT', 60);

// Object cache
define('WP_CACHE', true);
```

### Linux Kernel Tuning

```bash
# /etc/sysctl.conf
# Network
net.core.somaxconn = 65535
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.ip_local_port_range = 1024 65535
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 15

# Memory
vm.swappiness = 10
vm.dirty_ratio = 15
vm.dirty_background_ratio = 5

# File descriptors
fs.file-max = 2097152
```

## Disaster Recovery

### Backup Strategy

```bash
#!/bin/bash
# backup-rate-limits.sh

# Redis backup
redis-cli --rdb /backup/redis/dump-$(date +%Y%m%d-%H%M%S).rdb

# MySQL backup
mysqldump --single-transaction \
  --routines --triggers \
  --databases wordpress \
  --tables wp_zippicks_rate_limits \
  > /backup/mysql/rate-limits-$(date +%Y%m%d-%H%M%S).sql

# Upload to S3
aws s3 sync /backup/ s3://zippicks-backups/rate-limits/
```

### Failover Procedures

1. **Redis Failure**
   ```bash
   # Automatic via Sentinel
   # Manual promotion if needed
   redis-cli SLAVEOF NO ONE
   ```

2. **Region Failure**
   ```bash
   # Update DNS to secondary region
   aws route53 change-resource-record-sets \
     --hosted-zone-id Z123456 \
     --change-batch file://failover.json
   ```

3. **Complete System Recovery**
   ```bash
   # 1. Restore Redis from RDB
   redis-cli --pipe < dump.rdb
   
   # 2. Restore MySQL
   mysql wordpress < rate-limits-backup.sql
   
   # 3. Warm cache
   php artisan rate-limit:warm-cache
   ```

## Scaling Strategies

### Horizontal Scaling

```yaml
# Auto-scaling configuration
scaling_triggers:
  cpu_usage: 70%
  requests_per_second: 100000
  response_time_p95: 100ms

scaling_rules:
  scale_up:
    increment: 20%
    cooldown: 300s
  scale_down:
    decrement: 10%
    cooldown: 600s
```

### Vertical Scaling Roadmap

```
Phase 1 (Current): 10M ops/sec
- 6 Redis nodes per region
- 10-100 app servers

Phase 2 (50M ops/sec):
- 12 Redis nodes per region
- 50-500 app servers
- Add third region (EU)

Phase 3 (100M ops/sec):
- Redis cluster sharding
- 100-1000 app servers
- Global edge caching
```

### Cost Optimization

```yaml
# Reserved instances for baseline
baseline_capacity:
  redis: 6 nodes (3yr reserved)
  app: 10 nodes (1yr reserved)
  database: 3 nodes (3yr reserved)

# Spot instances for burst
burst_capacity:
  app: 90 nodes (spot fleet)
  
# Estimated monthly costs
costs:
  baseline: $15,000
  average: $25,000
  peak: $50,000
  
# ROI: $60-100M ARR / $300-600K infrastructure
```

## Security Considerations

### API Key Rate Limiting

```php
// Implement API key-based limits
add_filter('zippicks_rate_limit_key', function($key, $request) {
    if ($apiKey = $request->header('X-API-Key')) {
        return 'api_key:' . $apiKey;
    }
    return $key;
}, 10, 2);
```

### DDoS Protection

```nginx
# nginx.conf
limit_req_zone $binary_remote_addr zone=global:10m rate=100r/s;
limit_req zone=global burst=200 nodelay;

# Cloudflare rules
# - Challenge requests > 1000/min per IP
# - Block known attack patterns
# - Enable Under Attack Mode if needed
```

## Maintenance Procedures

### Daily Tasks
- Monitor Redis memory usage
- Check circuit breaker status
- Review rate limit exceeded logs
- Verify backup completion

### Weekly Tasks
- Analyze tier upgrade opportunities
- Review performance metrics
- Update rate limit configurations
- Clean up expired database entries

### Monthly Tasks
- Load test with 2x current traffic
- Review and optimize Lua scripts
- Update documentation
- Plan capacity for growth

## Troubleshooting

### Common Issues

1. **High Latency**
   ```bash
   # Check Redis latency
   redis-cli --latency-history
   
   # Check slow queries
   redis-cli SLOWLOG GET 10
   ```

2. **Memory Issues**
   ```bash
   # Redis memory stats
   redis-cli INFO memory
   
   # Force eviction if needed
   redis-cli FLUSHDB
   ```

3. **Circuit Breaker Open**
   ```php
   // Reset circuit breaker
   $breaker = app('rate_limiter.circuit_breaker');
   $breaker->reset();
   ```

## Conclusion

This rate limiting system provides the foundation for ZipPicks' $100B platform ambitions. With proper deployment and monitoring, it will:

- Protect resources from abuse
- Enable tier-based monetization
- Scale to billions of requests
- Maintain <1ms latency
- Drive $60-100M ARR

Regular monitoring and optimization are key to maintaining performance as we scale.