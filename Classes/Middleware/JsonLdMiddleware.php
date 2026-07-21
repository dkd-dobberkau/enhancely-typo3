<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Middleware;

use Enhancely\Enhancely\Client\Exception\ApiException;
use Enhancely\Enhancely\Client\HttpClientFactory;
use Enhancely\Enhancely\Client\JsonLdResponse;
use Enhancely\Enhancely\Client\UrlNormalizer;
use Enhancely\Enhancely\Configuration\ExtensionConfiguration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Frontend\Page\PageInformation;

final class JsonLdMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExtensionConfiguration $configuration,
        private readonly HttpClientFactory $httpClientFactory,
        private readonly FrontendInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly StreamFactory $streamFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->shouldProcess($request, $response)) {
            return $response;
        }

        return $this->injectJsonLd($request, $response);
    }

    private function shouldProcess(ServerRequestInterface $request, ResponseInterface $response): bool
    {
        // Check if extension is enabled
        if (!$this->configuration->isEnabled()) {
            return false;
        }

        // Check if API key is configured
        if ($this->configuration->getApiKey() === '') {
            return false;
        }

        // Only process HTML responses
        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return false;
        }

        // Only process successful responses
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // Check excluded page types.
        // frontend.page.information (PageInformation) replaces the deprecated
        // TypoScriptFrontendController / $GLOBALS['TSFE'], which is removed in
        // TYPO3 v14 (see #105230). The attribute exists since v13.0, so this
        // path works unchanged on both v13 and v14.
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            $pageType = (int)($pageInformation->getPageRecord()['doktype'] ?? 0);
            if (in_array($pageType, $this->configuration->getExcludedPageTypes(), true)) {
                return false;
            }
        }

        return true;
    }

    private function injectJsonLd(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $url = (string)$request->getUri();
        $cacheIdentifier = $this->getCacheIdentifier($url);

        // Get cached ETag
        $cachedData = $this->cache->get($cacheIdentifier);
        $cachedEtag = $cachedData['etag'] ?? null;
        $cachedJsonLd = $cachedData['jsonld'] ?? null;

        try {
            $client = $this->httpClientFactory->create();
            $normalizedUrl = UrlNormalizer::normalize($url);

            try {
                $enhancelyResponse = $client->postJsonLd($normalizedUrl, $cachedEtag);
            } catch (ApiException $e) {
                $enhancelyResponse = JsonLdResponse::createError($e->getMessage(), $e->getProblemDetails());
            }

            if ($enhancelyResponse->notModified() && $cachedJsonLd !== null) {
                // Content unchanged, use cached JSON-LD
                $jsonLdScript = $cachedJsonLd;
            } elseif ($enhancelyResponse->ready()) {
                // New content available
                $jsonLdScript = (string)$enhancelyResponse;

                // Cache the new data
                self::writeCachePayload(
                    $this->cache,
                    $cacheIdentifier,
                    $enhancelyResponse,
                    $this->configuration->getCacheLifetime()
                );
            } else {
                // Not ready yet or error, skip injection
                if ($enhancelyResponse->error()) {
                    $logContext = [
                        'url' => $url,
                        'error' => $enhancelyResponse->error(),
                    ];
                    if ($enhancelyResponse->problemDetails()) {
                        $logContext['problemDetails'] = $enhancelyResponse->problemDetails();
                    }
                    $this->logger->warning('Enhancely API error', $logContext);
                }
                return $response;
            }

            // Inject JSON-LD before </head>
            $body = (string)$response->getBody();
            $modifiedBody = $this->insertBeforeHeadClose($body, $jsonLdScript);

            return $response->withBody(
                $this->streamFactory->createStream($modifiedBody)
            );
        } catch (\Throwable $e) {
            $this->logger->error('Enhancely JSON-LD injection failed', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);
            return $response;
        }
    }

    /**
     * Build and store the cache payload for one URL.
     *
     * Two layers in the payload:
     *  - 'etag' + 'jsonld' — consumed by this middleware on subsequent FE
     *    requests (conditional ETag handling).
     *  - 'meta' — consumed by the BE info-module tab. Backwards compatible:
     *    entries written by older versions lack the 'meta' key, and the BE
     *    treats those as a cache miss.
     */
    public static function writeCachePayload(
        FrontendInterface $cache,
        string $cacheIdentifier,
        JsonLdResponse $response,
        int $lifetime
    ): void {
        $cache->set(
            $cacheIdentifier,
            [
                'etag' => $response->etag(),
                'jsonld' => (string)$response,
                'meta' => [
                    'crawled_at' => $response->crawledAt(),
                    'status' => $response->apiStatus(),
                    'hash' => $response->hash(),
                    'graph' => $response->jsonld(),
                    'cached_at' => time(),
                ],
            ],
            ['pages'],
            $lifetime
        );
    }

    private function getCacheIdentifier(string $url): string
    {
        // Cache key must match the URL we send to the API; otherwise queries
        // like ?utm_source=… create endless cache fragmentation while the API
        // returns the same JSON-LD for the normalized URL.
        return 'enhancely_' . md5(UrlNormalizer::normalize($url));
    }

    private function insertBeforeHeadClose(string $html, string $jsonLd): string
    {
        $position = stripos($html, '</head>');
        if ($position === false) {
            return $html;
        }

        return substr($html, 0, $position) . "\n" . $jsonLd . "\n" . substr($html, $position);
    }
}
