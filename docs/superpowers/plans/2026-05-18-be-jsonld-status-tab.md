# Backend JSON-LD Status Tab Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship `enhancely/enhancely-for-typo3` v1.3.0 with a read-only Info-module function that shows the Enhancely JSON-LD status, sanity checks, and raw payload for the selected page.

**Architecture:** New `EnhancelyStatusController` registered as a sub-module of `web_info` via `Configuration/Backend/Modules.php`. Reads from an extended `enhancely_etag` cache (backwards-compatible `meta` block) populated by the existing FE middleware; falls back to a live `Client::jsonld()` call on miss. A pure-logic `SanityChecker` runs four checks against the JSON-LD graph. Read-only with a Refresh button that re-fetches with `Cache-Control: no-cache` and invalidates the shared cache entry.

**Tech Stack:** TYPO3 12.4 – 14.99, PHP 8.2+, PHPUnit 10/11, Fluid, Symfony DI (autowiring), Guzzle (via existing HTTP client).

**Spec:** `docs/superpowers/specs/2026-05-18-be-jsonld-status-tab-design.md`

---

## File structure

### New files

| Path | Responsibility |
|---|---|
| `Classes/Backend/InfoModule/EnhancelyStatusController.php` | Info-module function entry point. Resolves page→URL, reads cache or calls Client, renders Fluid view. |
| `Classes/Backend/SanityCheck/CheckResult.php` | Tiny immutable DTO: `id`, `level` (`pass`/`warn`), `message`. |
| `Classes/Backend/SanityCheck/SanityChecker.php` | Pure logic. `check(array $jsonLd, array $apiMeta, string $expectedWebsiteTitle): CheckResult[]`. |
| `Resources/Private/Templates/Backend/InfoModule/Show.html` | Fluid template. |
| `Resources/Private/Partials/Backend/Status.html` | Status-badge partial. |
| `Resources/Public/Css/backend.css` | Minimal styling. |
| `Resources/Private/Language/locallang_mod.xlf` | BE strings. |
| `Configuration/Backend/Modules.php` | Sub-module registration under `web_info`. |
| `Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php` | One test class, one method per check + edge cases. |
| `Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php` | One test method per execution path. |
| `Tests/Unit/Middleware/JsonLdMiddlewareTest.php` | New; covers the cache-payload extension. |

### Modified files

| Path | Change |
|---|---|
| `Classes/Client/JsonLdResponse.php` | Add `crawledAt()`, `apiStatus()`, `hash()` accessors reading from the existing `$data` array. |
| `Classes/Middleware/JsonLdMiddleware.php` | When writing to `enhancely_etag` cache, also store `meta` (crawled_at, api status, hash, graph, cached_at). |
| `Configuration/Services.yaml` | Public-by-need for the controller (TYPO3 BE needs the controller to be addressable). |
| `ext_emconf.php` | Version 1.2.3 → 1.3.0. |
| `README.md` | New "Backend integration" section + screenshot reference + manual smoke test. |
| `RELEASE.md` | New 1.3.0 entry following existing format. |
| `.gitignore` | Add `/.superpowers/`. |

---

## Task 1: Branch, gitignore, and verify clean baseline

**Files:**
- Modify: `.gitignore`

- [ ] **Step 1: Create feature branch**

Run from `/Users/olivier/Versioncontrol/local/enhancelyai/enhancely`:

```bash
git checkout -b feature/be-info-jsonld-status
```

Expected: switched to a new branch.

- [ ] **Step 2: Add `.superpowers/` to `.gitignore`**

Append one line to `.gitignore`:

```
/.superpowers/
```

- [ ] **Step 3: Verify baseline tests pass**

```bash
composer install
vendor/bin/phpunit
```

Expected: green, all existing tests pass.

- [ ] **Step 4: Commit**

```bash
git add .gitignore
git commit -m "chore: ignore .superpowers/ brainstorming workspace"
```

---

## Task 2: Add `crawledAt()` / `apiStatus()` / `hash()` accessors to `JsonLdResponse`

**Files:**
- Modify: `Classes/Client/JsonLdResponse.php`
- Test: `Tests/Unit/Client/JsonLdResponseTest.php`

The Enhancely API response wrapper is `{hash, url, jsonld, etag, crawled_at, http_status_code, status, readonly}`. `JsonLdResponse` already stores the whole wrapper as `$data` but only exposes `jsonld()` and `etag()`. The BE tab needs the other top-level fields.

- [ ] **Step 1: Write three failing tests**

Append to `Tests/Unit/Client/JsonLdResponseTest.php` inside the existing test class:

```php
#[Test]
public function crawledAtReturnsTimestampFromApiResponse(): void
{
    $response = JsonLdResponse::fromApiResponse(200, [
        'jsonld' => ['@type' => 'WebPage'],
        'crawled_at' => '2026-05-18T12:32:15.410Z',
    ], 'etag-abc');

    self::assertSame('2026-05-18T12:32:15.410Z', $response->crawledAt());
}

#[Test]
public function apiStatusReturnsStatusFromApiResponse(): void
{
    $response = JsonLdResponse::fromApiResponse(200, [
        'jsonld' => ['@type' => 'WebPage'],
        'status' => 'ready',
    ]);

    self::assertSame('ready', $response->apiStatus());
}

#[Test]
public function hashReturnsHashFromApiResponse(): void
{
    $response = JsonLdResponse::fromApiResponse(200, [
        'jsonld' => ['@type' => 'WebPage'],
        'hash' => '148a0a50e1812e0f604e17da47f0c4da',
    ]);

    self::assertSame('148a0a50e1812e0f604e17da47f0c4da', $response->hash());
}

#[Test]
public function accessorsReturnNullForMissingFields(): void
{
    $response = JsonLdResponse::createError('boom');

    self::assertNull($response->crawledAt());
    self::assertNull($response->apiStatus());
    self::assertNull($response->hash());
}
```

- [ ] **Step 2: Run the failing tests**

```bash
vendor/bin/phpunit --filter 'crawledAtReturnsTimestampFromApiResponse|apiStatusReturnsStatusFromApiResponse|hashReturnsHashFromApiResponse|accessorsReturnNullForMissingFields'
```

Expected: 4 failures with "method not defined" on `JsonLdResponse`.

- [ ] **Step 3: Add the three accessors**

In `Classes/Client/JsonLdResponse.php`, after `etag()`:

```php
public function crawledAt(): ?string
{
    return isset($this->data['crawled_at']) ? (string)$this->data['crawled_at'] : null;
}

public function apiStatus(): ?string
{
    return isset($this->data['status']) ? (string)$this->data['status'] : null;
}

public function hash(): ?string
{
    return isset($this->data['hash']) ? (string)$this->data['hash'] : null;
}
```

- [ ] **Step 4: Run the tests**

```bash
vendor/bin/phpunit Tests/Unit/Client/JsonLdResponseTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add Classes/Client/JsonLdResponse.php Tests/Unit/Client/JsonLdResponseTest.php
git commit -m "feat(client): expose crawled_at, status, hash on JsonLdResponse"
```

---

## Task 3: Extend FE middleware cache payload with `meta` block (backwards compatible)

**Files:**
- Modify: `Classes/Middleware/JsonLdMiddleware.php`
- Create: `Tests/Unit/Middleware/JsonLdMiddlewareTest.php`

