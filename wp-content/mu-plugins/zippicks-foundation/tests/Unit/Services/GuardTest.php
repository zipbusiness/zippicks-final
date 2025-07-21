<?php
/**
 * Guard Tests
 *
 * @package ZipPicks\Foundation\Tests\Unit\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ZipPicks\Foundation\Auth\RoleGuard;
use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Models\User;

/**
 * Guard test suite
 *
 * @since 1.0.0
 */
class GuardTest extends TestCase
{
    /**
     * Auth manager mock
     *
     * @var MockObject&AuthManagerInterface
     */
    private AuthManagerInterface $authManager;

    /**
     * Guard instance
     *
     * @var RoleGuard
     */
    private RoleGuard $guard;

    /**
     * Set up test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->authManager = $this->createMock(AuthManagerInterface::class);
        $this->guard = new RoleGuard($this->authManager);

        // Mock WordPress functions
        if (!function_exists('user_can')) {
            function user_can($userId, $capability, ...$args) {
                // Mock implementation for testing
                $capabilities = [
                    'edit_posts' => [1, 2],
                    'manage_options' => [1],
                    'publish_posts' => [1, 2, 3],
                ];
                
                return isset($capabilities[$capability]) && in_array($userId, $capabilities[$capability]);
            }
        }
    }

    /**
     * Test allows method with authenticated user
     *
     * @return void
     */
    public function testAllowsWithAuthenticatedUser(): void
    {
        $user = new User(
            id: 1,
            email: 'admin@example.com',
            displayName: 'Admin User',
            roles: ['administrator']
        );

        $this->authManager->method('user')->willReturn($user);

        // Test role ability
        $this->assertTrue($this->guard->allows('role', 'administrator'));
        $this->assertFalse($this->guard->allows('role', 'subscriber'));

        // Test admin ability
        $this->assertTrue($this->guard->allows('admin'));

        // Test user ability
        $this->assertTrue($this->guard->allows('user', 1));
        $this->assertFalse($this->guard->allows('user', 2));

        // Test authenticated ability
        $this->assertTrue($this->guard->allows('authenticated'));
    }

    /**
     * Test allows method with unauthenticated user
     *
     * @return void
     */
    public function testAllowsWithUnauthenticatedUser(): void
    {
        $this->authManager->method('user')->willReturn(null);

        $this->assertFalse($this->guard->allows('role', 'administrator'));
        $this->assertFalse($this->guard->allows('admin'));
        $this->assertFalse($this->guard->allows('authenticated'));
    }

    /**
     * Test denies method
     *
     * @return void
     */
    public function testDeniesMethod(): void
    {
        $user = new User(
            id: 2,
            email: 'editor@example.com',
            displayName: 'Editor User',
            roles: ['editor']
        );

        $this->authManager->method('user')->willReturn($user);

        $this->assertFalse($this->guard->denies('role', 'editor'));
        $this->assertTrue($this->guard->denies('role', 'administrator'));
        $this->assertTrue($this->guard->denies('admin'));
    }

    /**
     * Test forUser method
     *
     * @return void
     */
    public function testForUserMethod(): void
    {
        $user1 = new User(
            id: 1,
            email: 'user1@example.com',
            displayName: 'User 1',
            roles: ['administrator']
        );

        $user2 = new User(
            id: 2,
            email: 'user2@example.com',
            displayName: 'User 2',
            roles: ['editor']
        );

        // Test with specific user
        $guardForUser1 = $this->guard->forUser($user1);
        $this->assertTrue($guardForUser1->allows('admin'));
        $this->assertEquals($user1, $guardForUser1->user());

        // Test with different user
        $guardForUser2 = $this->guard->forUser($user2);
        $this->assertFalse($guardForUser2->allows('admin'));
        $this->assertEquals($user2, $guardForUser2->user());

        // Original guard should not be affected
        $this->assertNotSame($this->guard, $guardForUser1);
        $this->assertNotSame($this->guard, $guardForUser2);
    }

    /**
     * Test hasUser method
     *
     * @return void
     */
    public function testHasUserMethod(): void
    {
        // Test without user
        $this->authManager->method('user')->willReturn(null);
        $this->assertFalse($this->guard->hasUser());

        // Test with user
        $user = new User(
            id: 1,
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['subscriber']
        );
        
        $guardWithUser = $this->guard->forUser($user);
        $this->assertTrue($guardWithUser->hasUser());
    }

