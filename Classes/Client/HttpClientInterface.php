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

namespace Enhancely\Enhancely\Client;

/**
 * Interface for HTTP client implementations.
 */
interface HttpClientInterface
{
    /**
     * Request JSON-LD for a URL.
     *
     * @param string $url The page URL to get JSON-LD for
     * @param string|null $etag Cached ETag for conditional request
     */
    public function postJsonLd(string $url, ?string $etag = null): JsonLdResponse;
}
