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

namespace Enhancely\Tests\Unit\Backend\SanityCheck;

use Enhancely\Enhancely\Backend\SanityCheck\SanityChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SanityCheckerTest extends TestCase
{
    private function findResult(array $results, string $id): ?object
    {
        foreach ($results as $r) {
            if ($r->id === $id) {
                return $r;
            }
        }
        return null;
    }

    #[Test]
    public function breadcrumbAbsolutePassesWhenAllItemsAreAbsolute(): void
    {
        $jsonLd = [
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['position' => 1, 'item' => 'https://example.com/'],
                        ['position' => 2, 'item' => 'https://example.com/page'],
                    ],
                ],
            ],
        ];

        $checker = new SanityChecker();
        $results = $checker->check($jsonLd, [], 'Example Site');
        $r = $this->findResult($results, 'breadcrumb_absolute');

        self::assertNotNull($r);
        self::assertSame('pass', $r->level);
    }

    #[Test]
    public function breadcrumbAbsoluteWarnsWhenAnyItemIsRelative(): void
    {
        $jsonLd = [
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['position' => 1, 'item' => '/'],
                        ['position' => 2, 'item' => 'https://example.com/page'],
                    ],
                ],
            ],
        ];

        $checker = new SanityChecker();
        $results = $checker->check($jsonLd, [], 'Example Site');
        $r = $this->findResult($results, 'breadcrumb_absolute');

        self::assertSame('warn', $r->level);
        self::assertStringContainsString('relative', $r->message);
    }

    #[Test]
    public function breadcrumbAbsolutePassesWhenNoBreadcrumbListPresent(): void
    {
        $jsonLd = ['@graph' => [['@type' => 'WebPage']]];

        $checker = new SanityChecker();
        $results = $checker->check($jsonLd, [], 'Example Site');
        $r = $this->findResult($results, 'breadcrumb_absolute');

        self::assertSame('pass', $r->level);
    }

    #[Test]
    public function titleMismatchPassesWhenWebsiteNameMatches(): void
    {
        $jsonLd = [
            '@graph' => [
                ['@type' => 'WebSite', 'name' => 'Example Site'],
                ['@type' => 'Organization', 'name' => 'Example Site'],
            ],
        ];

        $checker = new SanityChecker();
        $results = $checker->check($jsonLd, [], 'Example Site');
        $r = $this->findResult($results, 'title_mismatch');

        self::assertSame('pass', $r->level);
    }

    #[Test]
    public function titleMismatchWarnsWhenWebsiteNameDiffers(): void
    {
        $jsonLd = [
            '@graph' => [
                ['@type' => 'WebSite', 'name' => 'Old Name'],
            ],
        ];

        $checker = new SanityChecker();
        $results = $checker->check($jsonLd, [], 'Example Site');
        $r = $this->findResult($results, 'title_mismatch');

        self::assertSame('warn', $r->level);
        self::assertStringContainsString('Old Name', $r->message);
        self::assertStringContainsString('Example Site', $r->message);
    }

    #[Test]
    public function titleMismatchPassesWhenExpectedTitleIsEmpty(): void
    {
        $jsonLd = ['@graph' => [['@type' => 'WebSite', 'name' => 'Anything']]];

        $checker = new SanityChecker();
        $results = $checker->check($jsonLd, [], '');
        $r = $this->findResult($results, 'title_mismatch');

        self::assertSame('pass', $r->level);
    }

    #[Test]
    public function crawlFreshnessPassesWhenCrawledRecently(): void
    {
        $apiMeta = ['crawled_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 3600)];

        $results = (new SanityChecker())->check([], $apiMeta, '');
        $r = $this->findResult($results, 'crawl_freshness');

        self::assertSame('pass', $r->level);
    }

    #[Test]
    public function crawlFreshnessWarnsWhenCrawledMoreThanSevenDaysAgo(): void
    {
        $apiMeta = ['crawled_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 8 * 86400)];

        $results = (new SanityChecker())->check([], $apiMeta, '');
        $r = $this->findResult($results, 'crawl_freshness');

        self::assertSame('warn', $r->level);
    }

    #[Test]
    public function crawlFreshnessPassesWhenCrawledAtMissing(): void
    {
        $results = (new SanityChecker())->check([], [], '');
        $r = $this->findResult($results, 'crawl_freshness');

        self::assertSame('pass', $r->level);
    }

    #[Test]
    public function sizeCheckPassesForSmallGraph(): void
    {
        $jsonLd = ['@graph' => [['@type' => 'WebPage', 'name' => 'small']]];

        $results = (new SanityChecker())->check($jsonLd, [], '');
        $r = $this->findResult($results, 'size');

        self::assertSame('pass', $r->level);
    }

    #[Test]
    public function sizeCheckWarnsWhenApproachingCap(): void
    {
        // 80% of 1 MiB ≈ 838,860 bytes. Build a payload above that.
        $blob = str_repeat('x', 900_000);
        $jsonLd = ['@graph' => [['@type' => 'WebPage', 'note' => $blob]]];

        $results = (new SanityChecker())->check($jsonLd, [], '');
        $r = $this->findResult($results, 'size');

        self::assertSame('warn', $r->level);
    }
}
