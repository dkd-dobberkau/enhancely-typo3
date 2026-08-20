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

interface PageAccessCheckerInterface
{
    /**
     * The page record if the current backend user may read that page, null if
     * not. Null is the answer the caller must treat as "do nothing" — the
     * module refreshes a shared cache entry and spends billed API quota, so
     * the gate has to be passed before any of that runs.
     *
     * @return array<string, mixed>|null
     */
    public function readablePageInfo(int $pageUid): ?array;
}
