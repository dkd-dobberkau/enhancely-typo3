<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Client\Client;
use Enhancely\Enhancely\Client\JsonLdResponse;
use Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface;

final class JsonLdFetcher implements JsonLdFetcherInterface
{
    public function __construct(
        private readonly ExtensionConfigurationInterface $config,
    ) {}

    public function fetch(string $url, bool $forceRefresh): JsonLdResponse
    {
        Client::setApiKey($this->config->getApiKey());
        Client::setApiBaseUrl($this->config->getApiBaseUrl());
        // forceRefresh is reserved for future server-side cache bypass
        // (Cache-Control: no-cache header). The current Client API only
        // accepts an etag — we pass null on refresh to skip If-None-Match.
        return Client::jsonld($url, etag: null);
    }
}
