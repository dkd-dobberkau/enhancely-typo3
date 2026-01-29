<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Client;

/**
 * Response object for JSON-LD API calls.
 *
 * Provides status checking methods and access to the JSON-LD data.
 */
final class JsonLdResponse
{
    private const STATUS_OK = 200;
    private const STATUS_CREATED = 201;
    private const STATUS_ACCEPTED = 202;
    private const STATUS_PRECONDITION_FAILED = 412;

    /**
     * @param int $statusCode HTTP status code
     * @param array<string, mixed>|null $data JSON-LD data from API
     * @param string|null $etag ETag for caching
     * @param string|null $errorMessage Error message if request failed
     */
    private function __construct(
        private readonly int $statusCode,
        private readonly ?array $data = null,
        private readonly ?string $etag = null,
        private readonly ?string $errorMessage = null,
    ) {}

    /**
     * Create response from successful API response.
     *
     * @param int $statusCode HTTP status code
     * @param array<string, mixed> $data Response body
     * @param string|null $etag ETag header value
     */
    public static function fromApiResponse(int $statusCode, array $data, ?string $etag = null): self
    {
        return new self($statusCode, $data, $etag);
    }

    /**
     * Create response for 412 Precondition Failed (ETag matched).
     */
    public static function createNotModified(): self
    {
        return new self(self::STATUS_PRECONDITION_FAILED);
    }

    /**
     * Create response for 201/202 (still processing).
     */
    public static function createProcessing(int $statusCode): self
    {
        return new self($statusCode);
    }

    /**
     * Create error response.
     */
    public static function createError(string $message): self
    {
        return new self(0, null, null, $message);
    }

    /**
     * Check if JSON-LD is ready and available.
     */
    public function ready(): bool
    {
        return $this->statusCode === self::STATUS_OK
            && $this->data !== null
            && isset($this->data['jsonld']);
    }

    /**
     * Check if content hasn't changed (ETag matched).
     */
    public function notModified(): bool
    {
        return $this->statusCode === self::STATUS_PRECONDITION_FAILED;
    }

    /**
     * Check if content is still being processed.
     */
    public function isProcessing(): bool
    {
        return $this->statusCode === self::STATUS_CREATED
            || $this->statusCode === self::STATUS_ACCEPTED;
    }

    /**
     * Get error message if request failed.
     */
    public function error(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get ETag for caching.
     */
    public function etag(): ?string
    {
        return $this->etag;
    }

    /**
     * Get raw JSON-LD data.
     *
     * @return array<string, mixed>|null
     */
    public function jsonld(): ?array
    {
        return $this->data['jsonld'] ?? null;
    }

    /**
     * Get JSON-LD as script tag for HTML injection.
     */
    public function __toString(): string
    {
        $jsonld = $this->jsonld();

        if ($jsonld === null) {
            return '';
        }

        $encoded = json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            return '';
        }

        return '<script type="application/ld+json">' . $encoded . '</script>';
    }
}
