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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * Wraps the TYPO3 page-read permission check so the controller can be tested
 * without a backend user in $GLOBALS.
 */
final class PageAccessChecker implements PageAccessCheckerInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function readablePageInfo(int $pageUid): ?array
    {
        if ($pageUid <= 0) {
            return null;
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return null;
        }

        // getPagePermsClause() resolves to "1=1" for admins and to the user's
        // actual SHOW permissions otherwise. Passing a literal "1=1" instead
        // would leave only readPageAccess()'s web-mount check in place and skip
        // the per-page permission bits entirely.
        $pageInfo = BackendUtility::readPageAccess(
            $pageUid,
            $backendUser->getPagePermsClause(Permission::PAGE_SHOW)
        );

        return is_array($pageInfo) ? $pageInfo : null;
    }
}
