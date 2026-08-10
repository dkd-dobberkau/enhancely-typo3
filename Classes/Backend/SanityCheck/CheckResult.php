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

namespace Enhancely\Enhancely\Backend\SanityCheck;

final class CheckResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $level,
        public readonly string $message,
    ) {}

    public static function pass(string $id, string $message): self
    {
        return new self($id, 'pass', $message);
    }

    public static function warn(string $id, string $message): self
    {
        return new self($id, 'warn', $message);
    }
}
