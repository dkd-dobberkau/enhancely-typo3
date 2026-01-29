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
}
