<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Client;

use Enhancely\Enhancely\Client\UrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlNormalizerTest extends TestCase
{
    #[Test]
    #[DataProvider('urlProvider')]
    public function normalize(string $input, string $expected): void
    {
        self::assertSame($expected, UrlNormalizer::normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function urlProvider(): array
    {
        return [
            'simple url unchanged' => [
                'https://example.com/page',
                'https://example.com/page',
            ],
            'removes trailing slash' => [
                'https://example.com/page/',
                'https://example.com/page',
            ],
            'keeps root slash' => [
                'https://example.com/',
                'https://example.com/',
            ],
            'removes query string' => [
                'https://example.com/page?utm_source=google&utm_medium=cpc',
                'https://example.com/page',
            ],
            'removes fragment' => [
                'https://example.com/page#section',
                'https://example.com/page',
            ],
            'removes query and fragment' => [
                'https://example.com/page?foo=bar#section',
                'https://example.com/page',
            ],
            'removes trailing slash and query' => [
                'https://example.com/page/?foo=bar',
                'https://example.com/page',
            ],
            'preserves port' => [
                'https://example.com:8080/page',
                'https://example.com:8080/page',
            ],
            'preserves http scheme' => [
                'http://example.com/page',
                'http://example.com/page',
            ],
            'handles deep paths' => [
                'https://example.com/a/b/c/page/',
                'https://example.com/a/b/c/page',
            ],
            'handles root with query' => [
                'https://example.com/?lang=de',
                'https://example.com/',
            ],
        ];
    }

    #[Test]
    public function normalizeReturnsOriginalOnInvalidUrl(): void
    {
        $invalid = '://not-a-valid-url';
        self::assertSame($invalid, UrlNormalizer::normalize($invalid));
    }
}
