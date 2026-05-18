<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Middleware;

use Enhancely\Enhancely\Client\JsonLdResponse;
use Enhancely\Enhancely\Configuration\ExtensionConfiguration;
use Enhancely\Enhancely\Middleware\JsonLdMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class JsonLdMiddlewareTest extends TestCase
{
    #[Test]
    public function cachedPayloadIncludesMetaBlock(): void
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

        $cache = $this->createMock(FrontendInterface::class);
        $captured = null;
        $cache->expects(self::once())
            ->method('set')
            ->willReturnCallback(function ($id, $payload) use (&$captured): void {
                $captured = $payload;
            });

        JsonLdMiddleware::writeCachePayload($cache, 'cache-id', $apiResponse, 86400);

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
}
