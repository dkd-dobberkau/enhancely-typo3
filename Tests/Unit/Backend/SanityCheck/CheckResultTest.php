<?php

declare(strict_types=1);

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

        self::assertSame('warn', $result->level);
    }
}
