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

namespace Enhancely\Tests\Unit\Backend\SanityCheck;

use Enhancely\Enhancely\Backend\SanityCheck\CheckResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckResultTest extends TestCase
{
    #[Test]
    public function passConstructorYieldsPassLevel(): void
    {
        $result = CheckResult::pass('breadcrumb_absolute', 'All items absolute');

        self::assertSame('breadcrumb_absolute', $result->id);
        self::assertSame('pass', $result->level);
        self::assertSame('All items absolute', $result->message);
    }

    #[Test]
    public function warnConstructorYieldsWarnLevel(): void
    {
        $result = CheckResult::warn('title_mismatch', 'Stale title');

        self::assertSame('title_mismatch', $result->id);
        self::assertSame('warn', $result->level);
        self::assertSame('Stale title', $result->message);
    }
}
