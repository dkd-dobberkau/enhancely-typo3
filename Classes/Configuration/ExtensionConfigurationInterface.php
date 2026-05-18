<?php

declare(strict_types=1);

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
}
