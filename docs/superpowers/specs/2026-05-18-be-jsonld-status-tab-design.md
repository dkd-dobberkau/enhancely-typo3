# Backend JSON-LD status tab — design

**Date:** 2026-05-18
**Extension:** `enhancely/enhancely-for-typo3`
**Target version:** 1.3.0

## Goal

Editors using the TYPO3 backend need a way to see what Enhancely thinks the
current page's JSON-LD looks like — without leaving the backend, without
shelling into the server, and without calling Enhancely's API by hand. The
existing extension is frontend-only: its middleware fetches JSON-LD and
injects it into the rendered page, and there is no backend surface.

This spec adds a read-only **Info module function** that displays:

1. The current Enhancely status for the page (ready / processing / error /
   skipped).
2. Metadata about the most recent crawl (timestamp, ETag, hash, cache
   freshness, graph node summary).
3. A small set of sanity checks against the returned JSON-LD graph.
4. The raw JSON-LD payload.

A **Refresh** button forces a live fetch. The tab is otherwise passive — no
re-crawl trigger is offered, because the Enhancely API does not expose one
to customers (the `DELETE /jsonld/{hash}` endpoint exists but requires
`X-ADMIN-KEY` auth and IP allowlisting).

## Non-goals

- Active re-crawl trigger against Enhancely.
- Persisted history of past crawls / drift over time.
- Backend notifications when sanity checks fail.
- Multi-language switcher inside the tab (the Info module already provides
  one and we follow it).
- TYPO3 v11 support.

## Architecture

One new logical unit — the **Info module function** — composed of:

| Component | Path | Responsibility |
|---|---|---|
| `EnhancelyStatusController` | `Classes/Backend/InfoModule/EnhancelyStatusController.php` | Entry point for the Info function. Resolves page → URL, reads cache or calls Client, renders Fluid template. |
| `SanityChecker` | `Classes/Backend/SanityCheck/SanityChecker.php` | Pure logic: takes a JSON-LD graph + page metadata, returns a list of check results. |
| `Show.html` (Fluid) | `Resources/Private/Templates/Backend/InfoModule/Show.html` | Renders the tab. |
| Middleware patch | `Classes/Middleware/JsonLdMiddleware.php` | One-line change: also write the `meta` block when storing into the `enhancely_etag` cache. FE read path unchanged. |
| `Status.html` (Fluid partial) | `Resources/Private/Partials/Backend/Status.html` | Status-badge fragment, shared across states. |
| `backend.css` | `Resources/Public/Css/backend.css` | Minimal styling for the badge, sanity-check rows, and the raw-JSON-LD pane. |
| `locallang_mod.xlf` | `Resources/Private/Language/locallang_mod.xlf` | New strings for the BE module (kept separate from FE `locallang.xlf`). |
| Registration | `Configuration/Backend/Modules.php` (or `ext_tables.php` shim for v12) | Registers the function in `web_info`. |

Reused without modification:

- `Client\Client::jsonld($url, $etag)` — existing static facade.
- `Configuration\ExtensionConfiguration` — API key, base URL, excluded
  doktypes, cache lifetime.

Reused **with a small backwards-compatible extension**:

- `enhancely_etag` cache. Today the FE middleware stores
  `['etag' => ..., 'jsonld' => '<script>...</script>']`. The BE tab needs
  more: `crawled_at`, `status`, `hash`, and the parsed JSON-LD graph (not
  the wrapped script tag) for sanity checks. We extend the payload to
  `['etag' => ..., 'jsonld' => '<script>...</script>', 'meta' => ['crawled_at' => ..., 'status' => ..., 'hash' => ..., 'graph' => [...], 'cached_at' => <unix_ts>]]`.
  The FE middleware continues reading `etag` and `jsonld` as today and is
  unaffected. The BE controller reads `meta`; if `meta` is missing (entry
  written by an older version), the controller treats it as a cache miss
  and does a live fetch.

## Data flow

On tab open for page `pageUid`, language `languageId`:

1. Controller reads `ExtensionConfiguration`.
   - If API key empty → render "not configured" banner; stop.
   - If extension disabled → render "disabled" banner; stop.
2. Controller resolves the public URL:
   - `SiteFinder::getSiteByPageId($pageUid)` → `Site::getRouter()->generateUri($pageUid, ['_language' => $languageId])`.
   - On failure → render "no site config" error; stop.
3. Controller checks excluded doktypes:
   - If page's doktype is in `excludedPageTypes` → status `skipped`; stop.
4. Controller reads the `enhancely_etag` cache by normalized URL key.
   - **Hit** → render from cache. Status row shows "source: local cache
     (hit), age N min". *Age* here is wall-clock time since the controller
     last wrote the entry (a small sidecar timestamp stored alongside the
     cached payload), not since Enhancely crawled — that is a separate
     line in the meta table.
   - **Miss** → `Client::jsonld($url)` synchronously (one HTTP call).
     - Success → store result + write-timestamp in cache, render. Status
       row shows "source: live fetch, fresh".
     - Error → render `error` state with `JsonLdResponse::getErrorMessage()`
       and a Refresh button.
5. `SanityChecker::check($jsonLd, $pageMeta)` runs against the loaded graph;
   results render as inline badges in the sanity-checks section.

