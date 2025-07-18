<?php
/**
 * File Log Driver
 * 
 * @package ZipPicks\Foundation\Logging\Drivers
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Logging\Drivers;

use ZipPicks\Foundation\Contracts\Logging\LogDriverInterface;
use ZipPicks\Foundation\Logging\LogEntry;
use ZipPicks\Foundation\Logging\LogLevel;

/**
 * File-based log driver with rotation and performance optimization
 */
class FileLogDriver implements LogDriverInterface
{
    private string $logPath;
    private string $minLevel;
    private int $maxFileSize;
    private int $maxFiles;
    private array $metrics = [
        'writes' => 0,
        'failures' => 0,
        'bytes_written' => 0,
    ];
    private array $buffer = [];
    private int $bufferSize;
    private float $lastFlush;

    public function __construct(
        string $logPath,
        string $minLevel = LogLevel::DEBUG,
        int $maxFileSize = 10485760, // 10MB
        int $maxFiles = 30,
        int $bufferSize = 100
    ) {
        $this->logPath = rtrim($logPath, '/');
        $this->minLevel = $minLevel;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->bufferSize = $bufferSize;
        $this->lastFlush = microtime(true);
        
        $this->ensureLogDirectory();
    }

    public function write(LogEntry $entry): void
    {
        if (!LogLevel::meetsThreshold($entry->getLevel(), $this->minLevel)) {
            return;
        }

        $this->buffer[] = $entry;

        // Flush if buffer is full or 1 second has passed
        if (count($this->buffer) >= $this->bufferSize || 
            (microtime(true) - $this->lastFlush) > 1.0) {
            $this->flush();
        }
    }

    public function writeBatch(array $entries): void
    {
        foreach ($entries as $entry) {
            if (LogLevel::meetsThreshold($entry->getLevel(), $this->minLevel)) {
                $this->buffer[] = $entry;
            }
        }
        
        $this->flush();
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $filename = $this->getLogFilename();
        $filepath = $this->logPath . '/' . $filename;

        // Check rotation before writing
        $this->rotateIfNeeded($filepath);

        $content = '';
        foreach ($this->buffer as $entry) {
            $line = $this->formatEntry($entry);
            $content .= $line . PHP_EOL;
        }

        $bytesWritten = @file_put_contents($filepath, $content, FILE_APPEND | LOCK_EX);

        if ($bytesWritten === false) {
            $this->metrics['failures'] += count($this->buffer);
            error_log("FileLogDriver: Unable to write to log file {$filepath}");
        } else {
            $this->metrics['writes'] += count($this->buffer);
            $this->metrics['bytes_written'] += $bytesWritten;
        }

        $this->buffer = [];
        $this->lastFlush = microtime(true);
    }

    public function isHealthy(): bool
    {
        $testFile = $this->logPath . '/.health-check';
        $result = @file_put_contents($testFile, time());
        
        if ($result !== false) {
            @unlink($testFile);
            return true;
        }
        
        return false;
    }

    public function getName(): string
    {
        return 'file';
    }

    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'buffer_size' => count($this->buffer),
            'log_path' => $this->logPath,
            'is_writable' => is_writable($this->logPath),
        ]);
    }

    private function formatEntry(LogEntry $entry): string
    {
        $data = $entry->toArray();
        
        // Format: [2024-01-01 12:00:00.123456] [channel] LEVEL: Message {context}
        $format = "[%s.%s] [%s] %s: %s";
        
        $line = sprintf(
            $format,
            $data['datetime'],
            $data['microseconds'],
            $data['channel'],
            strtoupper($data['level']),
            $data['formatted_message']
        );

        // Add context if present
        $context = array_diff_key($data['context'], array_flip(['exception']));
        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Add exception trace if present
        if (isset($data['context']['exception'])) {
            $exception = $data['context']['exception'];
            if ($exception instanceof \Throwable) {
                $line .= PHP_EOL . $this->formatException($exception);
            }
        }

        return $line;
    }

    private function formatException(\Throwable $e): string
    {
        return sprintf(
            "Exception: %s in %s:%d\nStack trace:\n%s",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
    }

    private function getLogFilename(): string
    {
        return date('Y-m-d') . '.log';
    }

    private function rotateIfNeeded(string $filepath): void
    {
        if (!file_exists($filepath)) {
            return;
        }

        $size = filesize($filepath);
        if ($size === false || $size < $this->maxFileSize) {
            return;
        }

        // Rotate the file
        $timestamp = date('His');
        $rotatedPath = str_replace('.log', "-{$timestamp}.log", $filepath);
        
        if (@rename($filepath, $rotatedPath)) {
            $this->cleanOldLogs();
        }
    }

    private function cleanOldLogs(): void
    {
        $files = glob($this->logPath . '/*.log');
        if (count($files) <= $this->maxFiles) {
            return;
        }

        // Sort by modification time
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Remove oldest files
        $toDelete = count($files) - $this->maxFiles;
        for ($i = 0; $i < $toDelete; $i++) {
            @unlink($files[$i]);
        }
    }

    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logPath)) {
            $created = @mkdir($this->logPath, 0755, true);
            
            if (!$created) {
                throw new \RuntimeException("Unable to create log directory: {$this->logPath}");
            }
        }

        if (!is_writable($this->logPath)) {
            throw new \RuntimeException("Log directory is not writable: {$this->logPath}");
        }
    }

    public function __destruct()
    {
        // Ensure buffer is flushed on destruction
        $this->flush();
    }
}