<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Backend\SanityCheck\SanityChecker;
use Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class EnhancelyStatusController
{
    public function __construct(
        private readonly ExtensionConfigurationInterface $config,
        private readonly FrontendInterface $cache,
        private readonly SanityChecker $sanityChecker,
        private readonly UrlResolverInterface $urlResolver,
        private readonly JsonLdFetcherInterface $fetcher,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams() + (array)$request->getParsedBody();
        $pageUid = (int)($params['id'] ?? 0);
        $languageId = (int)($params['language'] ?? 0);
        $forceRefresh = !empty($params['forceRefresh']);

        $doktype = $this->resolveDoktype($pageUid);

        $state = $this->buildViewState($pageUid, $languageId, $doktype, $forceRefresh);

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assign('state', $state);
        $moduleTemplate->assign('pageUid', $pageUid);

        $pageInfo = $pageUid > 0 ? (BackendUtility::readPageAccess($pageUid, '1=1') ?: []) : [];
        $moduleTemplate->setTitle('Enhancely JSON-LD', $pageInfo['title'] ?? '');
        if ($pageInfo !== []) {
            $moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageInfo);
        }
        $moduleTemplate->makeDocHeaderModuleMenu(['id' => $pageUid]);

        return $moduleTemplate->renderResponse('Backend/InfoModule/Show');
    }

    private function resolveDoktype(int $pageUid): int
    {
        if ($pageUid <= 0) {
            return 0;
        }
        $row = BackendUtility::getRecord('pages', $pageUid, 'doktype');
        return (int)($row['doktype'] ?? 0);
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

        $expectedTitle = $this->urlResolver->expectedWebsiteTitle($pageUid);
        $cacheId = $this->cacheIdentifier($url);

        if ($forceRefresh) {
            $this->cache->remove($cacheId);
        }

        $cached = $this->cache->get($cacheId);

        if (is_array($cached) && isset($cached['meta']) && !$forceRefresh) {
            return $this->stateFromCachedMeta($url, $cached, $expectedTitle);
        }

        $response = $this->fetcher->fetch($url, $forceRefresh);

        if ($response->ready()) {
            \Enhancely\Enhancely\Middleware\JsonLdMiddleware::writeCachePayload(
                $this->cache,
                $cacheId,
                $response,
                $this->config->getCacheLifetime()
            );
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

    private function cacheIdentifier(string $url): string
    {
        return hash('sha256', $url);
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
