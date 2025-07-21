<?php
/**
 * PSR-4 Autoloader implementation
 * 
 * @package ZipPicks\Foundation\Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Core;

/**
 * PSR-4 compliant autoloader for the ZipPicks Foundation
 */
class Autoloader
{
    /**
     * Namespace prefix to base directory mapping
     * 
     * @var array<string, string[]>
     */
    protected array $prefixes = [];

    /**
     * Register the autoloader
     * 
     * @return bool
     */
    public function register(): bool
    {
        return spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Unregister the autoloader
     * 
     * @return bool
     */
    public function unregister(): bool
    {
        return spl_autoload_unregister([$this, 'loadClass']);
    }

    /**
     * Add a base directory for a namespace prefix
     * 
     * @param string $prefix The namespace prefix
     * @param string $baseDir The base directory for class files in the namespace
     * @param bool $prepend If true, prepend the base directory to the stack instead of appending it
     * 
     * @return void
     */
    public function addNamespace(string $prefix, string $baseDir, bool $prepend = false): void
    {
        // Normalize namespace prefix
        $prefix = trim($prefix, '\\') . '\\';

        // Normalize the base directory with a trailing separator
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';

        // Initialize the namespace prefix array
        if (!isset($this->prefixes[$prefix])) {
            $this->prefixes[$prefix] = [];
        }

        // Retain the base directory for the namespace prefix
        if ($prepend) {
            array_unshift($this->prefixes[$prefix], $baseDir);
        } else {
            $this->prefixes[$prefix][] = $baseDir;
        }
    }

    /**
     * Load the class file for a given class name
     * 
     * @param string $class The fully-qualified class name
     * 
     * @return void
     */
    public function loadClass(string $class): void
    {
        // The current namespace prefix
        $prefix = $class;

        // Work backwards through the namespace names of the fully-qualified
        // class name to find a mapped file name
        while (false !== $pos = strrpos($prefix, '\\')) {
            // Retain the trailing namespace separator in the prefix
            $prefix = substr($class, 0, $pos + 1);

            // The rest is the relative class name
            $relativeClass = substr($class, $pos + 1);

            // Try to load a mapped file for the prefix and relative class
            $mappedFile = $this->loadMappedFile($prefix, $relativeClass);
            if ($mappedFile) {
                return;
            }

            // Remove the trailing namespace separator for the next iteration of strrpos()
            $prefix = rtrim($prefix, '\\');
        }
    }

    /**
     * Load the mapped file for a namespace prefix and relative class
     * 
     * @param string $prefix The namespace prefix
     * @param string $relativeClass The relative class name
     * 
     * @return bool|string False if no mapped file can be loaded, or the
     *                     name of the mapped file that was loaded
     */
    protected function loadMappedFile(string $prefix, string $relativeClass): bool|string
    {
        // Are there any base directories for this namespace prefix?
        if (!isset($this->prefixes[$prefix])) {
            return false;
        }

        // Look through base directories for this namespace prefix
        foreach ($this->prefixes[$prefix] as $baseDir) {
            // Replace the namespace prefix with the base directory,
            // replace namespace separators with directory separators
            // in the relative class name, append with .php
            $file = $baseDir
                  . str_replace('\\', '/', $relativeClass)
                  . '.php';

            // If the mapped file exists, require it
            if ($this->requireFile($file)) {
                return $file;
            }
        }

        // Never found it
        return false;
    }

    /**
     * If a file exists, require it from the file system
     * 
     * @param string $file The file to require
     * 
     * @return bool True if the file exists, false if not
     */
    protected function requireFile(string $file): bool
    {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        return false;
    }
}