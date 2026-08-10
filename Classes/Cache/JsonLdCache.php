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

namespace Enhancely\Enhancely\Cache;

use Enhancely\Enhancely\Client\JsonLdResponse;
use Enhancely\Enhancely\Client\UrlNormalizer;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Single owner of the enhancely_etag cache: identifier scheme and payload shape.
 *
 * Frontend (middleware) and backend (info module) previously derived cache
 * identifiers independently — md5 with a prefix vs. raw sha256, one normalizing
 * the URL and one not. Both wrote into the same cache, so neither could ever
 * read the other's entries: the backend module reported "cache miss" for pages
 * the frontend had just cached, and every module visit issued a second API call
 * for a URL that was already stored. Keeping the scheme in one place is what
 * keeps the two sides addressing the same entry.
 */
final class JsonLdCache
{
    /**
     * Distinguishes our entries inside a shared cache backend and keeps the
     * identifier a valid TYPO3 cache entry identifier (must start with a letter).
     */
    private const IDENTIFIER_PREFIX = 'enhancely_';

    public function __construct(
        private readonly FrontendInterface $cache,
    ) {}

    /**
     * The URL is normalized first: the API answers for the normalized URL, so
     * `?utm_source=…` variants must not fragment the cache.
     */
    public function identifierFor(string $url): string
    {
        return self::IDENTIFIER_PREFIX . md5(UrlNormalizer::normalize($url));
    }

    /**
     * @return array{etag: ?string, jsonld: string, meta: array<string, mixed>}|null
     */
    public function get(string $url): ?array
    {
        $entry = $this->cache->get($this->identifierFor($url));

        return is_array($entry) ? $entry : null;
    }

    public function remove(string $url): void
    {
        $this->cache->remove($this->identifierFor($url));
    }

    /**
     * Store one URL's payload.
     *
     * Two layers:
     *  - 'etag' + 'jsonld' — read by the frontend middleware on subsequent
     *    requests for conditional ETag handling.
     *  - 'meta' — read by the backend info module. Backwards compatible:
     *    entries written by older versions lack 'meta', and the backend treats
     *    those as a cache miss.
     */
    public function write(string $url, JsonLdResponse $response, int $lifetime): void
    {
        $this->cache->set(
            $this->identifierFor($url),
            [
                'etag' => $response->etag(),
                'jsonld' => (string)$response,
                'meta' => [
                    'crawled_at' => $response->crawledAt(),
                    'status' => $response->apiStatus(),
                    'hash' => $response->hash(),
                    'graph' => $response->jsonld(),
                    'cached_at' => time(),
                ],
            ],
            ['pages'],
            $lifetime
        );
    }
}
