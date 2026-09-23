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

namespace Enhancely\Tests\Unit\Middleware;

use Enhancely\Enhancely\Cache\JsonLdCache;
use Enhancely\Enhancely\Client\HttpClientFactory;
use Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface;
use Enhancely\Enhancely\Middleware\JsonLdMiddleware;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * End-to-end coverage of the injection path: a page response goes in, the API
 * is answered by a mock handler, and the rendered HTML must come back with the
 * JSON-LD block in place. The existing unit tests only cover the guards and the
 * response parsing in isolation — nothing proved the script actually lands in
 * the markup.
 */
final class JsonLdInjectionTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private ?array $httpConfBackup = null;

    protected function setUp(): void
    {
        $this->httpConfBackup = $GLOBALS['TYPO3_CONF_VARS']['HTTP'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->httpConfBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['HTTP']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['HTTP'] = $this->httpConfBackup;
        }
    }

    private const PAGE_HTML = "<html><head><title>Page</title></head><body>Hello</body></html>";

    private function configuration(): ExtensionConfigurationInterface
    {
        $config = $this->createMock(ExtensionConfigurationInterface::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('getApiKey')->willReturn('sk_test');
        $config->method('getApiBaseUrl')->willReturn('https://api.enhancely.ai');
        $config->method('getExcludedPageTypes')->willReturn([]);
        $config->method('getCacheLifetime')->willReturn(86400);
        $config->method('getTimeout')->willReturn(null);

        return $config;
    }

    /**
     * An in-memory stand-in for the TYPO3 cache frontend, so a write in one
     * step is visible to the next — the shared-identifier behaviour only shows
     * up across two requests.
     */
    private function cacheBackend(): FrontendInterface
    {
        $store = [];
        $backend = $this->createMock(FrontendInterface::class);
        // Regular closure, not an arrow function: arrow functions bind $store
        // by value at creation, so writes would never be visible to reads.
        $backend->method('get')->willReturnCallback(
            static function (string $id) use (&$store) {
                return $store[$id] ?? false;
            }
        );
        $backend->method('set')->willReturnCallback(
            static function (string $id, $data) use (&$store): void {
                $store[$id] = $data;
            }
        );
        $backend->method('remove')->willReturnCallback(
            static function (string $id) use (&$store): void {
                unset($store[$id]);
            }
        );

        return $backend;
    }

    private function middleware(array $apiResponses, FrontendInterface $backend): JsonLdMiddleware
    {
        $GLOBALS['TYPO3_CONF_VARS']['HTTP'] = [
            'verify' => true,
            'handler' => HandlerStack::create(new MockHandler($apiResponses)),
        ];

        $config = $this->configuration();

        return new JsonLdMiddleware(
            $config,
            new HttpClientFactory($config, new RequestFactory(new GuzzleClientFactory())),
            new JsonLdCache($backend),
            $this->createMock(LoggerInterface::class),
            new StreamFactory(),
        );
    }

    /**
     * @param array<string, mixed>|null $pageRecord Attached as the page record
     *        behind the frontend.page.information attribute. Null leaves the
     *        attribute off entirely, the way a request looks when no page was
     *        resolved.
     */
    private function pageRequest(
        string $url = 'https://example.com/page',
        ?array $pageRecord = null,
    ): ServerRequestInterface {
        $request = new ServerRequest($url, 'GET');

        if ($pageRecord === null) {
            return $request;
        }

        $pageInformation = new PageInformation();
        $pageInformation->setId((int)($pageRecord['uid'] ?? 1));
        $pageInformation->setPageRecord($pageRecord);

        return $request->withAttribute('frontend.page.information', $pageInformation);
    }

    private function pageHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            static fn(): ResponseInterface => (new Response())
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody((new StreamFactory())->createStream(self::PAGE_HTML))
        );

        return $handler;
    }

    private static function apiPayload(): string
    {
        return (string)json_encode([
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@graph' => [['@type' => 'WebPage', 'name' => 'Page']],
            ],
            'status' => 'ready',
            'hash' => 'h1',
            'crawled_at' => '2026-08-10T09:00:00Z',
        ]);
    }

    #[Test]
    public function jsonLdIsInjectedBeforeHeadClose(): void
    {
        $middleware = $this->middleware(
            [new GuzzleResponse(200, ['ETag' => '"e1"'], self::apiPayload())],
            $this->cacheBackend()
        );

        $html = (string)$middleware->process($this->pageRequest(), $this->pageHandler())->getBody();

        self::assertStringContainsString('<script type="application/ld+json" data-source="Enhancely.ai">', $html);
        self::assertStringContainsString('"@type":"WebPage"', $html);

        // Placement matters: the block belongs inside <head>, before its close.
        self::assertLessThan(strpos($html, '</head>'), strpos($html, '<script type="application/ld+json"'));
        self::assertStringContainsString('<body>Hello</body>', $html);
    }

    #[Test]
    public function pageIsUnchangedWhenApiIsUnreachable(): void
    {
        $middleware = $this->middleware(
            [new \GuzzleHttp\Exception\ConnectException(
                'Connection refused',
                new \GuzzleHttp\Psr7\Request('POST', 'https://api.enhancely.ai/api/v1/jsonld')
            )],
            $this->cacheBackend()
        );

        $html = (string)$middleware->process($this->pageRequest(), $this->pageHandler())->getBody();

        self::assertSame(self::PAGE_HTML, $html);
    }

    #[Test]
    public function pageIsUnchangedWhileApiStillProcesses(): void
    {
        $middleware = $this->middleware(
            [new GuzzleResponse(202, [], (string)json_encode(['status' => 'processing']))],
            $this->cacheBackend()
        );

        $html = (string)$middleware->process($this->pageRequest(), $this->pageHandler())->getBody();

        self::assertSame(self::PAGE_HTML, $html);
    }

    /**
     * Second request answers 412 with no body — the JSON-LD has to come from
     * the entry the first request wrote, under the same identifier.
     */
    #[Test]
    public function cachedJsonLdIsReusedOnNotModified(): void
    {
        $backend = $this->cacheBackend();

        $first = $this->middleware(
            [new GuzzleResponse(200, ['ETag' => '"e1"'], self::apiPayload())],
            $backend
        );
        $first->process($this->pageRequest(), $this->pageHandler());

        $second = $this->middleware([new GuzzleResponse(412)], $backend);
        $html = (string)$second->process($this->pageRequest(), $this->pageHandler())->getBody();

        self::assertStringContainsString('"@type":"WebPage"', $html);
    }

    /**
     * The frontend writes under the shared identifier scheme, so a query-string
     * variant of the same page hits the entry written for the canonical URL
     * instead of triggering a fresh crawl.
     */
    #[Test]
    public function queryStringVariantReusesTheSameCacheEntry(): void
    {
        $backend = $this->cacheBackend();

        $first = $this->middleware(
            [new GuzzleResponse(200, ['ETag' => '"e1"'], self::apiPayload())],
            $backend
        );
        $first->process($this->pageRequest('https://example.com/page'), $this->pageHandler());

        $second = $this->middleware([new GuzzleResponse(412)], $backend);
        $html = (string)$second
            ->process($this->pageRequest('https://example.com/page?utm_source=newsletter'), $this->pageHandler())
            ->getBody();

        self::assertStringContainsString('"@type":"WebPage"', $html);
    }

    #[Test]
    public function nonHtmlResponsesAreLeftAlone(): void
    {
        $middleware = $this->middleware([], $this->cacheBackend());

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(
            (new Response())
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream('{"a":1}'))
        );

        $html = (string)$middleware->process($this->pageRequest(), $handler)->getBody();

        self::assertSame('{"a":1}', $html);
    }

    /**
     * The per-page opt-out an editor sets in the page properties. The page has
     * to render exactly as it would without the extension installed.
     */
    #[Test]
    public function pageFlaggedByTheEditorIsRenderedWithoutTheJsonLdBlock(): void
    {
        $middleware = $this->middleware(
            [new GuzzleResponse(200, ['ETag' => '"e1"'], self::apiPayload())],
            $this->cacheBackend()
        );

        $html = (string)$middleware
            ->process(
                $this->pageRequest('https://example.com/page', ['uid' => 1, 'doktype' => 1, 'tx_enhancely_hide_jsonld' => 1]),
                $this->pageHandler()
            )
            ->getBody();

        self::assertSame(self::PAGE_HTML, $html);
    }

    /**
     * Suppressing the output stops at the markup: the API answer is still
     * fetched and stored, so unchecking the box shows current JSON-LD straight
     * away instead of waiting for a fresh crawl. Proven by the second request,
     * which is answered 412 with no body and can only get its JSON-LD from the
     * entry the flagged request wrote.
     */
    #[Test]
    public function flaggedPageStillWarmsTheSharedCacheEntry(): void
    {
        $backend = $this->cacheBackend();

        $flagged = $this->middleware(
            [new GuzzleResponse(200, ['ETag' => '"e1"'], self::apiPayload())],
            $backend
        );
        $flagged->process(
            $this->pageRequest('https://example.com/page', ['uid' => 1, 'doktype' => 1, 'tx_enhancely_hide_jsonld' => 1]),
            $this->pageHandler()
        );

        $afterUnchecking = $this->middleware([new GuzzleResponse(412)], $backend);
        $html = (string)$afterUnchecking
            ->process(
                $this->pageRequest('https://example.com/page', ['uid' => 1, 'doktype' => 1, 'tx_enhancely_hide_jsonld' => 0]),
                $this->pageHandler()
            )
            ->getBody();

        self::assertStringContainsString('"@type":"WebPage"', $html);
    }

    /**
     * An installation that updated the extension but has not run
     * `database:updateschema` has no such column on its page records. That must
     * not read as "suppressed", or the update would silently drop the JSON-LD
     * from every page.
     */
    #[Test]
    public function pageRecordWithoutTheOptOutColumnIsStillInjected(): void
    {
        $middleware = $this->middleware(
            [new GuzzleResponse(200, ['ETag' => '"e1"'], self::apiPayload())],
            $this->cacheBackend()
        );

        $html = (string)$middleware
            ->process(
                $this->pageRequest('https://example.com/page', ['uid' => 1, 'doktype' => 1]),
                $this->pageHandler()
            )
            ->getBody();

        self::assertStringContainsString('"@type":"WebPage"', $html);
    }
}
