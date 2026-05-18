<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Backend\InfoModule;

use Enhancely\Enhancely\Backend\InfoModule\EnhancelyStatusController;
use Enhancely\Enhancely\Backend\InfoModule\ViewState;
use Enhancely\Enhancely\Backend\SanityCheck\SanityChecker;
use Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class EnhancelyStatusControllerTest extends TestCase
{
    private function controllerWith(
        ExtensionConfigurationInterface $config,
        ?FrontendInterface $cache = null,
        ?\Enhancely\Enhancely\Backend\InfoModule\UrlResolverInterface $resolver = null,
    ): EnhancelyStatusController {
        if ($resolver === null) {
            $resolver = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\UrlResolverInterface::class);
            $resolver->method('resolve')->willReturn('https://example.com/');
        }
        return new EnhancelyStatusController(
            $config,
            $cache ?? $this->createMock(FrontendInterface::class),
            new SanityChecker(),
            $resolver,
        );
    }

    private function configMock(string $apiKey = 'k', bool $enabled = true, array $excluded = []): ExtensionConfigurationInterface
    {
        $m = $this->createMock(ExtensionConfigurationInterface::class);
        $m->method('getApiKey')->willReturn($apiKey);
        $m->method('isEnabled')->willReturn($enabled);
        $m->method('getExcludedPageTypes')->willReturn($excluded);
        return $m;
    }

    #[Test]
    public function returnsNotConfiguredWhenApiKeyEmpty(): void
    {
        $controller = $this->controllerWith($this->configMock(apiKey: ''));
        $state = $controller->buildViewState(pageUid: 1, languageId: 0, doktype: 1, forceRefresh: false);

        self::assertSame(ViewState::BANNER_NOT_CONFIGURED, $state->banner);
    }

    #[Test]
    public function returnsDisabledWhenExtensionDisabled(): void
    {
        $controller = $this->controllerWith($this->configMock(enabled: false));
        $state = $controller->buildViewState(1, 0, 1, false);

        self::assertSame(ViewState::BANNER_DISABLED, $state->banner);
    }

    #[Test]
    public function returnsSkippedWhenDoktypeExcluded(): void
    {
        $controller = $this->controllerWith($this->configMock(excluded: [404]));
        $state = $controller->buildViewState(1, 0, 404, false);

        self::assertSame('skipped', $state->statusBadge);
    }

    #[Test]
    public function returnsSiteErrorWhenUrlResolverThrows(): void
    {
        $resolver = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\UrlResolverInterface::class);
        $resolver->method('resolve')->willThrowException(new \RuntimeException('no site for page 42'));

        $controller = new EnhancelyStatusController(
            $this->configMock(),
            $this->createMock(FrontendInterface::class),
            new SanityChecker(),
            $resolver,
        );

        $state = $controller->buildViewState(42, 0, 1, false);

        self::assertSame(ViewState::BANNER_SITE_ERROR, $state->banner);
        self::assertStringContainsString('no site for page 42', $state->bannerDetail);
    }
}
