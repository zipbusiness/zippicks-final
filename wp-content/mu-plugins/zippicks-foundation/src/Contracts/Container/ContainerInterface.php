<?php
/**
 * Container Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Container
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * Extended container interface based on PSR-11
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Bind a value into the container
     * 
     * @param string $id
     * @param mixed $concrete
     * @param bool $shared
     * 
     * @return void
     */
    public function bind(string $id, mixed $concrete = null, bool $shared = false): void;

    /**
     * Bind a singleton into the container
     * 
     * @param string $id
     * @param mixed $concrete
     * 
     * @return void
     */
    public function singleton(string $id, mixed $concrete = null): void;

    /**
     * Register an existing instance
     * 
     * @param string $id
     * @param mixed $instance
     * 
     * @return void
     */
    public function instance(string $id, mixed $instance): void;

    /**
     * Call a method with dependency injection
     * 
     * @param callable $callback
     * @param array<string, mixed> $parameters
     * 
     * @return mixed
     */
    public function call(callable $callback, array $parameters = []): mixed;
}