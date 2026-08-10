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

namespace Enhancely\Enhancely\Client\Exception;

/**
 * Exception thrown when the Enhancely API returns an error.
 *
 * Supports RFC 7807 Problem Details format.
 */
final class ApiException extends \RuntimeException
{
    /**
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array<string, mixed>|null $problemDetails RFC 7807 problem details
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly ?array $problemDetails = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProblemDetails(): ?array
    {
        return $this->problemDetails;
    }
}
