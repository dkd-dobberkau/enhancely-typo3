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

namespace Enhancely\Tests\Unit\Middleware;

use Enhancely\Enhancely\Cache\JsonLdCache;
use Enhancely\Enhancely\Client\HttpClientFactory;
use Enhancely\Enhancely\Configuration\ExtensionConfiguration;
use Enhancely\Enhancely\Middleware\JsonLdMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Frontend\Page\PageInformation;

final class JsonLdMiddlewareTest extends TestCase
{
    /**
     * Excluded doktypes must be read from the frontend.page.information
     * request attribute (PageInformation), the v13+ replacement for the
     * TypoScriptFrontendController that is removed in v14 (#105230).
     */
    #[Test]
    public function skipsInjectionForExcludedPageTypeViaPageInformation(): void
    {
        $configuration = $this->createMock(ExtensionConfiguration::class);
        $configuration->method('isEnabled')->willReturn(true);
        $configuration->method('getApiKey')->willReturn('sk_test');
        $configuration->method('getExcludedPageTypes')->willReturn([4]);

        $httpClientFactory = $this->createMock(HttpClientFactory::class);
        // The API must not be contacted for an excluded page type.
        $httpClientFactory->expects(self::never())->method('create');

        $pageInformation = new PageInformation();
        $pageInformation->setId(1);
        $pageInformation->setPageRecord(['uid' => 1, 'doktype' => 4]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturnCallback(
            static fn(string $name, $default = null) => $name === 'frontend.page.information'
                ? $pageInformation
                : $default
        );

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeaderLine')->willReturn('text/html; charset=utf-8');
        $response->method('getStatusCode')->willReturn(200);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $middleware = new JsonLdMiddleware(
            $configuration,
            $httpClientFactory,
            new JsonLdCache($this->createMock(FrontendInterface::class)),
            $this->createMock(LoggerInterface::class),
            $this->createMock(StreamFactory::class),
        );

        // Response passes through unmodified; no injection happened.
        self::assertSame($response, $middleware->process($request, $handler));
    }
}
