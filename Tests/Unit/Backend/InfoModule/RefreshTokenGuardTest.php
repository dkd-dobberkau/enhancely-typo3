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

use Enhancely\Enhancely\Backend\InfoModule\RefreshTokenGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\FormProtection\AbstractFormProtection;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;

final class RefreshTokenGuardTest extends TestCase
{
    private function guardWith(AbstractFormProtection $formProtection): RefreshTokenGuard
    {
        $factory = $this->createMock(FormProtectionFactory::class);
        $factory->method('createFromRequest')->willReturn($formProtection);
        return new RefreshTokenGuard($factory);
    }

    /**
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, mixed> $queryParams
     */
    private function requestMock(?array $parsedBody, array $queryParams = []): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn($parsedBody);
        $request->method('getQueryParams')->willReturn($queryParams);
        return $request;
    }

    /**
     * The GET form of the refresh was the cheapest way to abuse this: a link or
     * an <img src> on any page an editor opens. It must not be honoured, and
     * must not even reach token validation.
     */
    #[Test]
    public function forceRefreshFromTheQueryStringIsIgnored(): void
    {
        $formProtection = $this->createMock(AbstractFormProtection::class);
        $formProtection->expects(self::never())->method('validateToken');

        $guard = $this->guardWith($formProtection);

        self::assertFalse(
            $guard->isRefreshRequested($this->requestMock(null, ['id' => 4711, 'forceRefresh' => 1]))
        );
    }

    #[Test]
    public function postWithoutTokenIsRejected(): void
    {
        $formProtection = $this->createMock(AbstractFormProtection::class);
        $formProtection->method('validateToken')->willReturn(false);

        $guard = $this->guardWith($formProtection);

        self::assertFalse(
            $guard->isRefreshRequested($this->requestMock(['id' => 4711, 'forceRefresh' => 1]))
        );
    }

    #[Test]
    public function postWithInvalidTokenIsRejected(): void
    {
        $formProtection = $this->createMock(AbstractFormProtection::class);
        $formProtection->expects(self::once())
            ->method('validateToken')
            ->with('forged', 'enhancely_info', 'forceRefresh')
            ->willReturn(false);

        $guard = $this->guardWith($formProtection);

        self::assertFalse(
            $guard->isRefreshRequested(
                $this->requestMock(['id' => 4711, 'forceRefresh' => 1, 'formToken' => 'forged'])
            )
        );
    }

    #[Test]
    public function postWithValidTokenIsAccepted(): void
    {
        $formProtection = $this->createMock(AbstractFormProtection::class);
        $formProtection->method('validateToken')->willReturn(true);

        $guard = $this->guardWith($formProtection);

        self::assertTrue(
            $guard->isRefreshRequested(
                $this->requestMock(['id' => 4711, 'forceRefresh' => 1, 'formToken' => 'good'])
            )
        );
    }

    #[Test]
    public function aPostThatDoesNotAskForARefreshIsNotOne(): void
    {
        $formProtection = $this->createMock(AbstractFormProtection::class);
        $formProtection->expects(self::never())->method('validateToken');

        $guard = $this->guardWith($formProtection);

        self::assertFalse($guard->isRefreshRequested($this->requestMock(['id' => 4711])));
    }

    #[Test]
    public function generateUsesTheSameFormAndActionAsValidation(): void
    {
        $formProtection = $this->createMock(AbstractFormProtection::class);
        $formProtection->expects(self::once())
            ->method('generateToken')
            ->with('enhancely_info', 'forceRefresh')
            ->willReturn('token-abc');

        $guard = $this->guardWith($formProtection);

        self::assertSame('token-abc', $guard->generate($this->requestMock(null)));
    }
}