The **Refresh** button POSTs back to the same controller with a
`forceRefresh=1` flag. The controller flushes the cache entry for this URL
(by tag or by single-key remove) and proceeds with step 4 as a guaranteed
miss. This invalidates the FE middleware's cache for the URL as well — that
is intentional: an editor pressing Refresh wants subsequent FE requests to
see the new data too.

## UI

The tab renders inside the Web › Info module. Sections, top to bottom:

1. **Status row** — colored badge (`ready` green, `processing` yellow,
   `error` red, `skipped` gray), the resolved public URL, a Refresh button
   right-aligned.
2. **Meta table** — small two-column table. Rows: Crawled (UTC timestamp +
   relative), ETag, Hash, Source (cache hit/miss + age), Graph nodes (list
   of `@type` values with count).
3. **Sanity checks** — stacked rows, one per check, green for pass and
   amber for warn. Initial check set:
   - **BreadcrumbList absolute** — every `item` value starts with `http://`
     or `https://`.
   - **Title mismatch** — the site's configured `websiteTitle` (from
     `Site::getConfiguration()['websiteTitle']`) compared against
     `WebSite.name` and `Organization.name` in the graph. Surfaces stale
     crawls after a `websiteTitle` change. We deliberately do not try to
     reconstruct the rendered `<title>` tag here — that depends on the
     theme and is not deterministic from BE context.
   - **Crawl freshness** — `crawled_at` older than 7 days → warn.
   - **JSON-LD size** — within 80% of the HTTP client's 1 MiB cap → warn.
     Sizes over 1 MiB cannot reach us — the HTTP client throws
     `ApiException` and the status falls through to `error`, not a sanity
     warning.
4. **Raw JSON-LD** — collapsible dark code block with copy-to-clipboard
   button.

## Error handling

Every failure path falls into one of the four status badges plus a small
set of banners.

| Condition | Detection | UI |
|---|---|---|
| API key empty | `ExtensionConfiguration::getApiKey() === ''` | Info banner with link to Admin Tools › Settings › Extension Configuration. No API call. |
| Extension disabled | `enabled = 0` | Info banner "Extension is disabled — enable in Extension Configuration". No API call. |
| Doktype excluded | Page's `doktype` is in `excludedPageTypes` config | Status `skipped` (gray), one-line explanation. |
| Page has no site | `SiteFinder::getSiteByPageId()` throws | Red banner with the exception message. No API call. |
| URL generation fails | `Router::generateUri()` throws | Red banner with the exception message. No API call. |
| HTTP 201/202 | `JsonLdResponse::statusCode in [201,202]` | Status `processing`, meta table grayed out, hint "Try Refresh in a minute". |
| Network timeout / unreachable | `ApiException` → `JsonLdResponse::createError()` | Status `error`, problem-details message in status row, Retry button (= Refresh). |
| HTTP 401 | Caught explicitly in the Client | Status `error` with "Invalid API key" + link to Configuration. |
| HTTP 429 | Caught explicitly | Status `error` with "Rate limit exceeded — reset at \<time\>". |
| Cache backend unavailable | Exception thrown by cache read | Logged as warning; falls through to live fetch. |

All non-trivial events log to `TYPO3\CMS\Core\Log\LogManager` on channel
`Enhancely\Backend\InfoModule`. Stack traces stay in the log; the BE shows
human messages only.

## Testing

PHPUnit, in the existing `Tests/` tree.

| Test class | Coverage |
|---|---|
| `Tests/Unit/Backend/SanityCheck/SanityCheckerTest` | Fixtures per check: good/bad BreadcrumbList, title match/mismatch, fresh/old crawl, small/oversized graph. Target 100% line coverage — pure logic. |
| `Tests/Unit/Backend/InfoModule/EnhancelyStatusControllerTest` | Controller with mocked `Client`, `CacheManager`, `SiteFinder`, `ExtensionConfiguration`. Paths: cache-hit, cache-miss-live-success, cache-miss-live-error, refresh-invalidates-cache, no-api-key, doktype-excluded, site-not-found, URL-generation-fails. Target ~80% coverage. |
| `Tests/Unit/Backend/InfoModule/TemplateSmokeTest` | Render `Show.html` against a minimal variable set; assert no PHP errors and that key markers (`badge`, `meta-table`, `raw-jsonld`) are present. |

Functional tests are deliberately out of scope for v1.3.0 — TYPO3
functional-test bootstrapping is heavy and the marginal value is small for
a read-only display. Replaced by a documented manual smoke test in the
README.

## Release

- Version bump `1.2.3` → `1.3.0` in `ext_emconf.php` and `composer.json`.
- New entry in `RELEASE.md` following the existing format.
- README: add a "Backend integration" section with a screenshot of the
  Info tab and the manual smoke-test steps.
- Branch: `feature/be-info-jsonld-status` → PR against `main`.
- The existing TER auto-publish workflow handles publication on release
  tag.

## Compatibility

The extension currently supports TYPO3 12.4 – 14.99 and PHP 8.2+. This
feature must preserve that matrix:

- Module registration via `Configuration/Backend/Modules.php` works
  identically on v12, v13, v14.
- `SiteFinder`, `Router`, `CacheManager`, `ExtensionConfiguration` APIs
  used here are stable across the supported versions.
- Fluid templates use only core view helpers — no version-specific
  helpers.
