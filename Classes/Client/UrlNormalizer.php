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
 * Normalizes URLs for consistent caching.
 *
 * Removes query strings, fragments, and trailing slashes to ensure
 * the same page doesn't get multiple cache entries.
 */
final class UrlNormalizer
{
    /**
     * Normalize a URL by removing query string, fragment, and trailing slash.
     *
     * @param string $url The URL to normalize
     * @return string The normalized URL
     */
    public static function normalize(string $url): string
    {
        $parsed = parse_url($url);

        // Return original if parsing failed or essential parts are missing
        if ($parsed === false || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return $url;
        }

        $scheme = $parsed['scheme'];
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path = $parsed['path'] ?? '/';

        // Remove trailing slash, but keep it for root path
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Rebuild URL without query and fragment
        return sprintf('%s://%s%s%s', $scheme, $host, $port, $path);
    }
}
