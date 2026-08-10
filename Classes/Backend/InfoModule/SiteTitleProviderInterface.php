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

interface SiteTitleProviderInterface
{
    /**
     * The websiteTitle configured for the site a page belongs to.
     *
     * Used by the sanity check that compares the API's WebSite name against
     * what TYPO3 itself would render. Returns '' when unknown, which the
     * check treats as "cannot compare" rather than "mismatch".
     */
    public function websiteTitle(int $pageUid): string;
}
