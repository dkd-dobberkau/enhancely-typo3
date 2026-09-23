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

namespace Enhancely\Enhancely\Domain;

/**
 * Reads the Enhancely settings an editor makes on a single page.
 */
final class PageSettings
{
    /**
     * Column on `pages`, declared in ext_tables.sql and made editable by
     * Configuration/TCA/Overrides/pages.php.
     */
    public const HIDE_JSONLD_FIELD = 'tx_enhancely_hide_jsonld';

    /**
     * Whether the editor asked for this page to be rendered without the
     * Enhancely JSON-LD block.
     *
     * A record that does not carry the column at all — an installation that
     * has not run `database:updateschema` since the update — counts as "not
     * suppressed", so the extension keeps its previous behaviour instead of
     * silently dropping the JSON-LD everywhere.
     *
     * @param array<string, mixed>|null $pageRecord Row from `pages`
     */
    public static function jsonLdOutputSuppressed(?array $pageRecord): bool
    {
        return (int)($pageRecord[self::HIDE_JSONLD_FIELD] ?? 0) === 1;
    }
}