The FE middleware today writes `['etag' => ..., 'jsonld' => '<script>...</script>']` into `enhancely_etag`. We add a `meta` key containing the data the BE tab needs. Old entries without `meta` remain readable by the FE (which only reads `etag` + `jsonld`) — the BE controller treats them as a miss.

- [ ] **Step 1: Write the failing middleware test**

Create `Tests/Unit/Middleware/JsonLdMiddlewareTest.php`:

```php
<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Middleware;

use Enhancely\Enhancely\Client\JsonLdResponse;
use Enhancely\Enhancely\Configuration\ExtensionConfiguration;
use Enhancely\Enhancely\Middleware\JsonLdMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class JsonLdMiddlewareTest extends TestCase
{
    #[Test]
    public function cachedPayloadIncludesMetaBlock(): void
    {
        $apiResponse = JsonLdResponse::fromApiResponse(
            200,
            [
                'jsonld' => ['@graph' => [['@type' => 'WebPage', 'name' => 'X']]],
                'crawled_at' => '2026-05-18T12:32:15.410Z',
                'status' => 'ready',
                'hash' => 'abc123',
            ],
            'etag-xyz'
        );

        $cache = $this->createMock(FrontendInterface::class);
        $captured = null;
        $cache->expects(self::once())
            ->method('set')
            ->willReturnCallback(function ($id, $payload) use (&$captured): void {
                $captured = $payload;
            });

        // Drive the middleware's cache-write helper directly. (The middleware
        // currently inlines the set() call; this test asserts the shape of
        // the payload the middleware hands to the cache. If the helper does
        // not exist yet, extract one — see Step 3.)
        JsonLdMiddleware::writeCachePayload($cache, 'cache-id', $apiResponse, 86400);

        self::assertSame('etag-xyz', $captured['etag']);
        self::assertStringStartsWith('<script', $captured['jsonld']);
        self::assertSame('2026-05-18T12:32:15.410Z', $captured['meta']['crawled_at']);
        self::assertSame('ready', $captured['meta']['status']);
        self::assertSame('abc123', $captured['meta']['hash']);
        self::assertSame(
            [['@type' => 'WebPage', 'name' => 'X']],
            $captured['meta']['graph']['@graph']
        );
        self::assertIsInt($captured['meta']['cached_at']);
    }
}
```

- [ ] **Step 2: Run the failing test**

```bash
vendor/bin/phpunit Tests/Unit/Middleware/JsonLdMiddlewareTest.php
```

Expected: fail — `writeCachePayload` does not exist yet.

- [ ] **Step 3: Extract a static helper and call it from the middleware**

In `Classes/Middleware/JsonLdMiddleware.php`, add a new public static method (at the bottom of the class, before the closing brace):

```php
/**
 * Build and store the cache payload for one URL.
 *
 * The payload has two layers:
 *  - 'etag' + 'jsonld' (the script tag) — consumed by this middleware on
 *    subsequent FE requests.
 *  - 'meta' — consumed by the BE info-module tab. Backwards compatible:
 *    entries written by older versions lack the 'meta' key and the BE
 *    treats them as a cache miss.
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
```

Then replace the existing inline `$this->cache->set(...)` block (currently around lines 104–113) with a call to the helper:

```php
self::writeCachePayload(
    $this->cache,
    $cacheIdentifier,
    $enhancelyResponse,
    $this->configuration->getCacheLifetime()
);
```

- [ ] **Step 4: Run the tests**

```bash
vendor/bin/phpunit
```

Expected: green across the full suite.

- [ ] **Step 5: Commit**

```bash
git add Classes/Middleware/JsonLdMiddleware.php Tests/Unit/Middleware/JsonLdMiddlewareTest.php
git commit -m "feat(middleware): store backend meta block in enhancely_etag cache"
```

---

## Task 4: `CheckResult` DTO

**Files:**
- Create: `Classes/Backend/SanityCheck/CheckResult.php`
- Test: `Tests/Unit/Backend/SanityCheck/CheckResultTest.php`

- [ ] **Step 1: Write the failing test**

Create `Tests/Unit/Backend/SanityCheck/CheckResultTest.php`:

```php
<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Backend\SanityCheck;

use Enhancely\Enhancely\Backend\SanityCheck\CheckResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckResultTest extends TestCase
{
    #[Test]
    public function passConstructorYieldsPassLevel(): void
    {
        $result = CheckResult::pass('breadcrumb_absolute', 'All items absolute');

        self::assertSame('breadcrumb_absolute', $result->id);
        self::assertSame('pass', $result->level);
        self::assertSame('All items absolute', $result->message);
    }

    #[Test]
    public function warnConstructorYieldsWarnLevel(): void
    {
        $result = CheckResult::warn('title_mismatch', 'Stale title');

        self::assertSame('warn', $result->level);
    }
}
```

- [ ] **Step 2: Run failing test**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/CheckResultTest.php
```

Expected: class not found.

- [ ] **Step 3: Implement**

Create `Classes/Backend/SanityCheck/CheckResult.php`:

```php
<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\SanityCheck;

final class CheckResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $level,
        public readonly string $message,
    ) {}

    public static function pass(string $id, string $message): self
    {
        return new self($id, 'pass', $message);
    }

    public static function warn(string $id, string $message): self
    {
        return new self($id, 'warn', $message);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/CheckResultTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add Classes/Backend/SanityCheck/CheckResult.php Tests/Unit/Backend/SanityCheck/CheckResultTest.php
git commit -m "feat(sanity): add CheckResult DTO"
```

---

## Task 5: `SanityChecker` — BreadcrumbList absolute check

**Files:**
- Create: `Classes/Backend/SanityCheck/SanityChecker.php`
- Test: `Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php`

- [ ] **Step 1: Write failing tests**

Create `Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php`:

```php
<?php

declare(strict_types=1);

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
}
```

- [ ] **Step 2: Run failing tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
```

Expected: class not found.

- [ ] **Step 3: Implement skeleton + breadcrumb check**

Create `Classes/Backend/SanityCheck/SanityChecker.php`:

```php
<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\SanityCheck;

final class SanityChecker
{
    /**
     * @param array<string, mixed> $jsonLd       The 'jsonld' object from the Enhancely API response.
     * @param array<string, mixed> $apiMeta      The 'meta' block (crawled_at, status, hash, ...).
     * @param string               $expectedWebsiteTitle From Site::getConfiguration()['websiteTitle'].
     * @return CheckResult[]
     */
    public function check(array $jsonLd, array $apiMeta, string $expectedWebsiteTitle): array
    {
        return [
            $this->checkBreadcrumbAbsolute($jsonLd),
        ];
    }

    private function checkBreadcrumbAbsolute(array $jsonLd): CheckResult
    {
        $relative = [];
        foreach ($this->findNodesByType($jsonLd, 'BreadcrumbList') as $bc) {
            foreach ($bc['itemListElement'] ?? [] as $item) {
                $url = (string)($item['item'] ?? '');
                if ($url === '') {
                    continue;
                }
                if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                    $relative[] = $url;
                }
            }
        }

        if ($relative === []) {
            return CheckResult::pass(
                'breadcrumb_absolute',
                'BreadcrumbList items are absolute URLs'
            );
        }

        return CheckResult::warn(
            'breadcrumb_absolute',
            sprintf(
                'BreadcrumbList contains %d relative URL(s): %s',
                count($relative),
                implode(', ', array_slice($relative, 0, 3))
            )
        );
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function findNodesByType(array $jsonLd, string $type): iterable
    {
        $graph = $jsonLd['@graph'] ?? [$jsonLd];
        foreach ($graph as $node) {
            if (is_array($node) && (($node['@type'] ?? null) === $type)) {
                yield $node;
            }
        }
    }
}
```

