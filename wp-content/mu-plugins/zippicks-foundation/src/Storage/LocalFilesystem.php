<?php
/**
 * Local Filesystem Implementation
 * 
 * @package ZipPicks\Foundation\Storage
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Storage;

use ZipPicks\Foundation\Contracts\Storage\FilesystemInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

/**
 * Local filesystem adapter using native PHP functions
 */
class LocalFilesystem implements FilesystemInterface
{
    /**
     * Base storage path
     * 
     * @var string
     */
    protected string $basePath;

    /**
     * Logger instance
     * 
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger;

    /**
     * Create a new local filesystem instance
     * 
     * @param string $basePath Base storage path
     * @param LoggerInterface|null $logger Optional logger
     */
    public function __construct(string $basePath, ?LoggerInterface $logger = null)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->logger = $logger;

        // Ensure base directory exists
        $this->ensureDirectoryExists($this->basePath);
    }

    /**
     * {@inheritdoc}
     */
    public function read(string $path): ?string
    {
        $fullPath = $this->normalizePath($path);

        if (!$this->exists($path)) {
            $this->logDebug('File not found for reading', ['path' => $path]);
            return null;
        }

        $contents = @file_get_contents($fullPath);

        if ($contents === false) {
            $this->logError('Failed to read file', ['path' => $path, 'error' => error_get_last()]);
            return null;
        }

        return $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $path, string $contents): bool
    {
        $fullPath = $this->normalizePath($path);

        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            $this->makeDirectory(dirname($path));
        }

        $result = @file_put_contents($fullPath, $contents, LOCK_EX);

        if ($result === false) {
            $this->logError('Failed to write file', ['path' => $path, 'error' => error_get_last()]);
            return false;
        }

        $this->logDebug('File written successfully', ['path' => $path, 'bytes' => $result]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string $path): bool
    {
        $fullPath = $this->normalizePath($path);
        return file_exists($fullPath);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->normalizePath($path);

        if (!$this->exists($path)) {
            return true; // Already doesn't exist
        }

        if (is_dir($fullPath)) {
            $result = @rmdir($fullPath);
        } else {
            $result = @unlink($fullPath);
        }

        if (!$result) {
            $this->logError('Failed to delete file', ['path' => $path, 'error' => error_get_last()]);
            return false;
        }

        $this->logDebug('File deleted successfully', ['path' => $path]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->normalizePath($from);
        $toPath = $this->normalizePath($to);

        if (!$this->exists($from)) {
            $this->logError('Source file not found for copy', ['from' => $from]);
            return false;
        }

        // Ensure destination directory exists
        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            $this->makeDirectory(dirname($to));
        }

        $result = @copy($fromPath, $toPath);

        if (!$result) {
            $this->logError('Failed to copy file', ['from' => $from, 'to' => $to, 'error' => error_get_last()]);
            return false;
        }

        $this->logDebug('File copied successfully', ['from' => $from, 'to' => $to]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function move(string $from, string $to): bool
    {
        $fromPath = $this->normalizePath($from);
        $toPath = $this->normalizePath($to);

        if (!$this->exists($from)) {
            $this->logError('Source file not found for move', ['from' => $from]);
            return false;
        }

        // If paths are the same, return success
        if ($fromPath === $toPath) {
            return true;
        }

        // Ensure destination directory exists
        $directory = dirname($toPath);
        if (!is_dir($directory)) {
            $this->makeDirectory(dirname($to));
        }

        $result = @rename($fromPath, $toPath);

        if (!$result) {
            $this->logError('Failed to move file', ['from' => $from, 'to' => $to, 'error' => error_get_last()]);
            return false;
        }

        $this->logDebug('File moved successfully', ['from' => $from, 'to' => $to]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function makeDirectory(string $path, int $permissions = 0755): bool
    {
        $fullPath = $this->normalizePath($path);

        if (is_dir($fullPath)) {
            return true; // Already exists
        }

        $result = @mkdir($fullPath, $permissions, true);

        if (!$result) {
            $this->logError('Failed to create directory', ['path' => $path, 'error' => error_get_last()]);
            return false;
        }

        $this->logDebug('Directory created successfully', ['path' => $path, 'permissions' => decoct($permissions)]);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function listFiles(string $directory): array
    {
        $fullPath = $this->normalizePath($directory);

        if (!is_dir($fullPath)) {
            $this->logError('Directory not found for listing', ['directory' => $directory]);
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                // Get relative path from base directory
                $relativePath = $this->getRelativePath($file->getPathname());
                if ($relativePath !== null) {
                    $files[] = $relativePath;
                }
            }
        }

        sort($files);
        return $files;
    }

    /**
     * {@inheritdoc}
     */
    public function lastModified(string $path): ?int
    {
        $fullPath = $this->normalizePath($path);

        if (!$this->exists($path)) {
            return null;
        }

        $timestamp = @filemtime($fullPath);

        if ($timestamp === false) {
            $this->logError('Failed to get last modified time', ['path' => $path, 'error' => error_get_last()]);
            return null;
        }

        return $timestamp;
    }

    /**
     * {@inheritdoc}
     */
    public function fileSize(string $path): ?int
    {
        $fullPath = $this->normalizePath($path);

        if (!$this->exists($path)) {
            return null;
        }

        $size = @filesize($fullPath);

        if ($size === false) {
            $this->logError('Failed to get file size', ['path' => $path, 'error' => error_get_last()]);
            return null;
        }

        return $size;
    }

    /**
     * Normalize a path relative to the base path
     * 
     * @param string $path
     * 
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        // Remove any leading slashes
        $path = ltrim($path, '/\\');

        // Replace backslashes with forward slashes
        $path = str_replace('\\', '/', $path);

        // Remove any double slashes
        $path = preg_replace('#/+#', '/', $path);

        // Remove any directory traversal attempts
        $parts = explode('/', $path);
        $filtered = array_filter($parts, function($part) {
            return $part !== '..' && $part !== '.';
        });
        $path = implode('/', $filtered);

        // Combine with base path
        return $this->basePath . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Get relative path from full path
     * 
     * @param string $fullPath
     * 
     * @return string|null
     */
    protected function getRelativePath(string $fullPath): ?string
    {
        $fullPath = str_replace('\\', '/', $fullPath);
        $basePath = str_replace('\\', '/', $this->basePath);

        if (strpos($fullPath, $basePath) === 0) {
            return ltrim(substr($fullPath, strlen($basePath)), '/');
        }

        return null;
    }

    /**
     * Ensure a directory exists
     * 
     * @param string $path
     * 
     * @return void
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    /**
     * Log debug message
     * 
     * @param string $message
     * @param array<string, mixed> $context
     * 
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->channel('filesystem')->debug($message, $context);
        }
    }

    /**
     * Log error message
     * 
     * @param string $message
     * @param array<string, mixed> $context
     * 
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->channel('filesystem')->error($message, $context);
        }
    }

    /**
     * Get the base path
     * 
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Delete a directory and all its contents
     * 
     * @param string $directory
     * 
     * @return bool
     */
    public function deleteDirectory(string $directory): bool
    {
        $fullPath = $this->normalizePath($directory);

        if (!is_dir($fullPath)) {
            return true; // Already doesn't exist
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            if (!@$todo($fileinfo->getRealPath())) {
                $this->logError('Failed to delete directory item', [
                    'path' => $fileinfo->getRealPath(),
                    'error' => error_get_last()
                ]);
                return false;
            }
        }

        if (!@rmdir($fullPath)) {
            $this->logError('Failed to delete directory', [
                'directory' => $directory,
                'error' => error_get_last()
            ]);
            return false;
        }

        $this->logDebug('Directory deleted successfully', ['directory' => $directory]);
        return true;
    }

    /**
     * Get file extension
     * 
     * @param string $path
     * 
     * @return string
     */
    public function extension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Get file name without extension
     * 
     * @param string $path
     * 
     * @return string
     */
    public function name(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Get MIME type of a file
     * 
     * @param string $path
     * 
     * @return string|null
     */
    public function mimeType(string $path): ?string
    {
        $fullPath = $this->normalizePath($path);

        if (!$this->exists($path)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        return $mimeType !== false ? $mimeType : null;
    }
}