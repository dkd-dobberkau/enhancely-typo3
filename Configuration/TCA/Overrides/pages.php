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

use Enhancely\Enhancely\Domain\PageSettings;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

$enhancelyLabels = 'LLL:EXT:enhancely/Resources/Private/Language/locallang.xlf:';

ExtensionManagementUtility::addTCAcolumns('pages', [
    PageSettings::HIDE_JSONLD_FIELD => [
        'exclude' => true,
        'label' => $enhancelyLabels . 'pages.hide_jsonld',
        'description' => $enhancelyLabels . 'pages.hide_jsonld.description',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
        ],
    ],
]);

// Anchored on `keywords`, which puts the toggle into the Metadata tab next to
// the other data written for search engines.
//
// The anchor has to be a field the Core actually *displays*: an anchor that
// appears in no showitem and no palette silently sends the field to the end of
// the form — the Extended tab — instead. `pages.description` looks like the
// obvious neighbour and is a Core column, but nothing in Core puts it on the
// form; EXT:seo does, and that is not a dependency here. `keywords` sits in the
// `metatags` palette shipped by the Core itself, so the placement holds with or
// without EXT:seo installed. Covered by TcaOverridePagesTest, which the CI
// matrix runs against both v13 and v14.
ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    PageSettings::HIDE_JSONLD_FIELD,
    '',
    'after:keywords'
);
