<?php

declare(strict_types=1);

use Enhancely\Enhancely\Backend\InfoModule\EnhancelyStatusController;

return [
    'web_info_enhancely' => [
        'parent' => 'web_info',
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
