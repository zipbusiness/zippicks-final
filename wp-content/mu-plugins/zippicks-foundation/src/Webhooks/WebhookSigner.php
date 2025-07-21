<?php
/**
 * Webhook Signature Generator and Verifier
 *
 * @package ZipPicks\Foundation\Webhooks
 */

namespace ZipPicks\Foundation\Webhooks;

use ZipPicks\Foundation\Contracts\Webhooks\WebhookSignerInterface;
use ZipPicks\Foundation\Logging\LoggerInterface;
use InvalidArgumentException;
use Exception;

/**
 * Handles HMAC-SHA256 webhook signatures for security
 */
class WebhookSigner implements WebhookSignerInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $defaultAlgorithm = 'sha256';

    /**
     * @var int
     */
    protected $timestampTolerance = 300; // 5 minutes

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param array $config
     */
    public function __construct(LoggerInterface $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }

    /**
     * Generate signature for webhook payload
     *
     * @param string $payload
     * @param string $secret
     * @param array $options
     * @return array
     */
    public function sign(string $payload, string $secret, array $options = []): array
    {
        $timestamp = time();
        $algorithm = $options['algorithm'] ?? $this->defaultAlgorithm;
        
        // Create signed payload with timestamp
        $signedPayload = $timestamp . '.' . $payload;
        
        // Generate signature
        $signature = hash_hmac($algorithm, $signedPayload, $secret);
        
        // Generate header value
        $headerValue = sprintf(
            't=%d,v1=%s',
            $timestamp,
            $signature
        );

        $this->logger->debug('Webhook signature generated', [
            'timestamp' => $timestamp,
            'algorithm' => $algorithm,
            'payload_size' => strlen($payload),
        ]);

        return [
            'signature' => $signature,
            'timestamp' => $timestamp,
            'header' => $headerValue,
            'header_name' => $this->config['header_name'],
        ];
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param string $headerValue
     * @param string $secret
     * @param array $options
     * @return bool
     * @throws InvalidArgumentException
     */
    public function verify(string $payload, string $headerValue, string $secret, array $options = []): bool
    {
        try {
            // Parse header value
            $elements = $this->parseHeaderValue($headerValue);
            
            if (!isset($elements['t']) || !isset($elements['v1'])) {
                throw new InvalidArgumentException('Invalid signature header format');
            }
            
            $timestamp = (int) $elements['t'];
            $signature = $elements['v1'];
            
            // Check timestamp to prevent replay attacks
            if (!$this->isValidTimestamp($timestamp, $options['tolerance'] ?? $this->timestampTolerance)) {
                $this->logger->warning('Webhook verification failed: timestamp too old', [
                    'timestamp' => $timestamp,
                    'current_time' => time(),
                ]);
                return false;
            }
            
            // Recreate signed payload
            $signedPayload = $timestamp . '.' . $payload;
            
            // Generate expected signature
            $expectedSignature = hash_hmac(
                $options['algorithm'] ?? $this->defaultAlgorithm,
                $signedPayload,
                $secret
            );
            
            // Constant-time comparison
            $isValid = hash_equals($expectedSignature, $signature);
            
            if (!$isValid) {
                $this->logger->warning('Webhook verification failed: signature mismatch', [
                    'expected' => substr($expectedSignature, 0, 10) . '...',
                    'received' => substr($signature, 0, 10) . '...',
                ]);
            }
            
            return $isValid;
            
        } catch (Exception $e) {
            $this->logger->error('Webhook verification error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate webhook event
     *
     * @param string $event
     * @param array $data
     * @param string $secret
     * @return array
     */
    public function createWebhookEvent(string $event, array $data, string $secret): array
    {
        $webhook = [
            'event' => $event,
            'data' => $data,
            'timestamp' => time(),
            'id' => $this->generateEventId(),
        ];

        $payload = json_encode($webhook);
        $signature = $this->sign($payload, $secret);

        return [
            'webhook' => $webhook,
            'payload' => $payload,
            'signature' => $signature,
        ];
    }

    /**
     * Send webhook
     *
     * @param string $url
     * @param array $webhook
     * @param array $options
     * @return array
     */
    public function send(string $url, array $webhook, array $options = []): array
    {
        $startTime = microtime(true);
        
        $response = wp_remote_post($url, [
            'body' => $webhook['payload'],
            'headers' => [
                'Content-Type' => 'application/json',
                $webhook['signature']['header_name'] => $webhook['signature']['header'],
                'X-ZipPicks-Event' => $webhook['webhook']['event'],
                'X-ZipPicks-Event-ID' => $webhook['webhook']['id'],
            ],
            'timeout' => $options['timeout'] ?? 30,
            'sslverify' => $options['sslverify'] ?? true,
        ]);

        $duration = (microtime(true) - $startTime) * 1000;
        $statusCode = wp_remote_retrieve_response_code($response);
        $isError = is_wp_error($response);

        $result = [
            'success' => !$isError && $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'duration_ms' => $duration,
            'error' => $isError ? $response->get_error_message() : null,
            'response_body' => !$isError ? wp_remote_retrieve_body($response) : null,
        ];

        $this->logger->info('Webhook sent', array_merge($result, [
            'url' => $url,
            'event' => $webhook['webhook']['event'],
            'event_id' => $webhook['webhook']['id'],
        ]));

        return $result;
    }

    /**
     * Rotate webhook secret
     *
     * @param string $oldSecret
     * @param string $newSecret
     * @param int $gracePeriod Seconds to accept both secrets
     * @return void
     */
    public function rotateSecret(string $oldSecret, string $newSecret, int $gracePeriod = 3600): void
    {
        $rotation = [
            'old_secret' => $oldSecret,
            'new_secret' => $newSecret,
            'grace_period' => $gracePeriod,
            'expires_at' => time() + $gracePeriod,
        ];

        // Store rotation info (in production, use persistent storage)
        set_transient('zippicks_webhook_rotation', $rotation, $gracePeriod);

        $this->logger->info('Webhook secret rotation initiated', [
            'grace_period' => $gracePeriod,
            'expires_at' => date('Y-m-d H:i:s', $rotation['expires_at']),
        ]);
    }

    /**
     * Verify with rotation support
     *
     * @param string $payload
     * @param string $headerValue
     * @param string $currentSecret
     * @param array $options
     * @return bool
     */
    public function verifyWithRotation(string $payload, string $headerValue, string $currentSecret, array $options = []): bool
    {
        // Try current secret first
        if ($this->verify($payload, $headerValue, $currentSecret, $options)) {
            return true;
        }

        // Check if we're in a rotation period
        $rotation = get_transient('zippicks_webhook_rotation');
        if ($rotation && time() < $rotation['expires_at']) {
            // Try old secret
            if ($this->verify($payload, $headerValue, $rotation['old_secret'], $options)) {
                $this->logger->info('Webhook verified with old secret during rotation');
                return true;
            }
        }

        return false;
    }

    /**
     * Parse header value
     *
     * @param string $headerValue
     * @return array
     */
    protected function parseHeaderValue(string $headerValue): array
    {
        $elements = [];
        $pairs = explode(',', $headerValue);
        
        foreach ($pairs as $pair) {
            $parts = explode('=', trim($pair), 2);
            if (count($parts) === 2) {
                $elements[$parts[0]] = $parts[1];
            }
        }
        
        return $elements;
    }

    /**
     * Check if timestamp is valid
     *
     * @param int $timestamp
     * @param int $tolerance
     * @return bool
     */
    protected function isValidTimestamp(int $timestamp, int $tolerance): bool
    {
        $currentTime = time();
        $difference = abs($currentTime - $timestamp);
        
        return $difference <= $tolerance;
    }

    /**
     * Generate unique event ID
     *
     * @return string
     */
    protected function generateEventId(): string
    {
        return sprintf(
            'evt_%s_%s',
            time(),
            bin2hex(random_bytes(8))
        );
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'header_name' => 'X-ZipPicks-Signature',
            'algorithm' => 'sha256',
            'timestamp_tolerance' => 300,
            'retry_attempts' => 3,
            'retry_delay' => 1000, // milliseconds
        ];
    }
}