- [ ] **Step 4: Run tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add Classes/Backend/SanityCheck/SanityChecker.php Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
git commit -m "feat(sanity): add BreadcrumbList-absolute check"
```

---

## Task 6: Title-mismatch check

**Files:**
- Modify: `Classes/Backend/SanityCheck/SanityChecker.php`
- Modify: `Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php`

- [ ] **Step 1: Write failing tests**

Append to `SanityCheckerTest`:

```php
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
```

- [ ] **Step 2: Run failing tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
```

Expected: fail.

- [ ] **Step 3: Implement**

In `SanityChecker::check()`, append to the returned array:

```php
$this->checkTitleMismatch($jsonLd, $expectedWebsiteTitle),
```

Add the method:

```php
private function checkTitleMismatch(array $jsonLd, string $expected): CheckResult
{
    if ($expected === '') {
        return CheckResult::pass(
            'title_mismatch',
            'No websiteTitle configured — skipping check'
        );
    }

    $mismatches = [];
    foreach (['WebSite', 'Organization'] as $type) {
        foreach ($this->findNodesByType($jsonLd, $type) as $node) {
            $name = (string)($node['name'] ?? '');
            if ($name !== '' && $name !== $expected) {
                $mismatches[$type] = $name;
            }
        }
    }

    if ($mismatches === []) {
        return CheckResult::pass(
            'title_mismatch',
            'Site name matches configured websiteTitle'
        );
    }

    $pairs = [];
    foreach ($mismatches as $type => $found) {
        $pairs[] = sprintf('%s.name="%s"', $type, $found);
    }

    return CheckResult::warn(
        'title_mismatch',
        sprintf(
            'Site name mismatch: %s; expected "%s" (Enhancely may hold a pre-rename crawl)',
            implode(', ', $pairs),
            $expected
        )
    );
}
```

- [ ] **Step 4: Run tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add Classes/Backend/SanityCheck/SanityChecker.php Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
git commit -m "feat(sanity): add title-mismatch check"
```

---

## Task 7: Crawl-freshness check

**Files:**
- Modify: `Classes/Backend/SanityCheck/SanityChecker.php`
- Modify: `Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php`

- [ ] **Step 1: Write failing tests**

Append to `SanityCheckerTest`:

```php
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
```

- [ ] **Step 2: Run failing tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
```

Expected: fail.

- [ ] **Step 3: Implement**

Add to `check()`:

```php
$this->checkCrawlFreshness($apiMeta),
```

Add method:

```php
private function checkCrawlFreshness(array $apiMeta): CheckResult
{
    $crawledAt = (string)($apiMeta['crawled_at'] ?? '');
    if ($crawledAt === '') {
        return CheckResult::pass(
            'crawl_freshness',
            'No crawl timestamp available — skipping check'
        );
    }

    $ts = strtotime($crawledAt);
    if ($ts === false) {
        return CheckResult::pass(
            'crawl_freshness',
            'Crawl timestamp unparseable — skipping check'
        );
    }

    $ageSeconds = time() - $ts;
    $sevenDays = 7 * 86400;

    if ($ageSeconds <= $sevenDays) {
        return CheckResult::pass(
            'crawl_freshness',
            sprintf('Crawled %d hours ago (threshold 7 days)', max(0, (int)($ageSeconds / 3600)))
        );
    }

    return CheckResult::warn(
        'crawl_freshness',
        sprintf('Last crawl is %d days old (threshold 7 days)', (int)($ageSeconds / 86400))
    );
}
```

- [ ] **Step 4: Run tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add Classes/Backend/SanityCheck/SanityChecker.php Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
git commit -m "feat(sanity): add crawl-freshness check"
```

---

## Task 8: JSON-LD size check

**Files:**
- Modify: `Classes/Backend/SanityCheck/SanityChecker.php`
- Modify: `Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php`

- [ ] **Step 1: Write failing tests**

Append to `SanityCheckerTest`:

```php
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
```

- [ ] **Step 2: Run failing tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
```

Expected: fail.

- [ ] **Step 3: Implement**

Add to `check()`:

```php
$this->checkSize($jsonLd),
```

Add method:

```php
private function checkSize(array $jsonLd): CheckResult
{
    $bytes = strlen((string)json_encode($jsonLd));
    $cap = 1024 * 1024;            // 1 MiB — matches HttpClient::MAX_RESPONSE_BYTES
    $threshold = (int)($cap * 0.8); // 80%

    if ($bytes < $threshold) {
        return CheckResult::pass(
            'size',
            sprintf('JSON-LD %s KiB (cap 1 MiB)', number_format($bytes / 1024, 1))
        );
    }

    return CheckResult::warn(
        'size',
        sprintf(
            'JSON-LD %s KiB approaching 1 MiB cap',
            number_format($bytes / 1024, 1)
        )
    );
}
```

- [ ] **Step 4: Run tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add Classes/Backend/SanityCheck/SanityChecker.php Tests/Unit/Backend/SanityCheck/SanityCheckerTest.php
git commit -m "feat(sanity): add JSON-LD size check"
```

---

## Task 9: Controller scaffold — config-driven banners

**Files:**
- Create: `Classes/Backend/InfoModule/EnhancelyStatusController.php`
- Create: `Classes/Backend/InfoModule/ViewState.php`
- Create: `Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php`

This task introduces the controller as a *pure decision function* that returns a `ViewState` object given inputs. We do not render Fluid yet — that comes in Task 14. By splitting decision from rendering we keep the controller unit-testable without a TYPO3 BE container.

- [ ] **Step 1: Write failing tests**

Create `Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Enhancely\Tests\Unit\Backend\InfoModule;

use Enhancely\Enhancely\Backend\InfoModule\EnhancelyStatusController;
use Enhancely\Enhancely\Backend\InfoModule\ViewState;
use Enhancely\Enhancely\Backend\SanityCheck\SanityChecker;
use Enhancely\Enhancely\Configuration\ExtensionConfiguration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class EnhancelyStatusControllerTest extends TestCase
{
    private function controllerWith(
        ExtensionConfiguration $config,
        ?FrontendInterface $cache = null
    ): EnhancelyStatusController {
        return new EnhancelyStatusController(
            $config,
            $cache ?? $this->createMock(FrontendInterface::class),
            new SanityChecker(),
        );
    }

    private function configMock(string $apiKey = 'k', bool $enabled = true, array $excluded = []): ExtensionConfiguration
    {
        $m = $this->createMock(ExtensionConfiguration::class);
        $m->method('getApiKey')->willReturn($apiKey);
        $m->method('isEnabled')->willReturn($enabled);
        $m->method('getExcludedPageTypes')->willReturn($excluded);
        return $m;
    }

    #[Test]
    public function returnsNotConfiguredWhenApiKeyEmpty(): void
    {
        $controller = $this->controllerWith($this->configMock(apiKey: ''));
        $state = $controller->buildViewState(pageUid: 1, languageId: 0, doktype: 1, forceRefresh: false);

        self::assertSame(ViewState::BANNER_NOT_CONFIGURED, $state->banner);
    }

    #[Test]
    public function returnsDisabledWhenExtensionDisabled(): void
    {
        $controller = $this->controllerWith($this->configMock(enabled: false));
        $state = $controller->buildViewState(1, 0, 1, false);

        self::assertSame(ViewState::BANNER_DISABLED, $state->banner);
    }

    #[Test]
    public function returnsSkippedWhenDoktypeExcluded(): void
    {
        $controller = $this->controllerWith($this->configMock(excluded: [404]));
        $state = $controller->buildViewState(1, 0, 404, false);

        self::assertSame('skipped', $state->statusBadge);
    }
}
```

- [ ] **Step 2: Run failing tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php
```

