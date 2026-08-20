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
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;

final class RefreshTokenGuard implements RefreshTokenGuardInterface
{
    private const FORM_NAME = 'enhancely_info';
    private const ACTION = 'forceRefresh';

    public function __construct(
        private readonly FormProtectionFactory $formProtectionFactory,
    ) {}

    public function generate(ServerRequestInterface $request): string
    {
        return $this->formProtectionFactory
            ->createFromRequest($request)
            ->generateToken(self::FORM_NAME, self::ACTION);
    }

    public function isRefreshRequested(ServerRequestInterface $request): bool
    {
        // Read from the parsed body only. Accepting the flag from the query
        // string would let a plain link — or an <img src> on any page the
        // editor happens to open — trigger a refresh.
        $parsedBody = (array)$request->getParsedBody();

        if (empty($parsedBody['forceRefresh'])) {
            return false;
        }

        return $this->formProtectionFactory
            ->createFromRequest($request)
            ->validateToken(
                (string)($parsedBody['formToken'] ?? ''),
                self::FORM_NAME,
                self::ACTION
            );
    }
}
