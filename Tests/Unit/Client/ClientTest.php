<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Client;

use Enhancely\Enhancely\Client\Client;
use Enhancely\Enhancely\Client\HttpClientInterface;
use Enhancely\Enhancely\Client\JsonLdResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Client::reset();
    }

    #[Test]
    public function jsonldReturnsResponseFromHttpClient(): void
    {
        $expectedResponse = JsonLdResponse::fromApiResponse(200, [
            'jsonld' => ['@type' => 'WebPage'],
        ], 'etag-abc');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('postJsonLd')
            ->with('https://example.com/page', 'cached-etag')
            ->willReturn($expectedResponse);

        Client::setApiKey('test-key');
        Client::setHttpClient($httpClient);

        $response = Client::jsonld('https://example.com/page/', 'cached-etag');

        self::assertTrue($response->ready());
        self::assertSame('etag-abc', $response->etag());
    }

    #[Test]
    public function jsonldNormalizesUrl(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('postJsonLd')
            ->with('https://example.com/page', null)
            ->willReturn(JsonLdResponse::createNotModified());

        Client::setApiKey('test-key');
        Client::setHttpClient($httpClient);

        // URL with trailing slash and query params
        Client::jsonld('https://example.com/page/?utm_source=test#section');
    }

    #[Test]
    public function jsonldReturnsErrorOnException(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->method('postJsonLd')
            ->willThrowException(new \RuntimeException('Connection failed'));

        Client::setApiKey('test-key');
        Client::setHttpClient($httpClient);

        $response = Client::jsonld('https://example.com/page');

        self::assertNotNull($response->error());
        self::assertStringContainsString('Connection failed', $response->error());
    }

    #[Test]
    public function setApiKeyResetsHttpClient(): void
    {
        $httpClient1 = $this->createMock(HttpClientInterface::class);
        $httpClient1
            ->expects(self::once())
            ->method('postJsonLd')
            ->willReturn(JsonLdResponse::createNotModified());

        Client::setApiKey('key-1');
        Client::setHttpClient($httpClient1);
        Client::jsonld('https://example.com');

        // Setting new API key should reset client
        Client::setApiKey('key-2');

        // This would fail if httpClient1 was still used
        $httpClient2 = $this->createMock(HttpClientInterface::class);
        $httpClient2
            ->expects(self::once())
            ->method('postJsonLd')
            ->willReturn(JsonLdResponse::createProcessing(201));

        Client::setHttpClient($httpClient2);
        Client::jsonld('https://example.com');
    }

    #[Test]
    public function resetClearsState(): void
    {
        Client::setApiKey('test-key');
        Client::reset();

        // After reset, calling jsonld without setApiKey should return error
        $response = Client::jsonld('https://example.com');

        self::assertNotNull($response->error());
        self::assertStringContainsString('API key', $response->error());
    }
}
