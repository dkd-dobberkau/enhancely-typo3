<?php

declare(strict_types=1);

use Enhancely\Enhancely\Backend\InfoModule\EnhancelyStatusController;
use TYPO3\CMS\Core\Information\Typo3Version;

// TYPO3 v14 renamed the legacy `web_info` module to `content_status`. The
// `web_info` route alias still works for direct URLs, but the alias does NOT
// resolve when used as a `parent` — sub-modules registered with the legacy
// parent name don't show up in the v14 sidebar. Use the version-correct key.
$parentModule = (new Typo3Version())->getMajorVersion() >= 14
    ? 'content_status'
    : 'web_info';

return [
    'web_info_enhancely' => [
        'parent' => $parentModule,
        'access' => 'user',
        'path' => '/module/web/info/enhancely',
        'iconIdentifier' => 'module-enhancely',
        'labels' => 'LLL:EXT:enhancely/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'Enhancely',
        'routes' => [
            '_default' => [
                'target' => EnhancelyStatusController::class . '::__invoke',
            ],
        ],
    ],
];
