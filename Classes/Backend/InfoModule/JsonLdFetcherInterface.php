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

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Client\JsonLdResponse;

interface JsonLdFetcherInterface
{
    /**
     * Fetch JSON-LD for a URL, bypassing the local TYPO3 cache.
     *
     * Callers that want a fresh result must clear their own cache entry
     * beforehand — the Enhancely API has no cache-bypass parameter.
     */
    public function fetch(string $url): JsonLdResponse;
}
