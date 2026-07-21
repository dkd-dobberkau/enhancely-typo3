<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Middleware;

use Enhancely\Enhancely\Client\HttpClientFactory;
use Enhancely\Enhancely\Client\JsonLdResponse;
use Enhancely\Enhancely\Configuration\ExtensionConfiguration;
use Enhancely\Enhancely\Middleware\JsonLdMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Frontend\Page\PageInformation;

final class JsonLdMiddlewareTest extends TestCase
{
    /**
     * Excluded doktypes must be read from the frontend.page.information
     * request attribute (PageInformation), the v13+ replacement for the
     * TypoScriptFrontendController that is removed in v14 (#105230).
     */
    #[Test]
    public function skipsInjectionForExcludedPageTypeViaPageInformation(): void
    {
        $configuration = $this->createMock(ExtensionConfiguration::class);
        $configuration->method('isEnabled')->willReturn(true);
        $configuration->method('getApiKey')->willReturn('sk_test');
        $configuration->method('getExcludedPageTypes')->willReturn([4]);

        $httpClientFactory = $this->createMock(HttpClientFactory::class);
        // The API must not be contacted for an excluded page type.
        $httpClientFactory->expects(self::never())->method('create');

        $pageInformation = new PageInformation();
        $pageInformation->setId(1);
        $pageInformation->setPageRecord(['uid' => 1, 'doktype' => 4]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(
            static fn(string $name, $default = null) => $name === 'frontend.page.information'
                ? $pageInformation
                : $default
        );

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaderLine')->willReturn('text/html; charset=utf-8');
        $response->method('getStatusCode')->willReturn(200);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $middleware = new JsonLdMiddleware(
            $configuration,
            $httpClientFactory,
            $this->createMock(FrontendInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(StreamFactory::class),
        );

        // Response passes through unmodified; no injection happened.
        self::assertSame($response, $middleware->process($request, $handler));
    }

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
