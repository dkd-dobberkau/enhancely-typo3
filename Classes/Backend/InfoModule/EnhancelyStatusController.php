<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Backend\SanityCheck\SanityChecker;
use Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class EnhancelyStatusController
{
    public function __construct(
        private readonly ExtensionConfigurationInterface $config,
        private readonly FrontendInterface $cache,
        private readonly SanityChecker $sanityChecker,
        private readonly UrlResolverInterface $urlResolver,
    ) {}

    public function buildViewState(
        int $pageUid,
        int $languageId,
        int $doktype,
        bool $forceRefresh,
    ): ViewState {
        if ($this->config->getApiKey() === '') {
            return new ViewState(
                banner: ViewState::BANNER_NOT_CONFIGURED,
                bannerDetail: 'API key not configured.'
            );
        }

        if (!$this->config->isEnabled()) {
            return new ViewState(
                banner: ViewState::BANNER_DISABLED,
                bannerDetail: 'Extension is disabled in Extension Configuration.'
            );
        }

        if (in_array($doktype, $this->config->getExcludedPageTypes(), true)) {
            return new ViewState(
                statusBadge: 'skipped',
                bannerDetail: sprintf('Doktype %d is excluded from Enhancely.', $doktype)
            );
        }

        try {
            $url = $this->urlResolver->resolve($pageUid, $languageId);
        } catch (\RuntimeException $e) {
            return new ViewState(
                banner: ViewState::BANNER_SITE_ERROR,
                bannerDetail: $e->getMessage(),
            );
        }

        // Cache + live fetch — Task 11.
        return new ViewState(statusBadge: 'unknown', url: $url);
    }
}
