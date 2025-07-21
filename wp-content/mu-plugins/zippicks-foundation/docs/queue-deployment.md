# ZipPicks Queue System - Production Deployment Guide

## Table of Contents
1. [Overview](#overview)
2. [System Requirements](#system-requirements)
3. [Supervisor Configuration](#supervisor-configuration)
4. [Systemd Service Setup](#systemd-service-setup)
5. [Docker Deployment](#docker-deployment)
6. [Monitoring Integration](#monitoring-integration)
7. [Scaling Strategies](#scaling-strategies)
8. [Troubleshooting](#troubleshooting)
9. [Performance Tuning](#performance-tuning)

## Overview

The ZipPicks queue system is designed to process millions of jobs daily across taste graph calculations, email campaigns, and business analytics. This guide covers production deployment patterns for maximum reliability and performance.

## System Requirements

### Minimum Requirements
- PHP 8.1+ with extensions: `pcntl`, `posix`, `redis`
- MySQL 8.0+ or MariaDB 10.5+
- Redis 6.0+ (for caching and rate limiting)
- 4GB RAM per worker node
- 2 CPU cores per worker node

### Recommended Production Setup
- PHP 8.3 with OPcache enabled
- MySQL 8.0 with dedicated queue database
- Redis 7.0 cluster for high availability
- 8GB RAM per worker node
- 4-8 CPU cores per worker node
- SSD storage for logs and temporary files

## Supervisor Configuration

Supervisor is the recommended process manager for queue workers in production.

### Installation
```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor

# Start supervisor
sudo systemctl enable supervisord
sudo systemctl start supervisord
```

### Basic Worker Configuration

Create `/etc/supervisor/conf.d/zippicks-queue.conf`:

```ini
[program:zippicks-queue-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zippicks/wp queue:work --queue=default --max-jobs=1000 --max-time=3600 --sleep=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/zippicks/queue-default.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=10
stopwaitsecs=600
stopsignal=TERM
environment=WP_ENV="production"
```

### Priority Queue Configuration

For high-priority jobs, create separate workers:

```ini
[program:zippicks-queue-high]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zippicks/wp queue:work --queue=high --max-jobs=100 --max-time=600 --sleep=1 --timeout=300
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/zippicks/queue-high.log
priority=900
```

### Email Queue Configuration

Dedicated workers for email processing:

```ini
[program:zippicks-queue-emails]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zippicks/wp queue:work --queue=emails --max-jobs=500 --max-time=1800 --memory=256
autostart=true
autorestart=true
user=www-data
numprocs=6
redirect_stderr=true
stdout_logfile=/var/log/zippicks/queue-emails.log
environment=WP_ENV="production",QUEUE_CONNECTION="database"
```

### Analytics Queue Configuration

Heavy processing jobs with extended timeouts:

```ini
[program:zippicks-queue-analytics]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zippicks/wp queue:work --queue=analytics --max-jobs=50 --timeout=600 --memory=512
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/zippicks/queue-analytics.log
nice=10
```

### Supervisor Group Configuration

Group all workers for easier management:

```ini
[group:zippicks-workers]
programs=zippicks-queue-default,zippicks-queue-high,zippicks-queue-emails,zippicks-queue-analytics
priority=999
```

### Managing Workers

```bash
# Update supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start all workers
sudo supervisorctl start zippicks-workers:*

# Stop all workers gracefully
sudo supervisorctl stop zippicks-workers:*

# Restart specific worker group
sudo supervisorctl restart zippicks-queue-default:*

# View worker status
sudo supervisorctl status

# Tail worker logs
sudo supervisorctl tail -f zippicks-queue-default:00
```

## Systemd Service Setup

Alternative to Supervisor using systemd (for modern Linux distributions).

### Create Service File

Create `/etc/systemd/system/zippicks-queue@.service`:

```ini
[Unit]
Description=ZipPicks Queue Worker %i
After=network.target mysql.service redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/zippicks
ExecStart=/usr/bin/php /var/www/zippicks/wp queue:work --queue=%i --max-jobs=1000 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=append:/var/log/zippicks/queue-%i.log
StandardError=append:/var/log/zippicks/queue-%i-error.log

# Hardening
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/www/zippicks/wp-content/uploads
ReadWritePaths=/var/log/zippicks

[Install]
WantedBy=multi-user.target
```

### Enable Multiple Workers

```bash
# Enable 4 default queue workers
sudo systemctl enable zippicks-queue@default.service
sudo systemctl enable zippicks-queue@default-2.service
sudo systemctl enable zippicks-queue@default-3.service
sudo systemctl enable zippicks-queue@default-4.service

# Enable specialized workers
sudo systemctl enable zippicks-queue@high.service
sudo systemctl enable zippicks-queue@emails.service
sudo systemctl enable zippicks-queue@analytics.service

# Start all workers
sudo systemctl start zippicks-queue@{default,default-2,default-3,default-4,high,emails,analytics}.service

# Check status
sudo systemctl status 'zippicks-queue@*'
```

### Systemd Timer for Scheduled Jobs

Create `/etc/systemd/system/zippicks-scheduled.timer`:

```ini
[Unit]
Description=ZipPicks Scheduled Jobs Timer
Requires=zippicks-scheduled.service

[Timer]
OnCalendar=*-*-* *:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

Create `/etc/systemd/system/zippicks-scheduled.service`:

```ini
[Unit]
Description=ZipPicks Scheduled Jobs

[Service]
Type=oneshot
User=www-data
ExecStart=/usr/bin/php /var/www/zippicks/wp cron event run --all
```

## Docker Deployment

### Dockerfile for Queue Workers

```dockerfile
FROM php:8.3-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    supervisor \
    && docker-php-ext-install \
    pdo_mysql \
    intl \
    opcache \
    pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

# Copy application
COPY . /var/www/zippicks
WORKDIR /var/www/zippicks

# Copy supervisor config
COPY docker/supervisor/queue.conf /etc/supervisor/conf.d/

# Create log directory
RUN mkdir -p /var/log/zippicks

# Run supervisor
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
```

### Docker Compose Configuration

```yaml
version: '3.8'

services:
  queue-default:
    build: .
    environment:
      - WP_ENV=production
      - QUEUE_CONNECTION=redis
      - REDIS_HOST=redis
      - DB_HOST=mysql
    volumes:
      - ./logs:/var/log/zippicks
    depends_on:
      - redis
      - mysql
    deploy:
      replicas: 4
      resources:
        limits:
          cpus: '2'
          memory: 512M
    restart: unless-stopped

  queue-high-priority:
    build: .
    command: php wp queue:work --queue=high --max-jobs=100
    environment:
      - WP_ENV=production
      - QUEUE_PRIORITY=high
    deploy:
      replicas: 2
      resources:
        limits:
          cpus: '1'
          memory: 256M

  redis:
    image: redis:7-alpine
    volumes:
      - redis-data:/data
    deploy:
      resources:
        limits:
          memory: 2G

volumes:
  redis-data:
```

## Monitoring Integration

### Prometheus Configuration

Add to `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'zippicks_queue'
    static_configs:
      - targets: ['localhost:9090']
    metrics_path: '/wp-json/zippicks/v1/metrics/queue'
```

### Grafana Dashboard

Import the ZipPicks Queue Dashboard JSON:

```json
{
  "dashboard": {
    "title": "ZipPicks Queue Monitoring",
    "panels": [
      {
        "title": "Queue Depth",
        "targets": [
          {
            "expr": "zippicks_queue_size{queue=\"default\"}"
          }
        ]
      },
      {
        "title": "Processing Rate",
        "targets": [
          {
            "expr": "rate(zippicks_jobs_processed_total[5m])"
          }
        ]
      },
      {
        "title": "Failed Jobs",
        "targets": [
          {
            "expr": "zippicks_jobs_failed_total"
          }
        ]
      },
      {
        "title": "Worker Memory Usage",
        "targets": [
          {
            "expr": "zippicks_worker_memory_bytes"
          }
        ]
      }
    ]
  }
}
```

### AlertManager Rules

Create `queue-alerts.yml`:

```yaml
groups:
  - name: queue_alerts
    rules:
      - alert: HighQueueDepth
        expr: zippicks_queue_size > 10000
        for: 5m
        annotations:
          summary: "Queue depth is too high"
          description: "Queue {{ $labels.queue }} has {{ $value }} jobs pending"

      - alert: HighFailureRate
        expr: rate(zippicks_jobs_failed_total[5m]) > 0.1
        for: 5m
        annotations:
          summary: "High job failure rate"
          description: "Failure rate is {{ $value }} jobs/sec"

      - alert: NoActiveWorkers
        expr: zippicks_active_workers == 0
        for: 1m
        annotations:
          summary: "No active queue workers"
          description: "All queue workers are down"
```

### Custom Health Check Endpoint

Add to your WordPress installation:

```php
add_action('rest_api_init', function() {
    register_rest_route('zippicks/v1', '/health/queue', [
        'methods' => 'GET',
        'callback' => function() {
            $monitor = zippicks_foundation()->get('queue.monitor');
            $health = $monitor->getHealthStatus();
            
            return new WP_REST_Response([
                'status' => $health['healthy'] ? 'healthy' : 'unhealthy',
                'metrics' => [
                    'queue_depth' => $health['metrics']['queue_depth'],
                    'failure_rate' => $health['metrics']['failure_rate'],
                    'active_workers' => $health['metrics']['active_workers'],
                ],
                'timestamp' => current_time('c'),
            ], $health['healthy'] ? 200 : 503);
        },
        'permission_callback' => '__return_true',
    ]);
});
```

## Scaling Strategies

### Horizontal Scaling

#### 1. Database Queue Optimization

```sql
-- Add indexes for better performance
ALTER TABLE wp_zippicks_jobs 
ADD INDEX idx_queue_reserved (queue, reserved_at),
ADD INDEX idx_available_at (available_at),
ADD INDEX idx_priority_available (priority DESC, available_at);

-- Partition by date for large tables
ALTER TABLE wp_zippicks_jobs
PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) (
    PARTITION p_2024_01 VALUES LESS THAN (UNIX_TIMESTAMP('2024-02-01')),
    PARTITION p_2024_02 VALUES LESS THAN (UNIX_TIMESTAMP('2024-03-01')),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

#### 2. Redis Queue Configuration

Switch to Redis for better performance:

```php
// wp-config.php
define('QUEUE_CONNECTION', 'redis');
define('REDIS_HOST', 'redis-cluster.example.com');
define('REDIS_PORT', 6379);
define('REDIS_QUEUE_PREFIX', 'zippicks_queue_');
```

#### 3. Multi-Server Deployment

**Load Balancer Configuration** (HAProxy):

```
backend queue_workers
    balance roundrobin
    server worker1 10.0.1.10:80 check
    server worker2 10.0.1.11:80 check
    server worker3 10.0.1.12:80 check
    server worker4 10.0.1.13:80 check
```

**Shared Storage** (NFS):

```bash
# Mount shared uploads directory
sudo mount -t nfs storage:/uploads /var/www/zippicks/wp-content/uploads
```

### Vertical Scaling

#### Worker Tuning

```bash
# High-memory jobs
php wp queue:work --queue=analytics --memory=1024 --timeout=900

# High-throughput jobs
php wp queue:work --queue=emails --max-jobs=5000 --sleep=1

# CPU-intensive jobs
nice -n 10 php wp queue:work --queue=ml --max-jobs=10
```

### Queue Prioritization

```php
// Dispatch with priority
dispatch(new ImportantJob())->onQueue('high')->priority(10);
dispatch(new RegularJob())->onQueue('default')->priority(5);
dispatch(new BackgroundJob())->onQueue('low')->priority(1);
```

## Troubleshooting

### Common Issues

#### 1. Workers Not Processing Jobs

```bash
# Check worker status
sudo supervisorctl status

# Check PHP errors
tail -f /var/log/php/error.log

# Verify queue connection
php wp eval 'var_dump(zippicks_foundation()->queue()->size());'
```

#### 2. High Memory Usage

```bash
# Monitor memory usage
watch -n 1 'ps aux | grep "queue:work" | awk "{sum+=\$6} END {print sum/1024 \" MB\"}"'

# Restart workers periodically
*/30 * * * * supervisorctl restart zippicks-workers:*
```

#### 3. Database Locks

```sql
-- Check for locks
SHOW PROCESSLIST;

-- Kill stuck queries
KILL QUERY <process_id>;

-- Check table status
SHOW TABLE STATUS LIKE 'wp_zippicks_jobs';
```

#### 4. Redis Connection Issues

```bash
# Test Redis connection
redis-cli ping

# Monitor Redis
redis-cli monitor

# Check Redis memory
redis-cli info memory
```

### Debug Mode

Enable debug logging:

```php
// wp-config.php
define('ZIPPICKS_QUEUE_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Queue Inspection

```bash
# View queue statistics
php wp queue:stats

# List failed jobs
php wp queue:failed

# Retry specific job
php wp queue:retry <job_id>

# Clear all jobs (careful!)
php wp queue:clear --force
```

## Performance Tuning

### PHP Configuration

```ini
; /etc/php/8.3/cli/conf.d/99-queue.ini
memory_limit = 512M
max_execution_time = 0
opcache.enable_cli = 1
opcache.jit = 1255
opcache.jit_buffer_size = 128M
```

### MySQL Tuning

```ini
; /etc/mysql/mysql.conf.d/queue.cnf
[mysqld]
innodb_buffer_pool_size = 4G
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
max_connections = 500
```

### Redis Optimization

```conf
# /etc/redis/redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru
save ""
appendonly no
```

### System Tuning

```bash
# Increase file descriptors
echo "www-data soft nofile 65536" >> /etc/security/limits.conf
echo "www-data hard nofile 65536" >> /etc/security/limits.conf

# Optimize kernel parameters
echo "net.core.somaxconn = 65535" >> /etc/sysctl.conf
echo "vm.overcommit_memory = 1" >> /etc/sysctl.conf
sysctl -p
```

## Security Considerations

### File Permissions

```bash
# Set proper permissions
chown -R www-data:www-data /var/www/zippicks
chmod -R 755 /var/www/zippicks
chmod -R 775 /var/www/zippicks/wp-content/uploads
chmod 600 /var/www/zippicks/wp-config.php
```

### Environment Variables

```bash
# Use environment variables for sensitive data
export DB_PASSWORD='secure_password'
export REDIS_PASSWORD='redis_password'
export QUEUE_ENCRYPTION_KEY='32_character_key'
```

### Network Security

```bash
# Firewall rules
ufw allow from 10.0.0.0/8 to any port 3306  # MySQL
ufw allow from 10.0.0.0/8 to any port 6379  # Redis
ufw deny 3306
ufw deny 6379
```

## Maintenance

### Log Rotation

Create `/etc/logrotate.d/zippicks-queue`:

```
/var/log/zippicks/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        supervisorctl restart zippicks-workers:* > /dev/null
    endscript
}
```

### Database Maintenance

```sql
-- Weekly maintenance
OPTIMIZE TABLE wp_zippicks_jobs;
OPTIMIZE TABLE wp_zippicks_failed_jobs;

-- Clean old jobs (30 days)
DELETE FROM wp_zippicks_jobs 
WHERE status = 'completed' 
AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Backup Strategy

```bash
#!/bin/bash
# backup-queue.sh

# Backup queue tables
mysqldump -u root -p$DB_PASSWORD wordpress \
    wp_zippicks_jobs \
    wp_zippicks_failed_jobs \
    wp_zippicks_job_batches \
    > /backup/queue-$(date +%Y%m%d).sql

# Backup Redis
redis-cli --rdb /backup/redis-$(date +%Y%m%d).rdb

# Upload to S3
aws s3 sync /backup/ s3://zippicks-backups/queue/
```

## Deployment Checklist

- [ ] PHP extensions installed (pcntl, posix, redis)
- [ ] Supervisor/systemd configured and started
- [ ] Log directories created with proper permissions
- [ ] Database indexes added
- [ ] Redis connection tested
- [ ] Monitoring endpoints accessible
- [ ] Alerting rules configured
- [ ] Backup script scheduled
- [ ] Log rotation configured
- [ ] Security hardening applied
- [ ] Performance tuning completed
- [ ] Health checks passing
- [ ] Documentation updated

## Support

For issues or questions:
1. Check worker logs: `/var/log/zippicks/`
2. Review health endpoint: `/wp-json/zippicks/v1/health/queue`
3. Monitor dashboard: `/wp-admin/tools.php?page=zippicks-queue-dashboard`
4. Create issue: https://github.com/zippicks/foundation/issues

---
*Last updated: December 2024*  
*Version: 1.0*