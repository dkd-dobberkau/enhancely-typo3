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

use Psr\Http\Message\ServerRequestInterface;

/**
 * CSRF protection for the "refresh" action.
 *
 * TYPO3 does not verify request tokens for backend routes on its own — see the
 * @todo in core's RequestTokenMiddleware — so the module carries its own token,
 * the way core backend modules do via FormProtection.
 */
interface RefreshTokenGuardInterface
{
    /**
     * Token to embed in the refresh form.
     */
    public function generate(ServerRequestInterface $request): string;

    /**
     * Whether this request is a refresh the user actually asked for.
     * Must be false for anything that is not a token-carrying POST.
     */
    public function isRefreshRequested(ServerRequestInterface $request): bool;
}
