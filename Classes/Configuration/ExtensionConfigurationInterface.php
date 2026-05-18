<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Configuration;

interface ExtensionConfigurationInterface
{
    public function getApiKey(): string;

    public function isEnabled(): bool;

    public function getExcludedPageTypes(): array;

    public function getCacheLifetime(): int;

    public function getApiBaseUrl(): string;
}
