<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Client;

use Enhancely\Enhancely\Client\JsonLdResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonLdResponseTest extends TestCase
{
    #[Test]
    public function fromApiResponseWithReadyStatus(): void
    {
        $data = [
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'Test Page',
            ],
        ];

        $response = JsonLdResponse::fromApiResponse(200, $data, 'etag-123');

        self::assertTrue($response->ready());
        self::assertFalse($response->notModified());
        self::assertFalse($response->isProcessing());
        self::assertNull($response->error());
        self::assertSame('etag-123', $response->etag());
        self::assertSame($data['jsonld'], $response->jsonld());
    }

    #[Test]
    public function notModifiedFactory(): void
    {
        $response = JsonLdResponse::createNotModified();

        self::assertFalse($response->ready());
        self::assertTrue($response->notModified());
        self::assertFalse($response->isProcessing());
        self::assertNull($response->error());
        self::assertNull($response->etag());
    }

    #[Test]
    public function processingFactoryWith201(): void
    {
        $response = JsonLdResponse::createProcessing(201);

        self::assertFalse($response->ready());
        self::assertFalse($response->notModified());
        self::assertTrue($response->isProcessing());
        self::assertNull($response->error());
    }

    #[Test]
    public function processingFactoryWith202(): void
    {
        $response = JsonLdResponse::createProcessing(202);

        self::assertFalse($response->ready());
        self::assertFalse($response->notModified());
        self::assertTrue($response->isProcessing());
        self::assertNull($response->error());
    }

    #[Test]
    public function errorFactory(): void
    {
        $response = JsonLdResponse::createError('Something went wrong');

        self::assertFalse($response->ready());
        self::assertFalse($response->notModified());
        self::assertFalse($response->isProcessing());
        self::assertSame('Something went wrong', $response->error());
    }

    #[Test]
    public function toStringReturnsScriptTag(): void
    {
        $data = [
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
            ],
        ];

        $response = JsonLdResponse::fromApiResponse(200, $data, null);
        $output = (string)$response;

        self::assertStringStartsWith('<script type="application/ld+json">', $output);
        self::assertStringEndsWith('</script>', $output);
        self::assertStringContainsString('"@context":"https://schema.org"', $output);
        self::assertStringContainsString('"@type":"WebPage"', $output);
    }

    #[Test]
    public function toStringReturnsEmptyStringWhenNoJsonLd(): void
    {
        $response = JsonLdResponse::createNotModified();

        self::assertSame('', (string)$response);
    }

    #[Test]
    public function toStringReturnsEmptyStringForErrorResponse(): void
    {
        $response = JsonLdResponse::createError('API failed');

        self::assertSame('', (string)$response);
    }

    #[Test]
    public function readyReturnsFalseWhenJsonLdMissing(): void
    {
        $response = JsonLdResponse::fromApiResponse(200, ['other' => 'data'], null);

        self::assertFalse($response->ready());
    }

    #[Test]
    public function jsonldReturnsNullWhenMissing(): void
    {
        $response = JsonLdResponse::fromApiResponse(200, ['other' => 'data'], null);

        self::assertNull($response->jsonld());
    }

    #[Test]
    public function toStringEscapesScriptBreakoutAttempts(): void
    {
        $data = [
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'description' => '</script><img src=x onerror=alert(1)>',
            ],
        ];

        $response = JsonLdResponse::fromApiResponse(200, $data, null);
        $output = (string)$response;

        // Exactly one closing </script> (the wrapper), payload must not break out
        self::assertSame(1, substr_count($output, '</script>'));
        // No raw '<' or '>' from the payload — both must be < / >
        $payloadStart = strpos($output, '"description"');
        self::assertNotFalse($payloadStart);
        $payload = substr($output, (int)$payloadStart, strpos($output, '</script>') - (int)$payloadStart);
        self::assertStringNotContainsString('<', $payload);
        self::assertStringNotContainsString('>', $payload);
        self::assertStringContainsString('<', $output);
        self::assertStringContainsString('>', $output);
    }

    #[Test]
    public function toStringEscapesAmpersandsAndQuotes(): void
    {
        $data = [
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => 'Tom & Jerry "quoted" \'apos\'',
            ],
        ];

        $response = JsonLdResponse::fromApiResponse(200, $data, null);
        $output = (string)$response;

        // Within the JSON payload, &, ', and embedded " must be hex-escaped
        $payloadStart = (int)strpos($output, '"name"');
        $payloadEnd = (int)strpos($output, '</script>');
        $payload = substr($output, $payloadStart, $payloadEnd - $payloadStart);

        self::assertStringNotContainsString('&', $payload);
        self::assertStringNotContainsString("'", $payload);
        self::assertStringContainsString('\\u0026', $payload);
        self::assertStringContainsString('\\u0027', $payload);
        self::assertStringContainsString('\\u0022', $payload);
    }

    #[Test]
    public function crawledAtReturnsTimestampFromApiResponse(): void
    {
        $response = JsonLdResponse::fromApiResponse(200, [
            'jsonld' => ['@type' => 'WebPage'],
            'crawled_at' => '2026-05-18T12:32:15.410Z',
        ], 'etag-abc');

        self::assertSame('2026-05-18T12:32:15.410Z', $response->crawledAt());
    }

    #[Test]
    public function apiStatusReturnsStatusFromApiResponse(): void
    {
        $response = JsonLdResponse::fromApiResponse(200, [
            'jsonld' => ['@type' => 'WebPage'],
            'status' => 'ready',
        ]);

        self::assertSame('ready', $response->apiStatus());
    }

    #[Test]
    public function hashReturnsHashFromApiResponse(): void
    {
        $response = JsonLdResponse::fromApiResponse(200, [
            'jsonld' => ['@type' => 'WebPage'],
            'hash' => '148a0a50e1812e0f604e17da47f0c4da',
        ]);

        self::assertSame('148a0a50e1812e0f604e17da47f0c4da', $response->hash());
    }

    #[Test]
    public function accessorsReturnNullForMissingFields(): void
    {
        $response = JsonLdResponse::createError('boom');

        self::assertNull($response->crawledAt());
        self::assertNull($response->apiStatus());
        self::assertNull($response->hash());
    }
}
