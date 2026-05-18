<?php

declare(strict_types=1);

/**
 * Minimal stubs for TYPO3 Backend classes not available in the unit test
 * vendor (only typo3/cms-core is required-dev, not typo3/cms-backend).
 */

namespace TYPO3\CMS\Backend\Template {
    if (!class_exists(ModuleTemplateFactory::class)) {
        class ModuleTemplateFactory
        {
            public function create(\Psr\Http\Message\ServerRequestInterface $request): ModuleTemplate
            {
                return new ModuleTemplate();
            }
        }
    }

    if (!class_exists(ModuleTemplate::class)) {
        class ModuleTemplate
        {
            public function setContent(string $content): void {}

            public function renderContent(): string
            {
                return '';
            }
        }
    }
}

namespace TYPO3\CMS\Backend\Utility {
    if (!class_exists(BackendUtility::class)) {
        class BackendUtility
        {
            public static function getRecord(
                string $table,
                int $uid,
                string $fields = '*',
                string $where = '',
                bool $useDeleteClause = true
            ): ?array {
                return null;
            }
        }
    }
}