Expected: classes not found.

- [ ] **Step 3: Implement `ViewState`**

Create `Classes/Backend/InfoModule/ViewState.php`:

```php
<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Backend\SanityCheck\CheckResult;

final class ViewState
{
    public const BANNER_NONE = '';
    public const BANNER_NOT_CONFIGURED = 'not_configured';
    public const BANNER_DISABLED = 'disabled';
    public const BANNER_SITE_ERROR = 'site_error';

    /**
     * @param CheckResult[] $sanityChecks
     * @param array<string, mixed>|null $rawJsonLd
     */
    public function __construct(
        public readonly string $banner = self::BANNER_NONE,
        public readonly string $bannerDetail = '',
        public readonly string $statusBadge = 'unknown',    // ready|processing|error|skipped|unknown
        public readonly string $url = '',
        public readonly ?string $crawledAt = null,
        public readonly ?string $etag = null,
        public readonly ?string $hash = null,
        public readonly string $source = '',                // 'cache (hit, age N min)' | 'live (fresh)' | ''
        public readonly array $graphTypes = [],
        public readonly array $sanityChecks = [],
        public readonly ?array $rawJsonLd = null,
        public readonly ?string $errorMessage = null,
    ) {}
}
```

- [ ] **Step 4: Implement controller scaffold**

Create `Classes/Backend/InfoModule/EnhancelyStatusController.php`:

```php
<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Backend\SanityCheck\SanityChecker;
use Enhancely\Enhancely\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

final class EnhancelyStatusController
{
    public function __construct(
        private readonly ExtensionConfiguration $config,
        private readonly FrontendInterface $cache,
        private readonly SanityChecker $sanityChecker,
    ) {}

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

        // URL resolution + fetch logic — Task 10 onwards.
        return new ViewState(statusBadge: 'unknown');
    }
}
```

- [ ] **Step 5: Run tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php
```

Expected: green.

- [ ] **Step 6: Commit**

```bash
git add Classes/Backend/InfoModule Tests/Unit/Backend/InfoModule
git commit -m "feat(backend): controller scaffold + ViewState + banner paths"
```

---

## Task 10: Controller — URL resolution + site/URL errors

**Files:**
- Modify: `Classes/Backend/InfoModule/EnhancelyStatusController.php`
- Modify: `Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php`

Inject a `UrlResolver` collaborator so we don't need a real `SiteFinder` in tests.

- [ ] **Step 1: Write failing tests**

Append to `EnhancelyStatusControllerTest`:

```php
#[Test]
public function returnsSiteErrorWhenUrlResolverThrows(): void
{
    $resolver = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\UrlResolver::class);
    $resolver->method('resolve')->willThrowException(new \RuntimeException('no site for page 42'));

    $controller = new EnhancelyStatusController(
        $this->configMock(),
        $this->createMock(FrontendInterface::class),
        new SanityChecker(),
        $resolver,
    );

    $state = $controller->buildViewState(42, 0, 1, false);

    self::assertSame(ViewState::BANNER_SITE_ERROR, $state->banner);
    self::assertStringContainsString('no site for page 42', $state->bannerDetail);
}
```

Also update the `controllerWith()` helper:

```php
private function controllerWith(
    ExtensionConfiguration $config,
    ?FrontendInterface $cache = null,
    ?\Enhancely\Enhancely\Backend\InfoModule\UrlResolver $resolver = null,
): EnhancelyStatusController {
    if ($resolver === null) {
        $resolver = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\UrlResolver::class);
        $resolver->method('resolve')->willReturn('https://example.com/');
    }
    return new EnhancelyStatusController(
        $config,
        $cache ?? $this->createMock(FrontendInterface::class),
        new SanityChecker(),
        $resolver,
    );
}
```

- [ ] **Step 2: Run failing test**

```bash
vendor/bin/phpunit Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php
```

Expected: fail — `UrlResolver` does not exist.

- [ ] **Step 3: Implement `UrlResolver`**

Create `Classes/Backend/InfoModule/UrlResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

use TYPO3\CMS\Core\Site\SiteFinder;

final class UrlResolver
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * @throws \RuntimeException If page has no site or URL cannot be built.
     */
    public function resolve(int $pageUid, int $languageId): string
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                sprintf('Page %d has no site configuration: %s', $pageUid, $e->getMessage()),
                0,
                $e
            );
        }

        try {
            $uri = $site->getRouter()->generateUri($pageUid, ['_language' => $languageId]);
            return (string)$uri;
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                sprintf('Cannot build URL for page %d (lang %d): %s', $pageUid, $languageId, $e->getMessage()),
                0,
                $e
            );
        }
    }

    public function expectedWebsiteTitle(int $pageUid): string
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
            return (string)($site->getConfiguration()['websiteTitle'] ?? '');
        } catch (\Throwable) {
            return '';
        }
    }
}
```

- [ ] **Step 4: Update controller to accept and use `UrlResolver`**

In `EnhancelyStatusController`, change the constructor:

```php
public function __construct(
    private readonly ExtensionConfiguration $config,
    private readonly FrontendInterface $cache,
    private readonly SanityChecker $sanityChecker,
    private readonly UrlResolver $urlResolver,
) {}
```

Replace the trailing `return new ViewState(statusBadge: 'unknown');` with:

```php
try {
    $url = $this->urlResolver->resolve($pageUid, $languageId);
} catch (\RuntimeException $e) {
    return new ViewState(
        banner: ViewState::BANNER_SITE_ERROR,
        bannerDetail: $e->getMessage(),
    );
}

// Cache + live fetch — Task 11.
return new ViewState(statusBadge: 'unknown', url: $url);
```

- [ ] **Step 5: Run tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php
```

Expected: green (also the earlier banner tests still pass — they use mocked resolver).

- [ ] **Step 6: Commit**

```bash
git add Classes/Backend/InfoModule Tests/Unit/Backend/InfoModule
git commit -m "feat(backend): UrlResolver + site/URL error handling"
```

---

## Task 11: Controller — cache hit / miss + live fetch

**Files:**
- Modify: `Classes/Backend/InfoModule/EnhancelyStatusController.php`
- Modify: `Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php`

Inject a `JsonLdFetcher` collaborator (thin wrapper over `Client::jsonld`) so we can mock it.

- [ ] **Step 1: Write failing tests**

Append to `EnhancelyStatusControllerTest`:

