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

namespace Enhancely\Tests\Unit\Backend\InfoModule;

use Enhancely\Enhancely\Backend\InfoModule\EnhancelyStatusController;
use Enhancely\Enhancely\Backend\InfoModule\ViewState;
use Enhancely\Enhancely\Backend\SanityCheck\SanityChecker;
use Enhancely\Enhancely\Cache\JsonLdCache;
use Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class EnhancelyStatusControllerTest extends TestCase
{
    private function controllerWith(
        ExtensionConfigurationInterface $config,
        ?FrontendInterface $cache = null,
        ?\Enhancely\Enhancely\Backend\InfoModule\UrlResolverInterface $resolver = null,
        ?\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcherInterface $fetcher = null,
        ?ModuleTemplateFactory $moduleTemplateFactory = null,
        ?\Enhancely\Enhancely\Backend\InfoModule\SiteTitleProviderInterface $siteTitleProvider = null,
    ): EnhancelyStatusController {
        if ($resolver === null) {
            $resolver = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\UrlResolverInterface::class);
            $resolver->method('resolve')->willReturn('https://example.com/');
        }
        if ($fetcher === null) {
            $fetcher = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcherInterface::class);
        }
        return new EnhancelyStatusController(
            $config,
            new JsonLdCache($cache ?? $this->createMock(FrontendInterface::class)),
            new SanityChecker(),
            $resolver,
            $siteTitleProvider ?? $this->siteTitleProviderMock(),
            $fetcher,
            $moduleTemplateFactory ?? $this->createMock(ModuleTemplateFactory::class),
        );
    }

    private function urlResolverMock(string $url = 'https://example.com/'): \Enhancely\Enhancely\Backend\InfoModule\UrlResolverInterface
    {
        $m = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\UrlResolverInterface::class);
        $m->method('resolve')->willReturn($url);
        return $m;
    }

    private function siteTitleProviderMock(string $title = 'Example Site'): \Enhancely\Enhancely\Backend\InfoModule\SiteTitleProviderInterface
    {
        $m = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\SiteTitleProviderInterface::class);
        $m->method('websiteTitle')->willReturn($title);
        return $m;
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

        $controller = $this->controllerWith($this->configMock(), null, $resolver);

        $state = $controller->buildViewState(42, 0, 1, false);

        self::assertSame(ViewState::BANNER_SITE_ERROR, $state->banner);
        self::assertStringEndsWith('banner.site_error.detail', $state->bannerDetailKey);
        self::assertSame(['no site for page 42'], $state->bannerDetailArguments);
    }

    /**
     * Backend messages are carried as LLL keys, never as literal English, so
     * they can be translated like every other label in the module.
     */
    #[Test]
    public function bannerDetailsAreLanguageKeys(): void
    {
        $states = [
            $this->controllerWith($this->configMock(apiKey: ''))->buildViewState(1, 0, 1, false),
            $this->controllerWith($this->configMock(enabled: false))->buildViewState(1, 0, 1, false),
            $this->controllerWith($this->configMock(excluded: [404]))->buildViewState(1, 0, 404, false),
        ];

        foreach ($states as $state) {
            self::assertStringStartsWith('LLL:EXT:enhancely/', $state->bannerDetailKey);
        }
    }

    #[Test]
    public function rendersFromCacheWhenMetaPresent(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('get')->willReturn([
            'etag' => 'etag-abc',
            'jsonld' => '<script>...</script>',
            'meta' => [
                'crawled_at' => '2026-05-18T12:32:15Z',
                'status' => 'ready',
                'hash' => 'h1',
                'graph' => ['@graph' => [['@type' => 'WebPage', 'name' => 'X']]],
                'cached_at' => time() - 600,
            ],
        ]);

        $fetcher = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcherInterface::class);
        $fetcher->expects(self::never())->method('fetch');

        $controller = $this->controllerWith($this->configMock(), $cache, $this->urlResolverMock(), $fetcher);

        $state = $controller->buildViewState(1, 0, 1, false);

        self::assertSame('ready', $state->statusBadge);
        self::assertSame('etag-abc', $state->etag);
        self::assertSame('h1', $state->hash);
        self::assertStringContainsString('cache', $state->source);
    }

    #[Test]
    public function liveFetchOnCacheMissPopulatesCache(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('get')->willReturn(false);

        $response = \Enhancely\Enhancely\Client\JsonLdResponse::fromApiResponse(200, [
            'jsonld' => ['@graph' => [['@type' => 'WebPage']]],
            'crawled_at' => '2026-05-18T12:32:15Z',
            'status' => 'ready',
            'hash' => 'h2',
        ], 'etag-new');

        $fetcher = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcherInterface::class);
        $fetcher->expects(self::once())
            ->method('fetch')
            ->with('https://example.com/')
            ->willReturn($response);

        // Written under the shared identifier scheme, so the frontend middleware
        // reads the same entry on the next request.
        $cache->expects(self::once())
            ->method('set')
            ->with(self::stringStartsWith('enhancely_'));

        $controller = $this->controllerWith($this->configMock(), $cache, $this->urlResolverMock(), $fetcher);

        $state = $controller->buildViewState(1, 0, 1, false);

        self::assertSame('ready', $state->statusBadge);
        self::assertSame('h2', $state->hash);
        self::assertStringContainsString('live', $state->source);
    }

    #[Test]
    public function forceRefreshSkipsCacheAndFetches(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        // Cache has data but forceRefresh should bypass it.
        $cache->expects(self::once())->method('remove')->with(self::isType('string'));

        $response = \Enhancely\Enhancely\Client\JsonLdResponse::fromApiResponse(200, [
            'jsonld' => ['@graph' => []],
            'status' => 'ready',
        ], 'etag-fresh');

        $fetcher = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcherInterface::class);
        $fetcher->expects(self::once())->method('fetch')->with(self::anything())->willReturn($response);

        $controller = $this->controllerWith($this->configMock(), $cache, $this->urlResolverMock(), $fetcher);

        $controller->buildViewState(1, 0, 1, forceRefresh: true);
    }

    #[Test]
    public function legacyCacheEntryWithoutMetaTriggersLiveFetch(): void
    {
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('get')->willReturn([
            'etag' => 'etag-old',
            'jsonld' => '<script>...</script>',
            // no 'meta' key — written by < 1.3.0
        ]);

        $response = \Enhancely\Enhancely\Client\JsonLdResponse::fromApiResponse(200, [
            'jsonld' => ['@graph' => []],
            'status' => 'ready',
        ], 'etag-new');

        $fetcher = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcherInterface::class);
        $fetcher->expects(self::once())->method('fetch')->willReturn($response);

        $controller = $this->controllerWith($this->configMock(), $cache, $this->urlResolverMock(), $fetcher);

        $state = $controller->buildViewState(1, 0, 1, false);

        self::assertStringContainsString('live', $state->source);
    }
}
