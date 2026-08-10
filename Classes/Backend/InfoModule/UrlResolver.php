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

use TYPO3\CMS\Core\Site\SiteFinder;

final class UrlResolver implements UrlResolverInterface
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * @throws \RuntimeException If page has no site or URL cannot be built.
     */
    public function resolve(int $pageUid, int $languageId): string
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                sprintf('Page %d has no site configuration: %s', $pageUid, $e->getMessage()),
                0,
                $e
            );
        }

        try {
            $uri = $site->getRouter()->generateUri($pageUid, ['_language' => $languageId]);
            return (string)$uri;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                sprintf('Cannot build URL for page %d (lang %d): %s', $pageUid, $languageId, $e->getMessage()),
                0,
                $e
            );
        }
    }
}