```php
#[Test]
public function rendersFromCacheWhenMetaPresent(): void
{
    $cache = $this->createMock(FrontendInterface::class);
    $cache->method('get')->willReturn([
        'etag' => 'etag-abc',
        'jsonld' => '<script>...</script>',
        'meta' => [
            'crawled_at' => '2026-05-18T12:32:15Z',
            'status' => 'ready',
            'hash' => 'h1',
            'graph' => ['@graph' => [['@type' => 'WebPage', 'name' => 'X']]],
            'cached_at' => time() - 600,
        ],
    ]);

    $fetcher = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcher::class);
    $fetcher->expects(self::never())->method('fetch');

    $controller = new EnhancelyStatusController(
        $this->configMock(),
        $cache,
        new SanityChecker(),
        $this->urlResolverMock(),
        $fetcher,
    );

    $state = $controller->buildViewState(1, 0, 1, false);

    self::assertSame('ready', $state->statusBadge);
    self::assertSame('etag-abc', $state->etag);
    self::assertSame('h1', $state->hash);
    self::assertStringContainsString('cache', $state->source);
}

#[Test]
public function liveFetchOnCacheMissPopulatesCache(): void
{
    $cache = $this->createMock(FrontendInterface::class);
    $cache->method('get')->willReturn(false);

    $response = \Enhancely\Enhancely\Client\JsonLdResponse::fromApiResponse(200, [
        'jsonld' => ['@graph' => [['@type' => 'WebPage']]],
        'crawled_at' => '2026-05-18T12:32:15Z',
        'status' => 'ready',
        'hash' => 'h2',
    ], 'etag-new');

    $fetcher = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcher::class);
    $fetcher->expects(self::once())
        ->method('fetch')
        ->with('https://example.com/', false)
        ->willReturn($response);

    $cache->expects(self::once())->method('set');

    $controller = new EnhancelyStatusController(
        $this->configMock(),
        $cache,
        new SanityChecker(),
        $this->urlResolverMock(),
        $fetcher,
    );

    $state = $controller->buildViewState(1, 0, 1, false);

    self::assertSame('ready', $state->statusBadge);
    self::assertSame('h2', $state->hash);
    self::assertStringContainsString('live', $state->source);
}

private function urlResolverMock(string $url = 'https://example.com/', string $title = 'Example Site'): \Enhancely\Enhancely\Backend\InfoModule\UrlResolver
{
    $m = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\UrlResolver::class);
    $m->method('resolve')->willReturn($url);
    $m->method('expectedWebsiteTitle')->willReturn($title);
    return $m;
}
```

Update `controllerWith()` to accept a fetcher (default mock returns an error response — unused unless cache miss).

- [ ] **Step 2: Run failing tests**

```bash
vendor/bin/phpunit Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php
```

Expected: fail — `JsonLdFetcher` undefined.

- [ ] **Step 3: Implement `JsonLdFetcher`**

Create `Classes/Backend/InfoModule/JsonLdFetcher.php`:

```php
<?php

declare(strict_types=1);

namespace Enhancely\Enhancely\Backend\InfoModule;

use Enhancely\Enhancely\Client\Client;
use Enhancely\Enhancely\Client\JsonLdResponse;
use Enhancely\Enhancely\Configuration\ExtensionConfiguration;

final class JsonLdFetcher
{
    public function __construct(
        private readonly ExtensionConfiguration $config,
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
```

- [ ] **Step 4: Update controller**

Add to constructor parameters: `private readonly JsonLdFetcher $fetcher`.

After the `urlResolver->resolve(...)` block, replace the placeholder return with:

```php
$cacheId = $this->cacheIdentifier($url);
$cached = $this->cache->get($cacheId);

if (is_array($cached) && isset($cached['meta']) && !$forceRefresh) {
    return $this->stateFromCachedMeta($url, $cached);
}

$response = $this->fetcher->fetch($url, $forceRefresh);

if ($response->ready()) {
    \Enhancely\Enhancely\Middleware\JsonLdMiddleware::writeCachePayload(
        $this->cache,
        $cacheId,
        $response,
        $this->config->getCacheLifetime()
    );
    return $this->stateFromLiveResponse($url, $response);
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
```

Add three helpers at the bottom of the class:

```php
private function cacheIdentifier(string $url): string
{
    return hash('sha256', $url);
}

private function stateFromCachedMeta(string $url, array $cached): ViewState
{
    $meta = $cached['meta'];
    $graph = (array)($meta['graph'] ?? []);
    $expectedTitle = $this->urlResolver->expectedWebsiteTitle(0); // pageUid not needed; resolved already
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

private function stateFromLiveResponse(string $url, \Enhancely\Enhancely\Client\JsonLdResponse $response): ViewState
{
    $graph = (array)$response->jsonld();
    $apiMeta = [
        'crawled_at' => $response->crawledAt(),
        'status' => $response->apiStatus(),
        'hash' => $response->hash(),
    ];
    $expectedTitle = $this->urlResolver->expectedWebsiteTitle(0);
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
```

(Note: `expectedWebsiteTitle(0)` is a code smell — the resolver should remember the page it just resolved. Refactor in Task 12 if it shows up in code review; leaving it for now keeps the diff small. Mark as a TODO **only if** the reviewer flags it — do NOT add TODO comments in code.)

Actually fix it now to avoid the smell: pass `$pageUid` through to the helpers, drop the (0) call.

Replace `$this->urlResolver->expectedWebsiteTitle(0)` with `$expectedTitle` and add a `string $expectedTitle` parameter to both `stateFromCachedMeta` and `stateFromLiveResponse`. Pass `$this->urlResolver->expectedWebsiteTitle($pageUid)` once at the call site.

- [ ] **Step 5: Run tests**

```bash
vendor/bin/phpunit
```

Expected: green across the suite.

- [ ] **Step 6: Commit**

```bash
git add Classes/Backend/InfoModule Tests/Unit/Backend/InfoModule
git commit -m "feat(backend): controller cache hit / miss + live fetch flow"
```

---

## Task 12: Controller — Refresh invalidates cache

**Files:**
- Modify: `Classes/Backend/InfoModule/EnhancelyStatusController.php`
- Modify: `Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php`

- [ ] **Step 1: Write failing test**

Append:

```php
#[Test]
public function forceRefreshSkipsCacheAndFetches(): void
{
    $cache = $this->createMock(FrontendInterface::class);
    // Cache has data but forceRefresh should bypass it.
    $cache->expects(self::once())->method('remove')->with(self::isType('string'));

    $response = \Enhancely\Enhancely\Client\JsonLdResponse::fromApiResponse(200, [
        'jsonld' => ['@graph' => []],
        'status' => 'ready',
    ], 'etag-fresh');

    $fetcher = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcher::class);
    $fetcher->expects(self::once())->method('fetch')->with(self::anything(), true)->willReturn($response);

    $controller = new EnhancelyStatusController(
        $this->configMock(),
        $cache,
        new SanityChecker(),
        $this->urlResolverMock(),
        $fetcher,
    );

    $controller->buildViewState(1, 0, 1, forceRefresh: true);
}
```

- [ ] **Step 2: Run failing test**