    /**
     * Test custom ability definition
     *
     * @return void
     */
    public function testCustomAbilityDefinition(): void
    {
        $user = new User(
            id: 1,
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['editor'],
            metadata: ['subscription_level' => 'premium']
        );

        $this->authManager->method('user')->willReturn($user);

        // Define custom ability
        $this->guard->defineAbility('premium', function (User $user) {
            return ($user->metadata['subscription_level'] ?? '') === 'premium';
        });

        $this->assertTrue($this->guard->allows('premium'));

        // Define another custom ability
        $this->guard->defineAbility('owns_post', function (User $user, int $postId) {
            // Mock implementation
            return $postId === 123 && $user->id === 1;
        });

        $this->assertTrue($this->guard->allows('owns_post', 123));
        $this->assertFalse($this->guard->allows('owns_post', 456));
    }

    /**
     * Test multiple abilities definition
     *
     * @return void
     */
    public function testMultipleAbilitiesDefinition(): void
    {
        $user = new User(
            id: 1,
            email: 'user@example.com',
            displayName: 'Test User',
            roles: ['author']
        );

        $this->authManager->method('user')->willReturn($user);

        $abilities = [
            'create_post' => function (User $user) {
                return $user->hasAnyRole(['author', 'editor', 'administrator']);
            },
            'delete_post' => function (User $user, int $postId) {
                return $user->hasRole('administrator') || 
                       ($user->hasRole('author') && $postId === $user->id);
            },
        ];

        $this->guard->define($abilities);

        $this->assertTrue($this->guard->allows('create_post'));
        $this->assertFalse($this->guard->allows('delete_post', 999));
        $this->assertTrue($this->guard->allows('delete_post', 1)); // Same as user ID
    }

    /**
     * Test ZipPicks specific abilities
     *
     * @return void
     */
    public function testZipPicksSpecificAbilities(): void
    {
        // Test critic ability
        $critic = new User(
            id: 1,
            email: 'critic@example.com',
            displayName: 'Food Critic',
            roles: ['zippicks_critic']
        );

        $guardForCritic = $this->guard->forUser($critic);
        $this->assertTrue($guardForCritic->allows('critic'));
        $this->assertTrue($guardForCritic->allows('manage_reviews'));
        $this->assertFalse($guardForCritic->allows('business_owner'));

        // Test business owner ability
        $owner = new User(
            id: 2,
            email: 'owner@example.com',
            displayName: 'Business Owner',
            roles: ['zippicks_business_owner']
        );

        $guardForOwner = $this->guard->forUser($owner);
        $this->assertTrue($guardForOwner->allows('business_owner'));
        $this->assertTrue($guardForOwner->allows('manage_businesses'));
        $this->assertFalse($guardForOwner->allows('critic'));

        // Test admin has all abilities
        $admin = new User(
            id: 3,
            email: 'admin@example.com',
            displayName: 'Admin',
            roles: ['administrator']
        );

        $guardForAdmin = $this->guard->forUser($admin);
        $this->assertTrue($guardForAdmin->allows('critic'));
        $this->assertTrue($guardForAdmin->allows('business_owner'));
        $this->assertTrue($guardForAdmin->allows('manage_reviews'));
        $this->assertTrue($guardForAdmin->allows('manage_businesses'));
    }

    /**
     * Test roles ability with multiple roles
     *
     * @return void
     */
    public function testRolesAbilityWithMultipleRoles(): void
    {
        $user = new User(
            id: 1,
            email: 'user@example.com',
            displayName: 'Multi Role User',
            roles: ['editor', 'author']
        );

        $this->authManager->method('user')->willReturn($user);

        $this->assertTrue($this->guard->allows('roles', ['editor', 'subscriber']));
        $this->assertTrue($this->guard->allows('roles', ['author', 'contributor']));
        $this->assertFalse($this->guard->allows('roles', ['administrator', 'subscriber']));
    }

    /**
     * Test WordPress capability checking
     *
     * @return void
     */
    public function testWordPressCapabilityChecking(): void
    {
        $user = new User(
            id: 1,
            email: 'admin@example.com',
            displayName: 'Admin',
            roles: ['administrator']
        );

        $this->authManager->method('user')->willReturn($user);

        // These will use the mocked user_can function
        $this->assertTrue($this->guard->allows('edit_posts'));
        $this->assertTrue($this->guard->allows('manage_options'));
        $this->assertTrue($this->guard->allows('publish_posts'));

        // Test with non-admin user
        $editor = new User(
            id: 2,
            email: 'editor@example.com',
            displayName: 'Editor',
            roles: ['editor']
        );

        $guardForEditor = $this->guard->forUser($editor);
        $this->assertTrue($guardForEditor->allows('edit_posts'));
        $this->assertFalse($guardForEditor->allows('manage_options'));
    }
}