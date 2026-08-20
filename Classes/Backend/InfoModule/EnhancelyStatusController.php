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

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Backend\SanityCheck\SanityChecker;
use Enhancely\Enhancely\Cache\JsonLdCache;
use Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

final class EnhancelyStatusController
{
    private const LLL = 'LLL:EXT:enhancely/Resources/Private/Language/locallang_mod.xlf:';

    public function __construct(
        private readonly ExtensionConfigurationInterface $config,
        private readonly JsonLdCache $cache,
        private readonly SanityChecker $sanityChecker,
        private readonly UrlResolverInterface $urlResolver,
        private readonly SiteTitleProviderInterface $siteTitleProvider,
        private readonly JsonLdFetcherInterface $fetcher,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly PageAccessCheckerInterface $pageAccessChecker,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams() + (array)$request->getParsedBody();
        $pageUid = (int)($params['id'] ?? 0);
        $languageId = (int)($params['language'] ?? 0);
        $forceRefresh = !empty($params['forceRefresh']);

        // The module is registered with `access => 'user'`, so anyone logged in
        // can reach this point with any page UID. Everything below changes
        // state a page-read gate is supposed to protect: it purges the cache
        // entry the frontend middleware reads and spends billed API quota. So
        // the gate runs first, and nothing happens without it.
        $pageInfo = $this->pageAccessChecker->readablePageInfo($pageUid);

        if ($pageUid > 0 && $pageInfo === null) {
            $state = new ViewState(
                banner: ViewState::BANNER_ACCESS_DENIED,
                bannerDetailKey: self::LLL . 'banner.access_denied.detail',
                bannerDetailArguments: [(string)$pageUid],
            );
        } else {
            // readPageAccess() selects the full page row, so the doktype comes
            // out of the record we just authorized instead of a second query.
            $doktype = (int)($pageInfo['doktype'] ?? 0);
            $state = $this->buildViewState($pageUid, $languageId, $doktype, $forceRefresh);
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assign('state', $state);
        $moduleTemplate->assign('pageUid', $pageUid);

        $moduleTemplate->setTitle('Enhancely JSON-LD', $pageInfo['title'] ?? '');
        if ($pageInfo !== null) {
            $moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageInfo);
        }
        $moduleTemplate->makeDocHeaderModuleMenu(['id' => $pageUid]);

        return $moduleTemplate->renderResponse('Backend/InfoModule/Show');
    }

    public function buildViewState(
        int $pageUid,
        int $languageId,
        int $doktype,
        bool $forceRefresh,
    ): ViewState {
        if ($this->config->getApiKey() === '') {
            return new ViewState(
                banner: ViewState::BANNER_NOT_CONFIGURED,
                bannerDetailKey: self::LLL . 'banner.not_configured.detail'
            );
        }

        if (!$this->config->isEnabled()) {
            return new ViewState(
                banner: ViewState::BANNER_DISABLED,
                bannerDetailKey: self::LLL . 'banner.disabled.detail'
            );
        }

        if (in_array($doktype, $this->config->getExcludedPageTypes(), true)) {
            return new ViewState(
                statusBadge: 'skipped',
                bannerDetailKey: self::LLL . 'banner.skipped.detail',
                bannerDetailArguments: [(string)$doktype]
            );
        }

        try {
            $url = $this->urlResolver->resolve($pageUid, $languageId);
        } catch (\RuntimeException $e) {
            return new ViewState(
                banner: ViewState::BANNER_SITE_ERROR,
                bannerDetailKey: self::LLL . 'banner.site_error.detail',
                bannerDetailArguments: [$e->getMessage()],
            );
        }

        $expectedTitle = $this->siteTitleProvider->websiteTitle($pageUid);

        if ($forceRefresh) {
            $this->cache->remove($url);
        }

        $cached = $this->cache->get($url);

        if ($cached !== null && isset($cached['meta']) && !$forceRefresh) {
            return $this->stateFromCachedMeta($url, $cached, $expectedTitle);
        }

        $response = $this->fetcher->fetch($url);

        if ($response->ready()) {
            // Same cache entry the frontend middleware reads, so a refresh here
            // also serves the next frontend request.
            $this->cache->write($url, $response, $this->config->getCacheLifetime());
            return $this->stateFromLiveResponse($url, $response, $expectedTitle);
        }

        if ($response->isProcessing()) {
            return new ViewState(statusBadge: 'processing', url: $url, source: 'live (processing)');
        }

        return new ViewState(
            statusBadge: 'error',
            url: $url,
            errorMessage: $response->error() ?? 'Unknown error',
            source: 'live'
        );
    }

    private function stateFromCachedMeta(string $url, array $cached, string $expectedTitle): ViewState
    {
        $meta = $cached['meta'];
        $graph = (array)($meta['graph'] ?? []);
        $checks = $this->sanityChecker->check($graph, $meta, $expectedTitle);

        $ageMin = max(0, (int)((time() - (int)($meta['cached_at'] ?? time())) / 60));

        return new ViewState(
            statusBadge: (string)($meta['status'] ?? 'unknown'),
            url: $url,
            crawledAt: $meta['crawled_at'] ?? null,
            etag: $cached['etag'] ?? null,
            hash: $meta['hash'] ?? null,
            source: sprintf('cache (hit, age %d min)', $ageMin),
            graphTypes: $this->extractGraphTypes($graph),
            sanityChecks: $checks,
            rawJsonLd: $graph,
        );
    }

    private function stateFromLiveResponse(string $url, \Enhancely\Enhancely\Client\JsonLdResponse $response, string $expectedTitle): ViewState
    {
        $graph = (array)$response->jsonld();
        $apiMeta = [
            'crawled_at' => $response->crawledAt(),
            'status' => $response->apiStatus(),
            'hash' => $response->hash(),
        ];
        $checks = $this->sanityChecker->check($graph, $apiMeta, $expectedTitle);

        return new ViewState(
            statusBadge: $response->apiStatus() ?? 'ready',
            url: $url,
            crawledAt: $response->crawledAt(),
            etag: $response->etag(),
            hash: $response->hash(),
            source: 'live (fresh)',
            graphTypes: $this->extractGraphTypes($graph),
            sanityChecks: $checks,
            rawJsonLd: $graph,
        );
    }

    private function extractGraphTypes(array $graph): array
    {
        $types = [];
        foreach ($graph['@graph'] ?? [] as $node) {
            if (isset($node['@type'])) {
                $types[] = (string)$node['@type'];
            }
        }
        return $types;
    }
}