Expected: fail (controller doesn't call `remove`).

- [ ] **Step 3: Implement**

In the controller, at the top of the cache-handling block, before the `$cache->get(...)` call:

```php
if ($forceRefresh) {
    $this->cache->remove($cacheId);
}
```

Also guard the cache-hit branch with `&& !$forceRefresh` (already in place — keep it).

- [ ] **Step 4: Run tests**

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add Classes/Backend/InfoModule Tests/Unit/Backend/InfoModule
git commit -m "feat(backend): force-refresh invalidates shared cache entry"
```

---

## Task 13: Legacy cache entries (no `meta`) treated as miss

**Files:**
- Modify: `Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php`

The controller already only follows the cache-hit branch when `isset($cached['meta'])`. Add a regression test.

- [ ] **Step 1: Write failing test**

Append:

```php
#[Test]
public function legacyCacheEntryWithoutMetaTriggersLiveFetch(): void
{
    $cache = $this->createMock(FrontendInterface::class);
    $cache->method('get')->willReturn([
        'etag' => 'etag-old',
        'jsonld' => '<script>...</script>',
        // no 'meta' key — written by < 1.3.0
    ]);

    $response = \Enhancely\Enhancely\Client\JsonLdResponse::fromApiResponse(200, [
        'jsonld' => ['@graph' => []],
        'status' => 'ready',
    ], 'etag-new');

    $fetcher = $this->createMock(\Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcher::class);
    $fetcher->expects(self::once())->method('fetch')->willReturn($response);

    $controller = new EnhancelyStatusController(
        $this->configMock(),
        $cache,
        new SanityChecker(),
        $this->urlResolverMock(),
        $fetcher,
    );

    $state = $controller->buildViewState(1, 0, 1, false);

    self::assertStringContainsString('live', $state->source);
}
```

- [ ] **Step 2: Run test**

Expected: green (regression test confirms existing behaviour). If it fails, the cache-hit guard is missing `isset($cached['meta'])` — add it.

- [ ] **Step 3: Commit**

```bash
git add Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest.php
git commit -m "test(backend): legacy cache entries trigger live fetch"
```

---

## Task 14: Fluid templates, CSS, locallang

**Files:**
- Create: `Resources/Private/Templates/Backend/InfoModule/Show.html`
- Create: `Resources/Private/Partials/Backend/Status.html`
- Create: `Resources/Public/Css/backend.css`
- Create: `Resources/Private/Language/locallang_mod.xlf`

No new tests here — templates are exercised by the manual smoke test (Task 16). Keep templates minimal and lean on existing TYPO3 BE styles where possible.

- [ ] **Step 1: Create `locallang_mod.xlf`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.0">
  <file source-language="en" datatype="plaintext" original="messages" date="2026-05-18T00:00:00Z" product-name="enhancely">
    <header/>
    <body>
      <trans-unit id="mlang_tabs_tab"><source>Enhancely JSON-LD</source></trans-unit>
      <trans-unit id="banner.not_configured"><source>Extension is not configured. Set the API key in Admin Tools › Settings › Extension Configuration › enhancely.</source></trans-unit>
      <trans-unit id="banner.disabled"><source>Extension is disabled. Enable it in Extension Configuration.</source></trans-unit>
      <trans-unit id="banner.site_error"><source>Cannot resolve the public URL for this page:</source></trans-unit>
      <trans-unit id="badge.ready"><source>ready</source></trans-unit>
      <trans-unit id="badge.processing"><source>processing</source></trans-unit>
      <trans-unit id="badge.error"><source>error</source></trans-unit>
      <trans-unit id="badge.skipped"><source>skipped</source></trans-unit>
      <trans-unit id="meta.crawled"><source>Crawled</source></trans-unit>
      <trans-unit id="meta.etag"><source>ETag</source></trans-unit>
      <trans-unit id="meta.hash"><source>Hash</source></trans-unit>
      <trans-unit id="meta.source"><source>Source</source></trans-unit>
      <trans-unit id="meta.graphtypes"><source>Graph nodes</source></trans-unit>
      <trans-unit id="sanity.heading"><source>Sanity checks</source></trans-unit>
      <trans-unit id="raw.heading"><source>Raw JSON-LD</source></trans-unit>
      <trans-unit id="refresh"><source>Refresh</source></trans-unit>
    </body>
  </file>
</xliff>
```

- [ ] **Step 2: Create `Status.html` partial**

```html
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
<f:switch expression="{state.statusBadge}">
  <f:case value="ready"><span class="enhancely-badge enhancely-badge--ready">● <f:translate key="badge.ready"/></span></f:case>
  <f:case value="processing"><span class="enhancely-badge enhancely-badge--processing">● <f:translate key="badge.processing"/></span></f:case>
  <f:case value="error"><span class="enhancely-badge enhancely-badge--error">● <f:translate key="badge.error"/></span></f:case>
  <f:case value="skipped"><span class="enhancely-badge enhancely-badge--skipped">● <f:translate key="badge.skipped"/></span></f:case>
  <f:defaultCase><span class="enhancely-badge enhancely-badge--unknown">●</span></f:defaultCase>
</f:switch>
</html>
```

- [ ] **Step 3: Create `Show.html` template**

```html
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers" data-namespace-typo3-fluid="true">
<f:layout name="Module"/>
<f:section name="Content">

<link rel="stylesheet" href="{f:uri.resource(path: 'Css/backend.css')}"/>

<f:if condition="{state.banner}">
  <f:then>
    <div class="enhancely-banner enhancely-banner--{state.banner}">
      <f:translate key="banner.{state.banner}"/> {state.bannerDetail}
    </div>
  </f:then>
  <f:else>
    <div class="enhancely-statusrow">
      <f:render partial="Backend/Status" arguments="{state: state}"/>
      <code class="enhancely-url">{state.url}</code>
      <form method="post" class="enhancely-refresh">
        <input type="hidden" name="forceRefresh" value="1"/>
        <button type="submit"><f:translate key="refresh"/></button>
      </form>
    </div>

    <f:if condition="{state.crawledAt}">
      <table class="enhancely-meta">
        <tr><td><f:translate key="meta.crawled"/></td><td>{state.crawledAt}</td></tr>
        <tr><td><f:translate key="meta.etag"/></td><td><code>{state.etag}</code></td></tr>
        <tr><td><f:translate key="meta.hash"/></td><td><code>{state.hash}</code></td></tr>
        <tr><td><f:translate key="meta.source"/></td><td>{state.source}</td></tr>
        <tr><td><f:translate key="meta.graphtypes"/></td><td><f:for each="{state.graphTypes}" as="t" iteration="i">{t}<f:if condition="{i.isLast} == 0">, </f:if></f:for></td></tr>
      </table>
    </f:if>

    <f:if condition="{state.sanityChecks}">
      <h3><f:translate key="sanity.heading"/></h3>
      <ul class="enhancely-sanity">
        <f:for each="{state.sanityChecks}" as="check">
          <li class="enhancely-sanity__row enhancely-sanity__row--{check.level}">
            <strong><f:if condition="{check.level} == 'pass'"><f:then>✓</f:then><f:else>⚠</f:else></f:if></strong>
            {check.message}
          </li>
        </f:for>
      </ul>
    </f:if>

    <f:if condition="{state.rawJsonLd}">
      <h3><f:translate key="raw.heading"/></h3>
      <pre class="enhancely-raw">{state.rawJsonLdPretty -> f:format.htmlspecialchars()}</pre>
    </f:if>

    <f:if condition="{state.errorMessage}">
      <div class="enhancely-error">{state.errorMessage}</div>
    </f:if>
  </f:else>
</f:if>

</f:section>
</html>
```

- [ ] **Step 4: Add `rawJsonLdPretty` to `ViewState`**

The template references `{state.rawJsonLdPretty}` — the pretty-printed string. In `ViewState`, add an accessor method (cheaper than computing it ahead of time):

```php
public function getRawJsonLdPretty(): string
{
    if ($this->rawJsonLd === null) {
        return '';
    }
    return (string)json_encode($this->rawJsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
```

Fluid will resolve `{state.rawJsonLdPretty}` via Fluid's getter conventions.

- [ ] **Step 5: Create `backend.css`**

```css
.enhancely-banner { padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; }
.enhancely-banner--not_configured,
.enhancely-banner--disabled { background: #fff4d6; border: 1px solid #f0d27a; }
.enhancely-banner--site_error { background: #fbe3e3; border: 1px solid #e7a0a0; }

.enhancely-statusrow { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.enhancely-statusrow .enhancely-refresh { margin-left: auto; }
.enhancely-url { font-size: 12px; color: #555; }

.enhancely-badge { padding: 3px 8px; border-radius: 3px; font-weight: 600; font-size: 12px; }
.enhancely-badge--ready { background: #e6f7ee; color: #0a7; border: 1px solid #b6e3c8; }
.enhancely-badge--processing { background: #fff4d6; color: #8a6d1a; border: 1px solid #f0d27a; }
.enhancely-badge--error { background: #fbe3e3; color: #a33; border: 1px solid #e7a0a0; }
.enhancely-badge--skipped { background: #eef0f2; color: #555; border: 1px solid #ccd2d8; }
.enhancely-badge--unknown { background: #eef0f2; color: #888; border: 1px solid #ccd2d8; }

.enhancely-meta { font-size: 12px; border-collapse: collapse; margin-bottom: 12px; }
.enhancely-meta td { padding: 3px 12px 3px 0; vertical-align: top; }
.enhancely-meta td:first-child { color: #666; width: 120px; }

.enhancely-sanity { list-style: none; padding: 0; margin: 0 0 12px 0; }
.enhancely-sanity__row { padding: 6px 10px; border-radius: 3px; margin-bottom: 4px; font-size: 12px; }
.enhancely-sanity__row--pass { background: #e6f7ee; border: 1px solid #b6e3c8; color: #0a7; }
.enhancely-sanity__row--warn { background: #fff4d6; border: 1px solid #f0d27a; color: #8a6d1a; }

.enhancely-raw { background: #1e1e1e; color: #d4d4d4; padding: 10px; border-radius: 3px; font-size: 11px; line-height: 1.4; overflow: auto; max-height: 320px; }

.enhancely-error { background: #fbe3e3; border: 1px solid #e7a0a0; padding: 8px 12px; border-radius: 4px; color: #a33; }
```

- [ ] **Step 6: Run tests (no template tests yet)**

```bash
vendor/bin/phpunit
```

Expected: green (templates not exercised, just stored).

- [ ] **Step 7: Commit**

```bash
git add Resources/ Classes/Backend/InfoModule/ViewState.php
git commit -m "feat(backend): Fluid templates, CSS, locallang for status tab"
```

---

## Task 15: Module registration + Services.yaml + module entry point

**Files:**
- Create: `Configuration/Backend/Modules.php`
- Modify: `Configuration/Services.yaml`

The controller needs an `__invoke(ServerRequestInterface): ResponseInterface` for the module dispatcher. We add that as the entry point; it calls `buildViewState()` and renders Fluid.

- [ ] **Step 1: Add `__invoke` to the controller**

In `EnhancelyStatusController`, add at the top of the class:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Fluid\View\StandaloneView;
```

Add a new constructor argument: `private readonly ModuleTemplateFactory $moduleTemplateFactory`.

Add the entry point:

```php
public function __invoke(ServerRequestInterface $request): ResponseInterface
{
    $params = $request->getQueryParams() + $request->getParsedBody();
    $pageUid = (int)($params['id'] ?? 0);
    $languageId = (int)($params['language'] ?? 0);
    $forceRefresh = !empty($params['forceRefresh']);

    // Resolve doktype from the page record.
    $doktype = $this->resolveDoktype($pageUid);

    $state = $this->buildViewState($pageUid, $languageId, $doktype, $forceRefresh);

    $view = GeneralUtility::makeInstance(StandaloneView::class);
    $view->setTemplateRootPaths([
        'EXT:enhancely/Resources/Private/Templates/',
    ]);
    $view->setPartialRootPaths([
        'EXT:enhancely/Resources/Private/Partials/',
    ]);
    $view->setTemplate('Backend/InfoModule/Show');
    $view->assign('state', $state);

    $moduleTemplate = $this->moduleTemplateFactory->create($request);
    $moduleTemplate->setContent($view->render());

    return new HtmlResponse($moduleTemplate->renderContent());
}

private function resolveDoktype(int $pageUid): int
{
    if ($pageUid <= 0) {
        return 0;
    }
    $row = BackendUtility::getRecord('pages', $pageUid, 'doktype');
    return (int)($row['doktype'] ?? 0);
}
```

(Add `use TYPO3\CMS\Backend\Utility\BackendUtility;` and `use TYPO3\CMS\Core\Utility\GeneralUtility;` at the top.)

- [ ] **Step 2: Register the sub-module**

Create `Configuration/Backend/Modules.php`:

```php
<?php

declare(strict_types=1);

use Enhancely\Enhancely\Backend\InfoModule\EnhancelyStatusController;

return [
    'web_info_enhancely' => [
        'parent' => 'web_info',
        'access' => 'user',
        'path' => '/module/web/info/enhancely',
        'iconIdentifier' => 'module-enhancely',
        'labels' => 'LLL:EXT:enhancely/Resources/Private/Language/locallang_mod.xlf',
        'extensionName' => 'Enhancely',
        'routes' => [
            '_default' => [
                'target' => EnhancelyStatusController::class . '::__invoke',
            ],
        ],
    ],
];
```

- [ ] **Step 3: Make controller publicly addressable in DI**

Append to `Configuration/Services.yaml`:

```yaml
  Enhancely\Enhancely\Backend\InfoModule\EnhancelyStatusController:
    public: true
    arguments:
      $cache: '@cache.enhancely_etag'

  Enhancely\Enhancely\Backend\InfoModule\JsonLdFetcher:
    public: true

  Enhancely\Enhancely\Backend\InfoModule\UrlResolver:
    public: true
```

- [ ] **Step 4: Register the module icon**

In `ext_localconf.php`, append:

```php
$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \TYPO3\CMS\Core\Imaging\IconRegistry::class
);
$iconRegistry->registerIcon(
    'module-enhancely',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:enhancely/Resources/Public/Icons/Extension.svg']
);
```

- [ ] **Step 5: Run unit tests**

```bash
vendor/bin/phpunit
```

Expected: green (no new unit tests, but existing must still pass — the constructor signature changed, so update test helpers if they break).

- [ ] **Step 6: Commit**

```bash
git add Configuration/ Classes/Backend/InfoModule/EnhancelyStatusController.php ext_localconf.php
git commit -m "feat(backend): register web_info_enhancely sub-module + DI wiring"
```

---

## Task 16: Manual smoke test in a TYPO3 instance

**Files:** (no files changed in this task; pure verification)

- [ ] **Step 1: Install extension into the camino-demo VM**

This is the same TYPO3 instance used during spec authoring. From the host:

```bash
ssh root@typo3-camino-u1128.vm.elestio.app
cd /opt/app/typo3-demo
# Bind-mount the local extension into the container.
# Easiest: bump the version in composer.json of a test project and re-run
# composer require, OR drop the extension into typo3conf/ext for the test.
```

For the smoke test, the simplest path is rsync-ing the working tree into `typo3_var/ext/enhancely` and adding a bind mount to docker-compose; record exact commands in the README (Task 17).

- [ ] **Step 2: Walk through each path manually**

Verify in the BE:

1. **ready** path: open Web › Info, pick "Enhancely JSON-LD" for a page known to be crawled. Badge green, meta table filled, sanity checks rendered, raw JSON-LD visible.
2. **refresh**: click Refresh; observe a fresh `Source: live (fresh)` line and an updated `cached_at`.
3. **doktype excluded**: set excluded page types in Extension Configuration to include the page's doktype, reload tab. Expect gray "skipped" badge.
4. **disabled**: toggle `enabled = 0` in Extension Configuration, reload. Expect amber disabled banner.
5. **no api key**: blank the API key, reload. Expect amber not-configured banner.
6. **error**: temporarily set `apiBaseUrl` to an unreachable host. Expect red error badge + message + Refresh button still works.
7. **legacy cache entry**: with the FE middleware writing `meta` now, this path is only reachable on upgrade — skip unless you can manually plant a v1.2.3-style cache entry.

- [ ] **Step 3: Capture screenshot for README**

Save a screenshot of the ready-state view to `Resources/Public/Documentation/backend-screenshot.png`.

- [ ] **Step 4: Commit screenshot**

```bash
git add Resources/Public/Documentation/backend-screenshot.png
git commit -m "docs: add screenshot of backend status tab"
```

---

## Task 17: Version bump + README + RELEASE.md

**Files:**
- Modify: `ext_emconf.php`
- Modify: `README.md`
- Modify: `RELEASE.md`

- [ ] **Step 1: Bump version**

In `ext_emconf.php`, change `'version' => '1.2.3'` to `'version' => '1.3.0'`.

- [ ] **Step 2: Add README section**

After the existing "Features" section in `README.md`, insert:

```markdown
## Backend integration

