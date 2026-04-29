<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Configuration;

use Enhancely\Enhancely\Configuration\ExtensionConfiguration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration as Typo3ExtensionConfiguration;

final class ExtensionConfigurationTest extends TestCase
{
    private function createConfig(array $values): ExtensionConfiguration
    {
        $typo3Config = $this->createMock(Typo3ExtensionConfiguration::class);
        $typo3Config->method('get')->with('enhancely')->willReturn($values);
        return new ExtensionConfiguration($typo3Config);
    }

    #[Test]
    public function getApiBaseUrlReturnsConfiguredHttpsUrl(): void
    {
        $config = $this->createConfig(['apiBaseUrl' => 'https://api.example.com']);
        self::assertSame('https://api.example.com', $config->getApiBaseUrl());
    }

    #[Test]
    public function getApiBaseUrlRejectsHttpAndFallsBackToDefault(): void
    {
        $config = $this->createConfig(['apiBaseUrl' => 'http://insecure.example.com']);
        self::assertSame('https://api.enhancely.ai', $config->getApiBaseUrl());
    }

    #[Test]
    public function getApiBaseUrlRejectsSchemelessAndFallsBackToDefault(): void
    {
        $config = $this->createConfig(['apiBaseUrl' => 'api.example.com']);
        self::assertSame('https://api.enhancely.ai', $config->getApiBaseUrl());
    }

    #[Test]
    public function getApiBaseUrlReturnsDefaultWhenEmpty(): void
    {
        $config = $this->createConfig([]);
        self::assertSame('https://api.enhancely.ai', $config->getApiBaseUrl());
    }

    #[Test]
    public function getApiBaseUrlStripsTrailingSlash(): void
    {
        $config = $this->createConfig(['apiBaseUrl' => 'https://api.example.com/']);
        self::assertSame('https://api.example.com', $config->getApiBaseUrl());
    }

    #[Test]
    public function getCacheLifetimeReturnsConfiguredValue(): void
    {
        $config = $this->createConfig(['cacheLifetime' => 3600]);
        self::assertSame(3600, $config->getCacheLifetime());
    }

    #[Test]
    public function getCacheLifetimeFallsBackToDefaultForZero(): void
    {
        $config = $this->createConfig(['cacheLifetime' => 0]);
        self::assertSame(86400, $config->getCacheLifetime());
    }

    #[Test]
    public function getCacheLifetimeFallsBackToDefaultForNegative(): void
    {
        $config = $this->createConfig(['cacheLifetime' => -5]);
        self::assertSame(86400, $config->getCacheLifetime());
    }
}
