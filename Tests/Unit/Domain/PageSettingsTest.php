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

namespace Enhancely\Tests\Unit\Domain;

use Enhancely\Enhancely\Domain\PageSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The per-page opt-out is read from the same page record in two places — the
 * frontend middleware and the info module — so the rule that turns a column
 * value into a decision lives in one place.
 */
final class PageSettingsTest extends TestCase
{
    #[Test]
    public function checkedFlagSuppressesTheOutput(): void
    {
        self::assertTrue(PageSettings::jsonLdOutputSuppressed([
            'uid' => 1,
            'tx_enhancely_hide_jsonld' => 1,
        ]));
    }

    #[Test]
    public function unsetFlagLeavesTheOutputInPlace(): void
    {
        self::assertFalse(PageSettings::jsonLdOutputSuppressed([
            'uid' => 1,
            'tx_enhancely_hide_jsonld' => 0,
        ]));
    }

    /**
     * An update that skipped `database:updateschema` leaves the column missing
     * from every page record. That must read as "not suppressed" so the
     * extension keeps behaving exactly as it did before the update.
     */
    #[Test]
    public function recordWithoutTheColumnLeavesTheOutputInPlace(): void
    {
        self::assertFalse(PageSettings::jsonLdOutputSuppressed(['uid' => 1, 'doktype' => 1]));
    }

    #[Test]
    public function missingRecordLeavesTheOutputInPlace(): void
    {
        self::assertFalse(PageSettings::jsonLdOutputSuppressed(null));
    }
}
