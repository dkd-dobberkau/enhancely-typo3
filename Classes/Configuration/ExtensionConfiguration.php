<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as Typo3ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;

final class ExtensionConfiguration implements SingletonInterface
{
    private const DEFAULT_API_BASE_URL = 'https://api.enhancely.ai';
    private const DEFAULT_CACHE_LIFETIME = 86400;

    private array $configuration;

    public function __construct(
        private readonly Typo3ExtensionConfiguration $extensionConfiguration
    ) {
        $this->configuration = $this->extensionConfiguration->get('enhancely');
    }

    public function getApiKey(): string
    {
        return trim((string)($this->configuration['apiKey'] ?? ''));
    }

    public function isEnabled(): bool
    {
        return (bool)($this->configuration['enabled'] ?? true);
    }

    public function getExcludedPageTypes(): array
    {
        $types = trim((string)($this->configuration['excludedPageTypes'] ?? ''));
        if ($types === '') {
            return [];
        }
        return array_map('intval', explode(',', $types));
    }

    public function getCacheLifetime(): int
    {
        $lifetime = (int)($this->configuration['cacheLifetime'] ?? self::DEFAULT_CACHE_LIFETIME);
        // Reject non-positive values; the API key is sent on every request,
        // a 0/negative TTL would cause cache thrashing.
        return $lifetime > 0 ? $lifetime : self::DEFAULT_CACHE_LIFETIME;
    }

    public function getApiBaseUrl(): string
    {
        // Check new config key first, fall back to deprecated 'apiEndpoint' for BC
        // Use ?: instead of ?? because empty string should also trigger fallback
        $baseUrl = trim((string)($this->configuration['apiBaseUrl'] ?? ''))
            ?: trim((string)($this->configuration['apiEndpoint'] ?? ''));

        if ($baseUrl === '') {
            return self::DEFAULT_API_BASE_URL;
        }

        // Enforce HTTPS: the API key is sent as a Bearer token. A non-https
        // base URL would expose the token over the wire.
        if (!str_starts_with(strtolower($baseUrl), 'https://')) {
            return self::DEFAULT_API_BASE_URL;
        }

        return rtrim($baseUrl, '/');
    }
}
