<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Client;

use Enhancely\Enhancely\Client\Exception\ApiException;
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
    private static ?string $apiBaseUrl = null;
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
     * Set a custom API base URL (without /api/v1/jsonld path).
     */
    public static function setApiBaseUrl(string $baseUrl): void
    {
        self::$apiBaseUrl = rtrim(trim($baseUrl), '/');
        // Reset HTTP client so it picks up new base URL
        self::$httpClient = null;
    }

    /**
     * @deprecated Use setApiBaseUrl() instead
     */
    public static function setApiEndpoint(string $endpoint): void
    {
        self::setApiBaseUrl($endpoint);
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
            return JsonLdResponse::createError($e->getMessage(), $e->getProblemDetails());
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
        self::$apiBaseUrl = null;
        self::$httpClient = null;
    }

    private static function getHttpClient(): HttpClientInterface
    {
        if (self::$httpClient === null) {
            if (self::$apiKey === null || self::$apiKey === '') {
                throw new ApiException('API key not set. Call Client::setApiKey() first.');
            }

            $args = [new GuzzleClient(), self::$apiKey];
            if (self::$apiBaseUrl !== null && self::$apiBaseUrl !== '') {
                $args[] = self::$apiBaseUrl;
            }

            self::$httpClient = new HttpClient(...$args);
        }

        return self::$httpClient;
    }
}
