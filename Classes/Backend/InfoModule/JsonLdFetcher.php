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

use Enhancely\Enhancely\Client\Exception\ApiException;
use Enhancely\Enhancely\Client\HttpClientFactory;
use Enhancely\Enhancely\Client\JsonLdResponse;
use Enhancely\Enhancely\Client\UrlNormalizer;
use Psr\Log\LoggerInterface;

final class JsonLdFetcher implements JsonLdFetcherInterface
{
    public function __construct(
        private readonly HttpClientFactory $httpClientFactory,
        private readonly LoggerInterface $logger,
    ) {}

    public function fetch(string $url): JsonLdResponse
    {
        try {
            // No etag: the backend module deliberately asks for the current
            // state instead of a conditional 412.
            return $this->httpClientFactory->create()->postJsonLd(
                UrlNormalizer::normalize($url),
                null
            );
        } catch (ApiException $e) {
            // JsonLdResponse carries only a message, so the exception chain
            // would be lost here — log it before downgrading.
            $this->logger->warning('Enhancely API error in backend module', [
                'url' => $url,
                'exception' => $e,
            ]);
            return JsonLdResponse::createError($e->getMessage(), $e->getProblemDetails());
        } catch (\Throwable $e) {
            $this->logger->error('Enhancely lookup failed in backend module', [
                'url' => $url,
                'exception' => $e,
            ]);
            return JsonLdResponse::createError($e->getMessage());
        }
    }
}
