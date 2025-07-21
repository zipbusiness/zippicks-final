<?php
/**
 * Mock Foundation container for testing
 *
 * @package ZipPicks_Vibes\Tests\Mocks
 */

namespace ZipPicks\Vibes\Tests\Mocks;

/**
 * Mock Foundation class for testing without actual Foundation
 */
class MockFoundation {
    
    /**
     * @var array Registered services
     */
    private $services = [];
    
    /**
     * @var array Service instances
     */
    private $instances = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->registerDefaultServices();
    }
    
    /**
     * Register default services
     */
    private function registerDefaultServices() {
        // Register mock cache service
        $this->bind('cache', new MockCache());
        
        // Register mock logger service
        $this->bind('logger', new MockLogger());
        
        // Register mock HTTP client
        $this->bind('http', new MockHttpClient());
        
        // Register mock storage service
        $this->bind('storage', new MockStorage());
    }
    
    /**
     * Bind a service to the container
     *
     * @param string $name Service name
     * @param mixed $service Service instance or callable
     */
    public function bind($name, $service) {
        $this->services[$name] = $service;
        if (is_object($service)) {
            $this->instances[$name] = $service;
        }
    }
    
    /**
     * Get a service from the container
     *
     * @param string $name Service name
     * @return mixed|null
     */
    public function get($name) {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        
        if (isset($this->services[$name])) {
            $service = $this->services[$name];
            if (is_callable($service)) {
                $this->instances[$name] = $service();
                return $this->instances[$name];
            }
            return $service;
        }
        
        return null;
    }
    
    /**
     * Check if a service exists
     *
     * @param string $name Service name
     * @return bool
     */
    public function has($name) {
        return isset($this->services[$name]);
    }
    
    /**
     * Magic method to get services
     *
     * @param string $name Service name
     * @return mixed|null
     */
    public function __get($name) {
        return $this->get($name);
    }
}

/**
 * Mock Cache service
 */
class MockCache {
    private $data = [];
    
    public function get($key) {
        return $this->data[$key] ?? null;
    }
    
    public function set($key, $value, $ttl = 3600) {
        $this->data[$key] = $value;
        return true;
    }
    
    public function delete($key) {
        unset($this->data[$key]);
        return true;
    }
    
    public function flush() {
        $this->data = [];
        return true;
    }
    
    public function has($key) {
        return isset($this->data[$key]);
    }
    
    public function remember($key, $callback, $ttl = 3600) {
        if (!isset($this->data[$key])) {
            $this->data[$key] = $callback();
        }
        return $this->data[$key];
    }
}

/**
 * Mock Logger service
 */
class MockLogger {
    private $logs = [];
    
    public function emergency($message, array $context = []) {
        $this->log('emergency', $message, $context);
    }
    
    public function alert($message, array $context = []) {
        $this->log('alert', $message, $context);
    }
    
    public function critical($message, array $context = []) {
        $this->log('critical', $message, $context);
    }
    
    public function error($message, array $context = []) {
        $this->log('error', $message, $context);
    }
    
    public function warning($message, array $context = []) {
        $this->log('warning', $message, $context);
    }
    
    public function notice($message, array $context = []) {
        $this->log('notice', $message, $context);
    }
    
    public function info($message, array $context = []) {
        $this->log('info', $message, $context);
    }
    
    public function debug($message, array $context = []) {
        $this->log('debug', $message, $context);
    }
    
    public function log($level, $message, array $context = []) {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'time' => time()
        ];
    }
    
    public function getLogs() {
        return $this->logs;
    }
    
    public function clearLogs() {
        $this->logs = [];
    }
}

/**
 * Mock HTTP Client
 */
class MockHttpClient {
    private $responses = [];
    private $requests = [];
    
    public function get($url, $args = []) {
        return $this->request('GET', $url, $args);
    }
    
    public function post($url, $args = []) {
        return $this->request('POST', $url, $args);
    }
    
    public function request($method, $url, $args = []) {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'args' => $args
        ];
        
        $key = $method . ':' . $url;
        if (isset($this->responses[$key])) {
            return $this->responses[$key];
        }
        
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['success' => true])
        ];
    }
    
    public function setResponse($method, $url, $response) {
        $key = $method . ':' . $url;
        $this->responses[$key] = $response;
    }
    
    public function getRequests() {
        return $this->requests;
    }
    
    public function clearRequests() {
        $this->requests = [];
    }
}

/**
 * Mock Storage service
 */
class MockStorage {
    private $files = [];
    
    public function put($path, $contents) {
        $this->files[$path] = $contents;
        return true;
    }
    
    public function get($path) {
        return $this->files[$path] ?? null;
    }
    
    public function exists($path) {
        return isset($this->files[$path]);
    }
    
    public function delete($path) {
        unset($this->files[$path]);
        return true;
    }
    
    public function size($path) {
        return isset($this->files[$path]) ? strlen($this->files[$path]) : 0;
    }
    
    public function lastModified($path) {
        return isset($this->files[$path]) ? time() : null;
    }
    
    public function files($directory = '') {
        $result = [];
        $prefix = $directory ? rtrim($directory, '/') . '/' : '';
        
        foreach ($this->files as $path => $content) {
            if (!$prefix || strpos($path, $prefix) === 0) {
                $result[] = $path;
            }
        }
        
        return $result;
    }
}