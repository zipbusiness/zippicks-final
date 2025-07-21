<?php
/**
 * Auth Service Unit Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Contracts\Auth\UserProviderInterface;
use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Auth\WPUserProvider;
use ZipPicks\Foundation\Auth\AuthManager;
use ZipPicks\Foundation\Models\User;
use ZipPicks\Foundation\Services\AuthServiceProvider;
use WP_User;

class AuthTest extends TestCase
{
    private WPUserProvider $userProvider;
    private AuthManager $authManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset globals
        $GLOBALS['current_user'] = null;
        $GLOBALS['wp_users'] = [];
        
        $this->userProvider = new WPUserProvider();
        $this->authManager = new AuthManager($this->userProvider);
    }

    protected function tearDown(): void
    {
        // Clean up globals
        unset($GLOBALS['current_user']);
        unset($GLOBALS['wp_users']);
        
        parent::tearDown();
    }

    public function testGuestState(): void
    {
        // Mock no current user
        $GLOBALS['current_user'] = $this->createMockWPUser(0);

        $this->assertTrue($this->authManager->guest());
        $this->assertFalse($this->authManager->check());
        $this->assertNull($this->authManager->user());
        $this->assertNull($this->authManager->id());
    }

    public function testAuthenticatedState(): void
    {
        // Mock authenticated user
        $wpUser = $this->createMockWPUser(123, 'test@example.com', 'Test User', ['subscriber']);
        $GLOBALS['current_user'] = $wpUser;

        $this->assertFalse($this->authManager->guest());
        $this->assertTrue($this->authManager->check());
        
        $user = $this->authManager->user();
        $this->assertNotNull($user);
        $this->assertEquals(123, $user->getId());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('Test User', $user->getDisplayName());
        
        $this->assertEquals(123, $this->authManager->id());
    }

    public function testUserRetrieval(): void
    {
        $wpUser = $this->createMockWPUser(456, 'john@example.com', 'John Doe', ['editor']);
        $GLOBALS['current_user'] = $wpUser;

        $user = $this->userProvider->getCurrentUser();
        
        $this->assertNotNull($user);
        $this->assertEquals(456, $user->getId());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals('John Doe', $user->getDisplayName());
        $this->assertTrue($user->hasRole('editor'));
    }

    public function testFindById(): void
    {
        $wpUser = $this->createMockWPUser(789, 'admin@example.com', 'Admin User', ['administrator']);
        $GLOBALS['wp_users'][789] = $wpUser;

        $user = $this->userProvider->findById(789);
        
        $this->assertNotNull($user);
        $this->assertEquals(789, $user->getId());
        $this->assertEquals('admin@example.com', $user->getEmail());
        $this->assertTrue($user->isAdmin());
    }

    public function testFindByIdNotFound(): void
    {
        $user = $this->userProvider->findById(999);
        $this->assertNull($user);
    }

    public function testFindByEmail(): void
    {
        $wpUser = $this->createMockWPUser(321, 'email@test.com', 'Email User', ['author']);
        $GLOBALS['wp_users']['email@test.com'] = $wpUser;

        $user = $this->userProvider->findByEmail('email@test.com');
        
        $this->assertNotNull($user);
        $this->assertEquals(321, $user->getId());
        $this->assertEquals('email@test.com', $user->getEmail());
    }

    public function testFindByEmailNotFound(): void
    {
        $user = $this->userProvider->findByEmail('notfound@example.com');
        $this->assertNull($user);
    }

    public function testRoleChecks(): void
    {
        $wpUser = $this->createMockWPUser(100, 'roles@test.com', 'Role User', ['editor', 'author']);
        $GLOBALS['current_user'] = $wpUser;

        $this->assertTrue($this->authManager->hasRole('editor'));
        $this->assertTrue($this->authManager->hasRole('author'));
        $this->assertFalse($this->authManager->hasRole('administrator'));
        
        $this->assertTrue($this->authManager->hasAnyRole(['editor', 'subscriber']));
        $this->assertFalse($this->authManager->hasAnyRole(['administrator', 'subscriber']));
        
        $this->assertTrue($this->authManager->hasAllRoles(['editor', 'author']));
        $this->assertFalse($this->authManager->hasAllRoles(['editor', 'administrator']));
        
        $this->assertFalse($this->authManager->isAdmin());
    }

    public function testUserModel(): void
    {
        $user = new User(1, 'test@example.com', 'Test User', ['editor', 'author'], [
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $this->assertEquals(1, $user->getId());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('Test User', $user->getDisplayName());
        $this->assertEquals(['editor', 'author'], $user->getRoles());
        
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->hasRole('administrator'));
        
        $this->assertEquals('Test', $user->getMeta('first_name'));
        $this->assertEquals('Default', $user->getMeta('nonexistent', 'Default'));
        
        $array = $user->toArray();
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('test@example.com', $array['email']);
    }

    public function testUserFromArray(): void
    {
        $data = [
            'id' => 123,
            'email' => 'from@array.com',
            'display_name' => 'From Array',
            'roles' => ['subscriber'],
            'metadata' => ['key' => 'value'],
        ];

        $user = User::fromArray($data);
        
        $this->assertEquals(123, $user->getId());
        $this->assertEquals('from@array.com', $user->getEmail());
        $this->assertEquals('From Array', $user->getDisplayName());
        $this->assertTrue($user->hasRole('subscriber'));
        $this->assertEquals('value', $user->getMeta('key'));
    }

    public function testGuards(): void
    {
        $this->assertEquals('session', $this->authManager->getGuard());
        
        $this->authManager->addGuard('api', ['driver' => 'token']);
        $this->assertTrue($this->authManager->hasGuard('api'));
        
        $guards = $this->authManager->getGuards();
        $this->assertArrayHasKey('session', $guards);
        $this->assertArrayHasKey('api', $guards);
    }

    public function testInvalidGuard(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->authManager->guard('nonexistent');
    }

    public function testAuthManagerReset(): void
    {
        $wpUser = $this->createMockWPUser(111, 'reset@test.com', 'Reset User', ['editor']);
        $GLOBALS['current_user'] = $wpUser;

        // First call should cache the user
        $user1 = $this->authManager->user();
        $this->assertNotNull($user1);

        // Change the global user
        $GLOBALS['current_user'] = $this->createMockWPUser(222, 'new@test.com', 'New User', ['author']);

        // Should still return cached user
        $user2 = $this->authManager->user();
        $this->assertEquals(111, $user2->getId());

        // Reset should clear cache
        $this->authManager->reset();
        $user3 = $this->authManager->user();
        $this->assertEquals(222, $user3->getId());
    }

    public function testServiceProviderRegistration(): void
    {
        // Define constants if not already defined
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new AuthServiceProvider($foundation);
        $provider->register();

        // Test that services are registered
        $this->assertTrue($container->has(UserProviderInterface::class));
        $this->assertTrue($container->has(AuthManagerInterface::class));
        $this->assertTrue($container->has('user'));
        $this->assertTrue($container->has('auth'));

        // Test that we can resolve the services
        $userProvider = $container->get('user');
        $this->assertInstanceOf(UserProviderInterface::class, $userProvider);
        
        $authManager = $container->get('auth');
        $this->assertInstanceOf(AuthManagerInterface::class, $authManager);
    }

    public function testServiceProviderDoesNotOverwriteExistingAliases(): void
    {
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();

        // Pre-register custom aliases
        $customUserProvider = new WPUserProvider();
        $customAuthManager = new AuthManager($customUserProvider);
        
        $container->instance('user', $customUserProvider);
        $container->instance('auth', $customAuthManager);

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new AuthServiceProvider($foundation);
        $provider->register();

        // Test that the original aliases were not overwritten
        $this->assertSame($customUserProvider, $container->get('user'));
        $this->assertSame($customAuthManager, $container->get('auth'));
    }

    public function testEmptyRoles(): void
    {
        $wpUser = $this->createMockWPUser(555, 'noroles@test.com', 'No Roles User', []);
        $GLOBALS['current_user'] = $wpUser;

        $user = $this->authManager->user();
        $this->assertNotNull($user);
        $this->assertEmpty($user->getRoles());
        $this->assertFalse($user->hasRole('any'));
        $this->assertFalse($user->isAdmin());
    }

    public function testMalformedUserData(): void
    {
        // Test with null/empty values
        $wpUser = $this->createMockWPUser(0);
        $user = $this->mapWpUserToUser($wpUser);
        $this->assertNull($user);

        // Test with invalid email
        $wpUser = $this->createMockWPUser(123, '', 'User', ['role']);
        $user = $this->mapWpUserToUser($wpUser);
        $this->assertNotNull($user);
        $this->assertEquals('', $user->getEmail());
    }

    /**
     * Create a mock WP_User object
     */
    private function createMockWPUser(
        int $id,
        string $email = '',
        string $displayName = '',
        array $roles = []
    ): WP_User {
        $wpUser = $this->createMock(WP_User::class);
        
        $wpUser->ID = $id;
        $wpUser->user_email = $email;
        $wpUser->display_name = $displayName;
        $wpUser->user_login = 'user' . $id;
        $wpUser->roles = $roles;
        
        $wpUser->method('exists')->willReturn($id > 0);
        
        return $wpUser;
    }

    /**
     * Helper to test WP_User to User mapping
     */
    private function mapWpUserToUser(WP_User $wpUser): ?User
    {
        $provider = new WPUserProvider();
        $reflection = new \ReflectionClass($provider);
        $method = $reflection->getMethod('mapWpUser');
        $method->setAccessible(true);
        
        return $method->invoke($provider, $wpUser);
    }
}

// Mock WordPress functions
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return $GLOBALS['current_user'] ?? null;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id) {
        return $GLOBALS['wp_users'][$user_id] ?? false;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        if ($field === 'email' && isset($GLOBALS['wp_users'][$value])) {
            return $GLOBALS['wp_users'][$value];
        }
        return false;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}