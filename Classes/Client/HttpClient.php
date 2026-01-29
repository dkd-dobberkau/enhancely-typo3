<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Client;

use Enhancely\Enhancely\Client\Exception\ApiException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * HTTP client for Enhancely API communication.
 */
final class HttpClient implements HttpClientInterface
{
    private const DEFAULT_BASE_URL = 'https://api.enhancely.ai';
    private const ENDPOINT_JSONLD = '/api/v1/jsonld';

    private readonly string $baseUrl;

    public function __construct(
        private readonly GuzzleClient $guzzle,
        private readonly string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Request JSON-LD for a URL.
     *
     * @param string $url The page URL to get JSON-LD for
     * @param string|null $etag Cached ETag for conditional request
     * @throws ApiException On API errors
     */
    public function postJsonLd(string $url, ?string $etag = null): JsonLdResponse
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($etag !== null) {
            $headers['If-None-Match'] = $etag;
        }

        try {
            $response = $this->guzzle->post($this->baseUrl . self::ENDPOINT_JSONLD, [
                RequestOptions::HEADERS => $headers,
                RequestOptions::JSON => ['url' => $url],
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::TIMEOUT => 10,
                RequestOptions::CONNECT_TIMEOUT => 5,
            ]);

            $statusCode = $response->getStatusCode();
            $responseEtag = $response->getHeaderLine('ETag') ?: null;

            // Handle different status codes
            return match ($statusCode) {
                200 => $this->handleSuccessResponse($response, $responseEtag),
                201, 202 => JsonLdResponse::createProcessing($statusCode),
                412 => JsonLdResponse::createNotModified(),
                401 => throw new ApiException('Invalid API key', $statusCode),
                429 => throw new ApiException(
                    'Rate limit exceeded. Reset at: ' . $response->getHeaderLine('RateLimit-Reset'),
                    $statusCode
                ),
                default => $this->handleErrorResponse($response, $statusCode),
            };
        } catch (GuzzleException $e) {
            throw new ApiException(
                'HTTP request failed: ' . $e->getMessage(),
                0,
                null,
                $e
            );
        }
    }

    /**
     * @return JsonLdResponse
     */
    private function handleSuccessResponse(
        \Psr\Http\Message\ResponseInterface $response,
        ?string $etag
    ): JsonLdResponse {
        $body = (string)$response->getBody();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new ApiException('Invalid JSON response from API', 200);
        }

        return JsonLdResponse::fromApiResponse(200, $data, $etag);
    }

    /**
     * @throws ApiException
     */
    private function handleErrorResponse(
        \Psr\Http\Message\ResponseInterface $response,
        int $statusCode
    ): never {
        $body = (string)$response->getBody();
        $data = json_decode($body, true);

        $message = 'API error';
        $problemDetails = null;

        // Try to parse RFC 7807 Problem Details
        if (is_array($data)) {
            $problemDetails = $data;
            $message = $data['title'] ?? $data['detail'] ?? $message;
        }

        throw new ApiException($message, $statusCode, $problemDetails);
    }
}
