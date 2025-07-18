<?php
/**
 * Filesystem Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Storage
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Storage;

/**
 * Interface for filesystem operations
 */
interface FilesystemInterface
{
    /**
     * Read contents from a file
     * 
     * @param string $path The file path
     * 
     * @return string|null File contents or null if file doesn't exist
     */
    public function read(string $path): ?string;

    /**
     * Write contents to a file
     * 
     * @param string $path The file path
     * @param string $contents The contents to write
     * 
     * @return bool True on success, false on failure
     */
    public function write(string $path, string $contents): bool;

    /**
     * Check if a file or directory exists
     * 
     * @param string $path The file or directory path
     * 
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Delete a file
     * 
     * @param string $path The file path
     * 
     * @return bool True on success, false on failure
     */
    public function delete(string $path): bool;

    /**
     * Copy a file from one location to another
     * 
     * @param string $from Source path
     * @param string $to Destination path
     * 
     * @return bool True on success, false on failure
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file from one location to another
     * 
     * @param string $from Source path
     * @param string $to Destination path
     * 
     * @return bool True on success, false on failure
     */
    public function move(string $from, string $to): bool;

    /**
     * Create a directory
     * 
     * @param string $path The directory path
     * @param int $permissions The directory permissions
     * 
     * @return bool True on success, false on failure
     */
    public function makeDirectory(string $path, int $permissions = 0755): bool;

    /**
     * List files in a directory
     * 
     * @param string $directory The directory path
     * 
     * @return array<string> Array of file paths
     */
    public function listFiles(string $directory): array;

    /**
     * Get the last modified timestamp of a file
     * 
     * @param string $path The file path
     * 
     * @return int|null Unix timestamp or null if file doesn't exist
     */
    public function lastModified(string $path): ?int;

    /**
     * Get the size of a file in bytes
     * 
     * @param string $path The file path
     * 
     * @return int|null File size in bytes or null if file doesn't exist
     */
    public function fileSize(string $path): ?int;
}