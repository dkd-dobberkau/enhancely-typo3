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

final class SiteTitleProvider implements SiteTitleProviderInterface
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    public function websiteTitle(int $pageUid): string
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            return (string)($site->getConfiguration()['websiteTitle'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }
}