The extension ships a read-only Info-module tab that shows the Enhancely status for the currently selected page.

1. In the TYPO3 backend, open **Web › Info**.
2. Pick **Enhancely JSON-LD** from the function dropdown.
3. The tab shows:
   - Current Enhancely status (ready / processing / error / skipped)
   - Last crawl timestamp, ETag, hash
   - Sanity checks (BreadcrumbList absolute, title mismatch, crawl freshness, payload size)
   - The raw JSON-LD payload

A **Refresh** button re-fetches from Enhancely and invalidates the shared cache for that URL. The tab does not trigger a server-side re-crawl on Enhancely — that endpoint is not exposed to customers.

![Backend status tab](Resources/Public/Documentation/backend-screenshot.png)

### Manual smoke test (for contributors)

1. Install into a TYPO3 instance with the extension's API key configured.
2. Open a page in the BE with known Enhancely data → expect green "ready" badge.
3. Press Refresh → expect `Source: live (fresh)` and an updated cached_at line.
4. Set an excluded doktype matching the page → expect gray "skipped".
5. Blank the API key → expect amber "not configured" banner.
```

- [ ] **Step 3: Add RELEASE.md entry**

Prepend a new section to `RELEASE.md` matching the existing format:

```markdown
## 1.3.0 — 2026-05-18

