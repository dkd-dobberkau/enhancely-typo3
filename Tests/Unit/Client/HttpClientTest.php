<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Client;

use Enhancely\Exception\ApiException;
use Enhancely\HttpClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpClientTest extends TestCase
{
    private function createHttpClient(array $responses): HttpClient
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $handlerStack]);

        return new HttpClient($guzzle, 'test-api-key');
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
}
