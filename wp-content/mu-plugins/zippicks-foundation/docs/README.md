# ZipPicks Foundation

Enterprise foundation layer for the ZipPicks platform. This WordPress must-use plugin provides the core infrastructure for building a scalable, microservices-ready architecture.

## Requirements

- PHP 8.2+
- WordPress 6.4+
- Composer 2.0+

## Installation

1. This plugin should be placed in the `wp-content/mu-plugins` directory
2. Install dependencies:
   ```bash
   composer install
   ```

## Architecture

The foundation provides:

- **PSR-4 Autoloading**: Custom autoloader with Composer support
- **Dependency Injection**: PSR-11 compliant service container
- **Service Providers**: Laravel-inspired service provider pattern
- **Contracts**: Interface-based design for all major components
- **Enterprise Patterns**: Repository, Factory, Strategy patterns ready

## Development

### Running Tests
```bash
make test
```

### Static Analysis
```bash
make analyze
```

### Code Style
```bash
make phpcs
make fix-style
```

## Service Container

The foundation includes a powerful service container for dependency injection:

```php
$container = Foundation::getInstance()->getContainer();

// Bind a service
$container->bind('service', ServiceClass::class);

// Bind a singleton
$container->singleton('cache', CacheManager::class);

// Resolve a service
$service = $container->get('service');
```

## Service Providers

Create custom service providers to register your services:

```php
class CustomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton('custom.service', CustomService::class);
    }

    public function boot(): void
    {
        // Bootstrap your service
    }
}
```

## Configuration

Configuration files are loaded from the `config` directory and are accessible via:

```php
$value = Foundation::getInstance()->config('app.name');
```

## Logging System

The foundation includes a PSR-3 compliant logging system with file-based storage:

```php
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

// Get logger from container
/** @var LoggerInterface $logger */
$logger = Foundation::getInstance()->getContainer()->get(LoggerInterface::class);

// Or use the alias
$logger = Foundation::getInstance()->getContainer()->get('logger');

// Basic logging
$logger->info('Application started');
$logger->error('Database connection failed');

// Using channels
$logger->channel('taste')->info('TasteGraph initialized');
$logger->channel('api')->debug('API request received', ['endpoint' => '/v1/restaurants']);

// Context interpolation
$logger->info('User {username} logged in from {ip}', [
    'username' => 'john_doe',
    'ip' => '192.168.1.1'
]);

// All log levels supported
$logger->emergency('System is unusable');
$logger->alert('Action must be taken immediately');
$logger->critical('Critical conditions');
$logger->error('Error conditions');
$logger->warning('Warning conditions');
$logger->notice('Normal but significant condition');
$logger->info('Informational messages');
$logger->debug('Debug-level messages');
```

Logs are stored in `wp-content/mu-plugins/zippicks-foundation/logs/` with daily rotation.

## License

Proprietary - ZipPicks, Inc. All rights reserved.