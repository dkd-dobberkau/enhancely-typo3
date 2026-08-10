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

namespace Enhancely\Enhancely\Client;

use Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Builds a configured HttpClient per request.
 *
 * Request-scoped rather than a singleton so no API key or base URL leaks
 * across requests in long-running PHP runtimes (Swoole, RoadRunner, FrankenPHP).
 */
final class HttpClientFactory
{
    public function __construct(
        private readonly ExtensionConfigurationInterface $configuration,
        private readonly RequestFactory $requestFactory,
    ) {}

    public function create(): HttpClientInterface
    {
        $apiKey = $this->configuration->getApiKey();
        if ($apiKey === '') {
            throw new \RuntimeException('Enhancely API key is not configured');
        }

        return new HttpClient(
            $this->requestFactory,
            $apiKey,
            $this->configuration->getApiBaseUrl(),
            $this->configuration->getTimeout(),
        );
    }
}
