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

namespace Enhancely\Tests\Unit\Configuration;

use Enhancely\Enhancely\Domain\PageSettings;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The opt-out is only usable if editors actually find the checkbox, and where
 * it lands depends on the Core's own `pages` TCA — which differs between the
 * TYPO3 versions this extension supports. So the override is applied to the
 * real Core TCA of the installed version instead of a hand-written stub, and
 * the CI matrix runs this against both v13 and v14.
 */
final class TcaOverridePagesTest extends TestCase
{
    private const DEFAULT_DOKTYPE = '1';

    /** @var array<string, mixed>|null */
    private ?array $tcaBackup = null;

    /** @var array<string, mixed>|null */
    private ?array $confVarsBackup = null;

    protected function setUp(): void
    {
        $this->tcaBackup = $GLOBALS['TCA'] ?? null;
        $this->confVarsBackup = $GLOBALS['TYPO3_CONF_VARS'] ?? null;

        if (!defined('TYPO3')) {
            define('TYPO3', true);
        }

        // The Core's pages TCA picks a label based on this setting. Without a
        // real bootstrap it is unset, and reading it raises a warning that
        // phpunit.xml.dist turns into a failure.
        $GLOBALS['TYPO3_CONF_VARS']['FE']['hidePagesIfNotTranslatedByDefault'] ??= false;

        $GLOBALS['TCA']['pages'] = require self::corePagesTcaPath();
        require self::extensionPath() . '/Configuration/TCA/Overrides/pages.php';
    }

    protected function tearDown(): void
    {
        if ($this->tcaBackup === null) {
            unset($GLOBALS['TCA']);
        } else {
            $GLOBALS['TCA'] = $this->tcaBackup;
        }

        if ($this->confVarsBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS'] = $this->confVarsBackup;
        }
    }

    private static function extensionPath(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * Located instead of hardcoded: which Core package ships the `pages` TCA
     * has changed between TYPO3 versions, and this test runs against every
     * version the CI matrix pins.
     */
    private static function corePagesTcaPath(): string
    {
        $matches = glob(self::extensionPath() . '/vendor/typo3/*/Configuration/TCA/pages.php') ?: [];

        self::assertCount(
            1,
            $matches,
            'Expected exactly one Core pages TCA file, found: ' . (implode(', ', $matches) ?: 'none')
        );

        return $matches[0];
    }

    #[Test]
    public function theOptOutIsAnEditableCheckboxDefaultingToOff(): void
    {
        $column = $GLOBALS['TCA']['pages']['columns'][PageSettings::HIDE_JSONLD_FIELD] ?? null;

        self::assertIsArray($column, 'The opt-out column is missing from the pages TCA.');
        self::assertSame('check', $column['config']['type']);
        self::assertSame(0, $column['config']['default'], 'A new page must keep outputting JSON-LD.');
        self::assertStringStartsWith('LLL:EXT:enhancely/', $column['label']);
    }

    /**
     * The README sends editors to the Metadata tab of the page properties. If
     * the anchor field this override attaches to is not displayed by the
     * installed Core, the field silently lands at the end of the form instead —
     * a different tab than documented, and easy to miss.
     */
    #[Test]
    public function theCheckboxAppearsInTheMetadataTabOfAStandardPage(): void
    {
        $tab = self::tabContainingField(PageSettings::HIDE_JSONLD_FIELD);

        self::assertNotNull($tab, 'The checkbox is in no tab of a standard page at all.');
        self::assertMatchesRegularExpression(
            '/metadata/i',
            $tab,
            sprintf('The checkbox landed in the "%s" tab instead of Metadata.', $tab)
        );
    }

    /**
     * A column declared in the TCA but missing from ext_tables.sql makes the
     * backend form fail on the page it is supposed to appear on. Written as a
     * diff against the pristine Core TCA, so a second field added later is
     * covered without touching this test.
     */
    #[Test]
    public function everyColumnTheOverrideAddsIsAlsoDeclaredInExtTablesSql(): void
    {
        $pristine = require self::corePagesTcaPath();
        $addedColumns = array_diff(
            array_keys($GLOBALS['TCA']['pages']['columns']),
            array_keys($pristine['columns'])
        );

        self::assertNotEmpty($addedColumns, 'The override adds no columns at all.');

        $sql = (string)file_get_contents(self::extensionPath() . '/ext_tables.sql');

        foreach ($addedColumns as $column) {
            self::assertMatchesRegularExpression(
                '/^\s*' . preg_quote($column, '/') . '\s+\S+/m',
                $sql,
                sprintf('Column "%s" is in the TCA but not declared in ext_tables.sql.', $column)
            );
        }
    }

    /**
     * Walks the showitem of a standard page in order, expanding palettes, and
     * reports the tab the given field ends up under. Mirrors how the backend
     * form builds the tabs, so the assertion is about what an editor sees
     * rather than about the mechanics of the insertion.
     */
    private static function tabContainingField(string $field): ?string
    {
        $pages = $GLOBALS['TCA']['pages'];
        $showitem = (string)($pages['types'][self::DEFAULT_DOKTYPE]['showitem'] ?? '');
        $currentTab = null;

        foreach (explode(',', $showitem) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }

            $parts = explode(';', $item);
            $name = trim($parts[0]);

            if ($name === '--div--') {
                $currentTab = trim($parts[1] ?? '');
                continue;
            }

            if ($name === '--palette--') {
                $paletteName = trim($parts[2] ?? '');
                $palette = (string)($pages['palettes'][$paletteName]['showitem'] ?? '');
                foreach (explode(',', $palette) as $paletteItem) {
                    if (trim(explode(';', trim($paletteItem))[0]) === $field) {
                        return $currentTab;
                    }
                }
                continue;
            }

            if ($name === $field) {
                return $currentTab;
            }
        }

        return null;
    }
}
