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

namespace Enhancely\Enhancely\Middleware;

use Enhancely\Enhancely\Cache\JsonLdCache;
use Enhancely\Enhancely\Client\Exception\ApiException;
use Enhancely\Enhancely\Client\HttpClientFactory;
use Enhancely\Enhancely\Client\JsonLdResponse;
use Enhancely\Enhancely\Client\UrlNormalizer;
use Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface;
use Enhancely\Enhancely\Domain\PageSettings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Frontend\Page\PageInformation;

final class JsonLdMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExtensionConfigurationInterface $configuration,
        private readonly HttpClientFactory $httpClientFactory,
        private readonly JsonLdCache $cache,
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
        $pageRecord = $this->pageRecord($request);
        if ($pageRecord !== null) {
            $pageType = (int)($pageRecord['doktype'] ?? 0);
            if (in_array($pageType, $this->configuration->getExcludedPageTypes(), true)) {
                return false;
            }
        }

        return true;
    }

    private function injectJsonLd(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $url = (string)$request->getUri();

        // Get cached ETag
        $cachedData = $this->cache->get($url);
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
                $this->cache->write(
                    $url,
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

            // The editor switched the output off for this page in the page
            // properties. The API call and the cache write above still ran on
            // purpose: the entry stays warm, so unchecking the box shows
            // current JSON-LD instead of waiting for a fresh crawl.
            if (PageSettings::jsonLdOutputSuppressed($this->pageRecord($request))) {
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
     * The page row behind the frontend.page.information attribute.
     *
     * @return array<string, mixed>|null Null when no page was resolved for
     *         this request, which is the case for every non-page route.
     */
    private function pageRecord(ServerRequestInterface $request): ?array
    {
        $pageInformation = $request->getAttribute('frontend.page.information');

        return $pageInformation instanceof PageInformation
            ? $pageInformation->getPageRecord()
            : null;
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
