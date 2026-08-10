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

namespace Enhancely\Enhancely\Configuration;

/**
 * Public contract of {@see ExtensionConfiguration}.
 *
 * Exists primarily to support mocking in unit tests — the concrete class
 * is `final`, which PHPUnit cannot mock without disabling final-class
 * support globally.
 */
interface ExtensionConfigurationInterface
{
    public function getApiKey(): string;

    public function isEnabled(): bool;

    public function getExcludedPageTypes(): array;

    public function getCacheLifetime(): int;

    public function getApiBaseUrl(): string;

    /**
     * Per-request timeout in seconds, or null to use TYPO3's global HTTP timeout.
     */
    public function getTimeout(): ?int;
}
