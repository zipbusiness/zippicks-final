# Multi-stage build for optimized production image
FROM wordpress:6.4-php8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    unzip \
    curl \
    bash \
    mariadb-client \
    redis \
    imagemagick \
    imagemagick-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    freetype-dev \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo_mysql \
        opcache \
        intl \
        zip \
        exif \
        bcmath \
        pcntl \
        sockets

# Install PHP extensions via PECL
RUN pecl install redis apcu imagick \
    && docker-php-ext-enable redis apcu imagick

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Production build stage
FROM base AS production

# Set working directory
WORKDIR /var/www/html

# Copy custom PHP configuration
COPY k8s/base/configmap.yaml /tmp/
RUN grep -A 100 "99-zippicks.ini: |" /tmp/configmap.yaml | tail -n +2 | sed 's/^    //' > /usr/local/etc/php/conf.d/99-zippicks.ini

# Copy nginx configuration
COPY <<'EOF' /etc/nginx/nginx.conf
user www-data;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 2048;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for" '
                    'rt=$request_time uct="$upstream_connect_time" '
                    'uht="$upstream_header_time" urt="$upstream_response_time"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 50M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    server {
        listen 80 default_server;
        server_name _;
        root /var/www/html;
        index index.php;

        # Health check endpoint
        location = /health {
            access_log off;
            return 200 "healthy\n";
            add_header Content-Type text/plain;
        }

        # Metrics endpoint (internal only)
        location = /metrics {
            allow 10.0.0.0/8;
            allow 172.16.0.0/12;
            deny all;
            try_files $uri /index.php?$args;
        }

        # WordPress permalinks
        location / {
            try_files $uri $uri/ /index.php?$args;
        }

        # PHP processing
        location ~ \.php$ {
            try_files $uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            fastcgi_buffer_size 32k;
            fastcgi_buffers 8 16k;
            fastcgi_read_timeout 300;
        }

        # Deny access to sensitive files
        location ~ /\. {
            deny all;
        }
        location ~ /vendor/ {
            deny all;
        }
        location ~ /\.git/ {
            deny all;
        }
    }
}
EOF

# Copy supervisor configuration
COPY <<'EOF' /etc/supervisor/conf.d/supervisord.conf
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=/usr/local/sbin/php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/php-fpm.log
stderr_logfile=/var/log/supervisor/php-fpm-error.log
priority=1

[program:nginx]
command=/usr/sbin/nginx -g 'daemon off;'
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/nginx.log
stderr_logfile=/var/log/supervisor/nginx-error.log
priority=2

[program:queue-worker]
command=/usr/local/bin/php /var/www/html/wp-content/mu-plugins/zippicks-foundation/bin/queue-worker.php
autostart=true
autorestart=true
stdout_logfile=/var/log/supervisor/queue-worker.log
stderr_logfile=/var/log/supervisor/queue-worker-error.log
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
priority=3
EOF

# Copy WordPress files
COPY wp-content /var/www/html/wp-content

# Create necessary directories
RUN mkdir -p \
    /var/www/html/wp-content/uploads \
    /var/www/html/wp-content/cache \
    /var/www/html/wp-content/mu-plugins/zippicks-foundation/logs \
    /var/log/supervisor \
    /var/log/nginx \
    /var/log/php

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/wp-content/uploads \
    && chmod -R 775 /var/www/html/wp-content/cache \
    && chmod -R 775 /var/www/html/wp-content/mu-plugins/zippicks-foundation/logs

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Create queue worker script
COPY <<'EOF' /var/www/html/wp-content/mu-plugins/zippicks-foundation/bin/queue-worker.php
#!/usr/bin/env php
<?php
/**
 * Queue Worker for ZipPicks Foundation
 */

// Bootstrap WordPress
require_once '/var/www/html/wp-load.php';

// Get queue worker instance
$worker = app('queue.worker');

// Configure worker
$worker->setMaxJobs(1000);
$worker->setMaxTime(3600); // 1 hour
$worker->setMemoryLimit(256); // 256MB

// Start processing jobs
echo "Queue worker started at " . date('Y-m-d H:i:s') . PHP_EOL;
$worker->daemon('default', [
    'sleep' => 3,
    'tries' => 3,
    'timeout' => 300,
]);
EOF

RUN chmod +x /var/www/html/wp-content/mu-plugins/zippicks-foundation/bin/queue-worker.php

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Expose port
EXPOSE 80

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Development build stage
FROM base AS development

WORKDIR /var/www/html

# Install development tools
RUN apk add --no-cache \
    vim \
    nano \
    htop \
    nodejs \
    npm

# Install Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Development PHP configuration
COPY <<'EOF' /usr/local/etc/php/conf.d/99-development.ini
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
log_errors = On

xdebug.mode = develop,debug,coverage
xdebug.client_host = host.docker.internal
xdebug.client_port = 9003
xdebug.start_with_request = yes
EOF

# Copy development entrypoint
COPY <<'EOF' /usr/local/bin/docker-entrypoint-dev.sh
#!/bin/bash
set -e

# Install dependencies if needed
if [ ! -d "vendor" ]; then
    composer install
fi

# Start PHP built-in server
php -S 0.0.0.0:80 -t /var/www/html
EOF

RUN chmod +x /usr/local/bin/docker-entrypoint-dev.sh

EXPOSE 80
CMD ["/usr/local/bin/docker-entrypoint-dev.sh"]