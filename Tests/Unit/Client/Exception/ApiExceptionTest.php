<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Client\Exception;

use Enhancely\Exception\ApiException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiExceptionTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $problemDetails = [
            'type' => 'https://api.enhancely.ai/problems/rate-limit',
            'title' => 'Rate Limit Exceeded',
            'status' => 429,
        ];

        $exception = new ApiException('Rate limit exceeded', 429, $problemDetails);

        self::assertSame('Rate limit exceeded', $exception->getMessage());
        self::assertSame(429, $exception->getStatusCode());
        self::assertSame(429, $exception->getCode());
        self::assertSame($problemDetails, $exception->getProblemDetails());
    }

    #[Test]
    public function constructorWithPreviousException(): void
    {
        $previous = new \RuntimeException('Original error');
        $exception = new ApiException('Wrapped error', 500, null, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function defaultValues(): void
    {
        $exception = new ApiException('Simple error');

        self::assertSame(0, $exception->getStatusCode());
        self::assertNull($exception->getProblemDetails());
    }
}
