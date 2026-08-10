<?php

declare(strict_types=1);

/*
 * This file is part of the "enhancely" extension for TYPO3 CMS.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Enhancely\Enhancely\Client;

use Enhancely\Enhancely\Client\Exception\ApiException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * HTTP client for Enhancely API communication.
 *
 * Requests go through TYPO3's RequestFactory rather than a self-built Guzzle
 * client, so everything an administrator configures in
 * $GLOBALS['TYPO3_CONF_VARS']['HTTP'] applies — most importantly proxy settings
 * and the global timeout. A self-built client ignores all of it, which breaks
 * every request on installations that reach the internet through a proxy.
 *
 * @see https://docs.typo3.org/permalink/t3coreapi:http-requests-to-external-sources
 */
final class HttpClient implements HttpClientInterface
{
    private const DEFAULT_BASE_URL = 'https://api.enhancely.ai';
    private const ENDPOINT_JSONLD = '/api/v1/jsonld';
    private const MAX_RESPONSE_BYTES = 1048576; // 1 MiB

    private readonly string $baseUrl;

    /**
     * @param int|null $timeout Overrides the global TYPO3 HTTP timeout when set
     *                          via extension configuration; null leaves TYPO3's
     *                          own value in charge.
     */
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly ?int $timeout = null,
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

        $options = [
            RequestOptions::HEADERS => $headers,
            RequestOptions::JSON => ['url' => $url],
            RequestOptions::HTTP_ERRORS => false,
            // Deliberately not delegated to TYPO3: this request carries the API
            // key as a bearer token, so a global verify=false must not silently
            // downgrade it. Everything else (proxy, timeout) comes from TYPO3.
            RequestOptions::VERIFY => true,
        ];

        if ($this->timeout !== null) {
            $options[RequestOptions::TIMEOUT] = $this->timeout;
            $options[RequestOptions::CONNECT_TIMEOUT] = $this->timeout;
        }

        try {
            $response = $this->requestFactory->request(
                $this->baseUrl . self::ENDPOINT_JSONLD,
                'POST',
                $options
            );

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

    private function handleSuccessResponse(
        ResponseInterface $response,
        ?string $etag
    ): JsonLdResponse {
        $body = $this->readBoundedBody($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new ApiException('Invalid JSON response from API', 200);
        }

        return JsonLdResponse::fromApiResponse(200, $data, $etag);
    }

    /**
     * Read the response body up to MAX_RESPONSE_BYTES.
     *
     * Throws if Content-Length advertises more, or if the actual stream
     * exceeds the cap. Prevents OOM on a malicious or compromised endpoint.
     */
    private function readBoundedBody(ResponseInterface $response): string
    {
        $contentLength = $response->getHeaderLine('Content-Length');
        if ($contentLength !== '' && (int)$contentLength > self::MAX_RESPONSE_BYTES) {
            throw new ApiException('Response too large', $response->getStatusCode());
        }

        $stream = $response->getBody();
        $body = '';
        $remaining = self::MAX_RESPONSE_BYTES;
        while ($remaining > 0 && !$stream->eof()) {
            $chunk = $stream->read(min(8192, $remaining));
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
            $remaining -= strlen($chunk);
        }

        if (!$stream->eof()) {
            throw new ApiException('Response too large', $response->getStatusCode());
        }

        return $body;
    }

    /**
     * @throws ApiException
     */
    private function handleErrorResponse(
        ResponseInterface $response,
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
