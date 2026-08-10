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

namespace Enhancely\Tests\Unit\Cache;

use Enhancely\Enhancely\Cache\JsonLdCache;
use Enhancely\Enhancely\Client\JsonLdResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class JsonLdCacheTest extends TestCase
{
    private function cacheWith(FrontendInterface $backend): JsonLdCache
    {
        return new JsonLdCache($backend);
    }

    #[Test]
    public function writtenPayloadIncludesMetaBlock(): void
    {
        $apiResponse = JsonLdResponse::fromApiResponse(
            200,
            [
                'jsonld' => ['@graph' => [['@type' => 'WebPage', 'name' => 'X']]],
                'crawled_at' => '2026-05-18T12:32:15.410Z',
                'status' => 'ready',
                'hash' => 'abc123',
            ],
            'etag-xyz'
        );

        $backend = $this->createMock(FrontendInterface::class);
        $captured = null;
        $backend->expects(self::once())
            ->method('set')
            ->willReturnCallback(function ($id, $payload) use (&$captured): void {
                $captured = $payload;
            });

        $this->cacheWith($backend)->write('https://example.com/page', $apiResponse, 86400);

        self::assertSame('etag-xyz', $captured['etag']);
        self::assertStringStartsWith('<script', $captured['jsonld']);
        self::assertSame('2026-05-18T12:32:15.410Z', $captured['meta']['crawled_at']);
        self::assertSame('ready', $captured['meta']['status']);
        self::assertSame('abc123', $captured['meta']['hash']);
        self::assertSame(
            [['@type' => 'WebPage', 'name' => 'X']],
            $captured['meta']['graph']['@graph']
        );
        self::assertIsInt($captured['meta']['cached_at']);
    }

    /**
     * Regression guard. The frontend middleware and the backend info module
     * used to build cache identifiers independently (prefixed md5 of the
     * normalized URL vs. raw sha256 of the raw URL). Both wrote into the same
     * cache, so the module never saw an entry the frontend had written and
     * fired a second API call for a URL that was already cached. Both sides now
     * go through this class, so one derivation is all there is.
     */
    #[Test]
    public function identifierIsStableAcrossInstances(): void
    {
        $frontendSide = $this->cacheWith($this->createMock(FrontendInterface::class));
        $backendSide = $this->cacheWith($this->createMock(FrontendInterface::class));

        self::assertSame(
            $frontendSide->identifierFor('https://example.com/page'),
            $backendSide->identifierFor('https://example.com/page')
        );
    }

    #[Test]
    public function identifierIgnoresQueryStringAndTrailingSlash(): void
    {
        $cache = $this->cacheWith($this->createMock(FrontendInterface::class));
        $canonical = $cache->identifierFor('https://example.com/page');

        self::assertSame($canonical, $cache->identifierFor('https://example.com/page/'));
        self::assertSame($canonical, $cache->identifierFor('https://example.com/page?utm_source=news'));
        self::assertSame($canonical, $cache->identifierFor('https://example.com/page#top'));
    }

    #[Test]
    public function identifierStartsWithLetterAsTypo3Requires(): void
    {
        $cache = $this->cacheWith($this->createMock(FrontendInterface::class));

        self::assertMatchesRegularExpression(
            '/^[a-zA-Z][a-zA-Z0-9_%\-&]*$/',
            $cache->identifierFor('https://example.com/page')
        );
    }

    #[Test]
    public function getReturnsNullForCacheMiss(): void
    {
        $backend = $this->createMock(FrontendInterface::class);
        $backend->method('get')->willReturn(false);

        self::assertNull($this->cacheWith($backend)->get('https://example.com/'));
    }

    #[Test]
    public function removeUsesTheSameIdentifierAsWrite(): void
    {
        $backend = $this->createMock(FrontendInterface::class);
        $cache = $this->cacheWith($backend);
        $expected = $cache->identifierFor('https://example.com/page');

        $backend->expects(self::once())->method('remove')->with($expected);

        $cache->remove('https://example.com/page');
    }
}
