<?php

declare(strict_types=1);

namespace Enhancely;

use Enhancely\Exception\ApiException;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Static facade for the Enhancely API.
 *
 * Usage:
 *   Client::setApiKey('sk_live_xxx');
 *   $response = Client::jsonld('https://example.com/page');
 *
 *   if ($response->ready()) {
 *       echo $response; // Outputs <script type="application/ld+json">...</script>
 *   }
 */
final class Client
{
    private static ?string $apiKey = null;
    private static ?string $apiEndpoint = null;
    private static ?HttpClientInterface $httpClient = null;

    /**
     * Set the API key for all requests.
     */
    public static function setApiKey(string $apiKey): void
    {
        self::$apiKey = trim($apiKey);
        // Reset HTTP client so it picks up new API key
        self::$httpClient = null;
    }

    /**
     * Set a custom API endpoint URL.
     */
    public static function setApiEndpoint(string $endpoint): void
    {
        self::$apiEndpoint = rtrim(trim($endpoint), '/');
        // Reset HTTP client so it picks up new endpoint
        self::$httpClient = null;
    }

    /**
     * Request JSON-LD for a URL.
     *
     * @param string $url The page URL to get JSON-LD for
     * @param string|null $etag Cached ETag for conditional request
     * @return JsonLdResponse Response object with status and data
     */
    public static function jsonld(string $url, ?string $etag = null): JsonLdResponse
    {
        try {
            $normalizedUrl = UrlNormalizer::normalize($url);
            return self::getHttpClient()->postJsonLd($normalizedUrl, $etag);
        } catch (ApiException $e) {
            return JsonLdResponse::createError($e->getMessage());
        } catch (\Throwable $e) {
            return JsonLdResponse::createError('Unexpected error: ' . $e->getMessage());
        }
    }

    /**
     * Set a custom HTTP client (useful for testing).
     */
    public static function setHttpClient(?HttpClientInterface $client): void
    {
        self::$httpClient = $client;
    }

    /**
     * Reset the client state (useful for testing).
     */
    public static function reset(): void
    {
        self::$apiKey = null;
        self::$apiEndpoint = null;
        self::$httpClient = null;
    }

    private static function getHttpClient(): HttpClientInterface
    {
        if (self::$httpClient === null) {
            if (self::$apiKey === null || self::$apiKey === '') {
                throw new ApiException('API key not set. Call Client::setApiKey() first.');
            }

            $args = [new GuzzleClient(), self::$apiKey];
            if (self::$apiEndpoint !== null && self::$apiEndpoint !== '') {
                $args[] = self::$apiEndpoint;
            }

            self::$httpClient = new HttpClient(...$args);
        }

        return self::$httpClient;
    }
}
