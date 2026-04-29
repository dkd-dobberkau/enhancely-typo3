<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Client;

use Enhancely\Enhancely\Configuration\ExtensionConfiguration;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Builds a configured HttpClient per request.
 *
 * Replaces the static Client::setApiKey()/setApiBaseUrl() facade for
 * server-side use. The static facade remains for direct library callers,
 * but the middleware injects this factory to avoid leaking state across
 * requests in long-running PHP runtimes (Swoole, RoadRunner, FrankenPHP).
 */
final class HttpClientFactory
{
    public function __construct(
        private readonly ExtensionConfiguration $configuration,
    ) {}

    public function create(): HttpClientInterface
    {
        $apiKey = $this->configuration->getApiKey();
        if ($apiKey === '') {
            throw new \RuntimeException('Enhancely API key is not configured');
        }

        $guzzle = new GuzzleClient([
            // Explicit so a global Guzzle config override cannot disable TLS verification.
            'verify' => true,
        ]);

        return new HttpClient(
            $guzzle,
            $apiKey,
            $this->configuration->getApiBaseUrl(),
        );
    }
}
