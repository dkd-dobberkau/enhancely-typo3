<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Client;

/**
 * Interface for HTTP client implementations.
 */
interface HttpClientInterface
{
    /**
     * Request JSON-LD for a URL.
     *
     * @param string $url The page URL to get JSON-LD for
     * @param string|null $etag Cached ETag for conditional request
     */
    public function postJsonLd(string $url, ?string $etag = null): JsonLdResponse;
}