### Added
- Backend Info-module tab "Enhancely JSON-LD" showing per-page status, sanity checks, and raw payload.
- `JsonLdResponse::crawledAt()`, `apiStatus()`, `hash()` accessors.
- `Enhancely\Backend\SanityCheck\SanityChecker` with four checks: BreadcrumbList absolute, title mismatch, crawl freshness, payload size.

### Changed
- The `enhancely_etag` cache payload now carries an additional `meta` block (crawled_at, status, hash, parsed graph, cached_at). The existing `etag` and `jsonld` keys are unchanged. Old cache entries without `meta` remain readable by the FE middleware and are treated as a cache miss by the BE tab.

### Compatibility
- TYPO3 12.4 – 14.99, PHP 8.2+ (unchanged).
```

- [ ] **Step 4: Run the full test suite one last time**

```bash
vendor/bin/phpunit
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
git add ext_emconf.php README.md RELEASE.md
git commit -m "chore: bump version to 1.3.0"
```

- [ ] **Step 6: Tag**

```bash
git tag -a v1.3.0 -m "Release 1.3.0 — Backend JSON-LD status tab"
```

- [ ] **Step 7: Open PR**

```bash
gh pr create --title "feat: backend JSON-LD status tab (v1.3.0)" --body "$(cat <<'EOF'
## Summary

- Adds an Info-module sub-module under Web › Info that displays Enhancely status for the selected page (status badge, crawl timestamp, ETag, hash, source, graph node summary).
- Adds four sanity checks: BreadcrumbList items absolute, title mismatch vs site websiteTitle, crawl freshness (>7d warn), JSON-LD payload size (>80% of 1 MiB cap warn).
- Adds a Refresh button that flushes the shared cache entry and re-fetches.
- Extends the `enhancely_etag` cache payload with a backwards-compatible `meta` block. The FE middleware is unaffected; old entries are treated as a cache miss by the BE.

## Test plan
- [ ] `vendor/bin/phpunit` is green
- [ ] Manual smoke (see README › Backend integration › Manual smoke test)
- [ ] Screenshot updated

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

(The TER auto-publish workflow picks up the `v1.3.0` tag once the PR is merged and the tag is pushed.)

---

## Self-review

**Spec coverage:**
- Architecture / components: Tasks 4-15 ✓
- Cache extension (`meta` block, backwards compatible): Task 3 ✓
- `JsonLdResponse` accessors: Task 2 ✓
- All four sanity checks: Tasks 5-8 ✓
- All controller paths (no API key / disabled / excluded / site error / cache hit / cache miss / processing / error / 401 / 429 / refresh / legacy entry): Tasks 9-13 ✓ — note that 401 and 429 specifically are surfaced via the generic `error` path with `$response->error()` providing the message (the `Client` already maps them in `HttpClient::postJsonLd`). No separate task; reuse existing wiring.
- Fluid templates + CSS + locallang: Task 14 ✓
- Module registration: Task 15 ✓
- Tests for SanityChecker (100% target) and Controller (~80% target): Tasks 5-13 ✓
- README + RELEASE + version bump: Task 17 ✓
- Manual smoke test: Task 16 ✓

**Placeholder scan:** No TBDs. The TODO-mention in Task 11 step 4 about `expectedWebsiteTitle(0)` is resolved in the same step.

**Type consistency:**
- `SanityChecker::check(array, array, string): array<CheckResult>` — used consistently in Tasks 5-13.
- `ViewState` constructor signature — referenced consistently in tests and controller.
- `JsonLdFetcher::fetch(string, bool): JsonLdResponse` — used consistently.

**Ambiguity:** None remaining.
