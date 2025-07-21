# ZipPicks Vibes Test Suite

This directory contains the comprehensive test suite for the ZipPicks Vibes plugin.

## Setup

### Prerequisites

- PHP 8.0 or higher
- Composer
- MySQL/MariaDB
- WordPress test environment

### Installation

1. Install Composer dependencies:
   ```bash
   composer install
   ```

2. Set up WordPress test environment:
   ```bash
   ./bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

   Replace the parameters with your database credentials:
   - `wordpress_test`: Test database name (will be created)
   - `root`: MySQL username
   - `''`: MySQL password (empty in this example)
   - `localhost`: MySQL host
   - `latest`: WordPress version to test against

## Running Tests

### All Tests
```bash
composer test
```

### Unit Tests Only
```bash
composer test:unit
```

### Integration Tests Only
```bash
composer test:integration
```

### Performance Tests Only
```bash
composer test:performance
```

### With Code Coverage
```bash
composer test:coverage
```

Coverage reports will be generated in the `coverage/` directory.

## Writing Tests

### Unit Tests

Unit tests should extend `ZipPicks\Vibes\Tests\TestCase` and be placed in the `tests/Unit/` directory.

Example:
```php
namespace ZipPicks\Vibes\Tests\Unit;

use ZipPicks\Vibes\Tests\TestCase;
use ZipPicks\Vibes\Core\VibeManager;

class VibeManagerTest extends TestCase {
    
    public function test_create_vibe() {
        $manager = new VibeManager();
        
        $result = $manager->create([
            'name' => 'Test Vibe',
            'slug' => 'test-vibe'
        ]);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }
}
```

### Integration Tests

Integration tests should extend `ZipPicks\Vibes\Tests\IntegrationTestCase` and be placed in the `tests/Integration/` directory.

Example:
```php
namespace ZipPicks\Vibes\Tests\Integration;

use ZipPicks\Vibes\Tests\IntegrationTestCase;

class VibesApiTest extends IntegrationTestCase {
    
    public function test_get_vibes_endpoint() {
        $response = $this->make_rest_request('GET', '/zippicks/v2/vibes');
        
        $this->assertRestSuccess($response);
        $data = $this->get_json_data($response);
        $this->assertIsArray($data);
    }
}
```

### Performance Tests

Performance tests should extend `ZipPicks\Vibes\Tests\PerformanceTestCase` and be placed in the `tests/Performance/` directory.

Example:
```php
namespace ZipPicks\Vibes\Tests\Performance;

use ZipPicks\Vibes\Tests\PerformanceTestCase;

class VibeQueryPerformanceTest extends PerformanceTestCase {
    
    public function test_large_dataset_query_performance() {
        // Create test data
        $this->create_large_dataset(1000);
        
        // Benchmark query
        $this->benchmark('get_all_vibes', function() {
            get_terms(['taxonomy' => 'zippicks_vibe', 'hide_empty' => false]);
        });
        
        // Assert performance meets threshold
        $this->assertPerformance('get_all_vibes', 'avg', 100); // 100ms average
        $this->assertPerformance('get_all_vibes', 'p99', 200); // 200ms 99th percentile
    }
}
```

## Code Quality

### PHP CodeSniffer
```bash
composer phpcs
```

Fix coding standards violations:
```bash
composer phpcs:fix
```

### PHPStan Static Analysis
```bash
composer phpstan
```

## Test Database

The test suite uses a separate database that is created and destroyed for each test run. This ensures tests don't affect your development database.

### Manual Database Reset

If needed, you can manually reset the test database:
```bash
mysql -u root -p -e "DROP DATABASE IF EXISTS wordpress_test; CREATE DATABASE wordpress_test;"
```

## Continuous Integration

The test suite is designed to run in CI environments. Set these environment variables:

- `WP_TESTS_DIR`: Path to WordPress test library
- `WP_CORE_DIR`: Path to WordPress core
- `WP_TESTS_DB_NAME`: Test database name
- `WP_TESTS_DB_USER`: Database username
- `WP_TESTS_DB_PASS`: Database password
- `WP_TESTS_DB_HOST`: Database host

## Troubleshooting

### Tests Won't Run

1. Ensure WordPress test environment is installed:
   ```bash
   ls -la /tmp/wordpress-tests-lib/
   ```

2. Check database connection:
   ```bash
   mysql -u root -p -e "SHOW DATABASES;"
   ```

3. Verify Composer dependencies:
   ```bash
   composer install --no-interaction --prefer-dist
   ```

### Memory Errors

Increase PHP memory limit in `php.ini`:
```ini
memory_limit = 256M
```

### Timeout Errors

Increase PHPUnit timeout in `phpunit.xml.dist`:
```xml
<phpunit executionOrder="random" 
         processIsolation="false"
         stopOnFailure="false"
         defaultTestSuite="unit"
         timeoutForSmallTests="10"
         timeoutForMediumTests="30"
         timeoutForLargeTests="60">
```

## Best Practices

1. **Isolation**: Each test should be independent
2. **Cleanup**: Always clean up test data in `tearDown()`
3. **Naming**: Use descriptive test method names
4. **Assertions**: Use specific assertions (`assertSame` vs `assertEquals`)
5. **Mocking**: Mock external dependencies
6. **Performance**: Keep tests fast (< 1 second for unit tests)
7. **Coverage**: Aim for > 80% code coverage

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Documentation](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)