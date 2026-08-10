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

namespace Enhancely\Tests\Unit\Client;

use Enhancely\Enhancely\Client\Exception\ApiException;
use Enhancely\Enhancely\Client\HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\CMS\Core\Http\RequestFactory;

final class HttpClientTest extends TestCase
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

    private ?MockHandler $mockHandler = null;

    /**
     * Requests are routed through TYPO3's RequestFactory, so the mock handler
     * is injected the same way an administrator's proxy or timeout settings
     * arrive: via $GLOBALS['TYPO3_CONF_VARS']['HTTP'].
     */
    private function createHttpClient(array $responses, ?int $timeout = null, array $globalHttpConf = []): HttpClient
    {
        $this->mockHandler = new MockHandler($responses);
        $GLOBALS['TYPO3_CONF_VARS']['HTTP'] = $globalHttpConf + [
            'verify' => true,
            'handler' => HandlerStack::create($this->mockHandler),
        ];

        return new HttpClient(
            new RequestFactory(new GuzzleClientFactory()),
            'test-api-key',
            'https://api.enhancely.ai',
            $timeout
        );
    }

    /**
     * The reason for routing through RequestFactory at all: a self-built Guzzle
     * client silently ignores the administrator's proxy configuration, which
     * breaks every API call on installations that egress through a proxy.
     */
    #[Test]
    public function globalTypo3HttpConfigurationReachesTheRequest(): void
    {
        $client = $this->createHttpClient(
            [new Response(200, [], json_encode(['jsonld' => ['@type' => 'WebPage']]))],
            null,
            ['proxy' => 'http://proxy.example.internal:3128', 'timeout' => 42]
        );

        $client->postJsonLd('https://example.com/page');

        $options = $this->mockHandler->getLastOptions();
        self::assertSame('http://proxy.example.internal:3128', $options['proxy']);
        self::assertSame(42, $options['timeout']);
    }

    #[Test]
    public function extensionTimeoutOverridesTheGlobalTypo3Timeout(): void
    {
        $client = $this->createHttpClient(
            [new Response(200, [], json_encode(['jsonld' => ['@type' => 'WebPage']]))],
            5,
            ['timeout' => 42]
        );

        $client->postJsonLd('https://example.com/page');

        $options = $this->mockHandler->getLastOptions();
        self::assertSame(5, $options['timeout']);
        self::assertSame(5, $options['connect_timeout']);
    }

    /**
     * TLS verification is the one option not delegated: the request carries the
     * API key as a bearer token, so a global verify=false must not downgrade it.
     */
    #[Test]
    public function tlsVerificationStaysOnDespiteGlobalOverride(): void
    {
        $client = $this->createHttpClient(
            [new Response(200, [], json_encode(['jsonld' => ['@type' => 'WebPage']]))],
            null,
            ['verify' => false]
        );

        $client->postJsonLd('https://example.com/page');

        self::assertTrue($this->mockHandler->getLastOptions()['verify']);
    }

    #[Test]
    public function postJsonLdReturnsReadyResponseOn200(): void
    {
        $jsonld = ['@context' => 'https://schema.org', '@type' => 'WebPage'];
        $body = json_encode(['jsonld' => $jsonld]);

        $client = $this->createHttpClient([
            new Response(200, ['ETag' => '"abc123"'], $body),
        ]);

        $response = $client->postJsonLd('https://example.com/page');

        self::assertTrue($response->ready());
        self::assertSame('"abc123"', $response->etag());
        self::assertSame($jsonld, $response->jsonld());
    }

    #[Test]
    public function postJsonLdReturnsNotModifiedOn412(): void
    {
        $client = $this->createHttpClient([
            new Response(412),
        ]);

        $response = $client->postJsonLd('https://example.com/page', 'cached-etag');

        self::assertTrue($response->notModified());
        self::assertFalse($response->ready());
    }

    #[Test]
    public function postJsonLdReturnsProcessingOn201(): void
    {
        $client = $this->createHttpClient([
            new Response(201, [], json_encode(['status' => 'created'])),
        ]);

        $response = $client->postJsonLd('https://example.com/page');

        self::assertTrue($response->isProcessing());
        self::assertFalse($response->ready());
    }

    #[Test]
    public function postJsonLdReturnsProcessingOn202(): void
    {
        $client = $this->createHttpClient([
            new Response(202, [], json_encode(['status' => 'updating'])),
        ]);

        $response = $client->postJsonLd('https://example.com/page');

        self::assertTrue($response->isProcessing());
    }

    #[Test]
    public function postJsonLdThrowsOnUnauthorized(): void
    {
        $client = $this->createHttpClient([
            new Response(401),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid API key');

        $client->postJsonLd('https://example.com/page');
    }

    #[Test]
    public function postJsonLdThrowsOnRateLimit(): void
    {
        $client = $this->createHttpClient([
            new Response(429, ['RateLimit-Reset' => '1700000000']),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $client->postJsonLd('https://example.com/page');
    }

    #[Test]
    public function postJsonLdThrowsOnServerError(): void
    {
        $problemDetails = [
            'type' => 'https://api.enhancely.ai/problems/internal-error',
            'title' => 'Internal Server Error',
            'status' => 500,
        ];

        $client = $this->createHttpClient([
            new Response(500, [], json_encode($problemDetails)),
        ]);

        try {
            $client->postJsonLd('https://example.com/page');
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(500, $e->getStatusCode());
            self::assertSame('Internal Server Error', $e->getMessage());
            self::assertSame($problemDetails, $e->getProblemDetails());
        }
    }

    #[Test]
    public function postJsonLdThrowsOnInvalidJson(): void
    {
        $client = $this->createHttpClient([
            new Response(200, [], 'not valid json'),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid JSON response');

        $client->postJsonLd('https://example.com/page');
    }

    #[Test]
    public function postJsonLdRejectsResponseExceedingSizeLimit(): void
    {
        // Construct a body larger than the 1 MiB limit
        $oversized = str_repeat('a', 1024 * 1024 + 1);
        $body = json_encode(['jsonld' => ['@type' => 'WebPage', 'name' => $oversized]]);

        $client = $this->createHttpClient([
            new Response(200, ['Content-Length' => (string)strlen($body)], $body),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response too large');

        $client->postJsonLd('https://example.com/page');
    }

    #[Test]
    public function postJsonLdRejectsResponseExceedingSizeLimitWithoutContentLength(): void
    {
        // Server omits Content-Length; client must still cap the read.
        $oversized = str_repeat('a', 1024 * 1024 + 1);
        $body = json_encode(['jsonld' => ['@type' => 'WebPage', 'name' => $oversized]]);

        $client = $this->createHttpClient([
            new Response(200, [], $body),
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Response too large');

        $client->postJsonLd('https://example.com/page');
    }
}
