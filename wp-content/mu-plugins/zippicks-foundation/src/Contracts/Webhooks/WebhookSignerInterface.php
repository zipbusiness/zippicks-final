<?php
/**
 * Webhook Signer Interface
 *
 * @package ZipPicks\Foundation\Contracts\Webhooks
 */

namespace ZipPicks\Foundation\Contracts\Webhooks;

/**
 * Interface for webhook signature generation and verification
 */
interface WebhookSignerInterface
{
    /**
     * Generate signature for webhook payload
     *
     * @param string $payload
     * @param string $secret
     * @param array $options
     * @return array
     */
    public function sign(string $payload, string $secret, array $options = []): array;

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param string $headerValue
     * @param string $secret
     * @param array $options
     * @return bool
     */
    public function verify(string $payload, string $headerValue, string $secret, array $options = []): bool;

    /**
     * Create webhook event with signature
     *
     * @param string $event
     * @param array $data
     * @param string $secret
     * @return array
     */
    public function createWebhookEvent(string $event, array $data, string $secret): array;

    /**
     * Send webhook with signature
     *
     * @param string $url
     * @param array $webhook
     * @param array $options
     * @return array
     */
    public function send(string $url, array $webhook, array $options = []): array;
}