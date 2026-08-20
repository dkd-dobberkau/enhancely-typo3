# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Result of the automated security audit (Redmine #249782).

### Security

- The Info module now checks page-read permission **before** it does any work.
  Previously the module resolved the URL, purged the JSON-LD cache entry shared
  with the frontend middleware and issued a billed Enhancely API call for any
  page UID passed in `id`, and only afterwards called `readPageAccess()` — whose
  result was used for the doc header alone. Any backend user with the module
  enabled could therefore purge cache entries and burn API quota for pages
  outside their mounts by iterating `id`, with `forceRefresh` accepted from GET.
  The check now uses the user's real SHOW permissions
  (`getPagePermsClause(Permission::PAGE_SHOW)`) instead of the literal `1=1`,
  and pages without access render an "access denied" banner.

### Changed

- The page doktype is read from the record `readPageAccess()` already selected
  instead of a second unguarded `BackendUtility::getRecord()` query.
- `BackendUtility::readPageAccess()` moved behind `PageAccessCheckerInterface`,
  so the controller entry point is unit-testable.

## [1.5.0] - 2026-08-10

Result of the internal dkd code review (Redmine #245854).

### Fixed

- **Backend module could never see frontend cache entries.** The middleware and
  the info module derived cache identifiers independently — a prefixed MD5 of
  the normalized URL versus a raw SHA-256 of the raw URL — while writing into the
  same cache. The module therefore reported a cache miss for pages the frontend
  had just cached, and every module visit issued a second API call for a URL that
  was already stored. Both sides now go through `Cache\JsonLdCache`.
- **Backend module rendered with a stale layout on TYPO3 v14.** The extension
  shipped `Resources/Private/Layouts/Module.fluid.html`, an outdated copy of the
  core layout. Fluid 5 resolves `*.fluid.html` before `*.html`, so on v14 this
  copy shadowed `EXT:backend`'s current layout and dropped the module body
  container, form tag handling and UI-block spinner. The file is removed; the
  core layout applies again. Per Fluid changelog #108166 the `*.fluid.html`
  extension must not be used by extensions supporting TYPO3 below v14.
- **Proxy and timeout settings were ignored.** `HttpClient` built its own Guzzle
  client, so `$GLOBALS['TYPO3_CONF_VARS']['HTTP']` did not apply — on
  installations reaching the internet through a proxy, every API call failed and
  cost a connect timeout per page render. Requests now go through TYPO3's
  `RequestFactory`.

### Added

- Extension configuration option `timeout` — per-request timeout in seconds.
  `0` (default) leaves TYPO3's global HTTP timeout in charge; the previous 10 s
  was hardcoded and not configurable.
- `data-source="Enhancely.ai"` on the injected `<script>` tag, matching the
  official `enhancely/enhancely-php` library. `data-status` and `data-etag` are
  deliberately omitted: the library interpolates them into HTML attributes
  unescaped.
- GitHub Actions workflow running the unit tests across PHP 8.2–8.4 and
  TYPO3 v13 + v14, plus a coverage report. Tests previously ran nowhere in CI.
- End-to-end tests for the injection path (`JsonLdInjectionTest`) covering
  placement in `<head>`, unreachable API, in-flight crawls, ETag reuse and
  query-string variants sharing one cache entry.

### Changed

- Backend messages ("API key not configured", "Doktype %s is excluded", site
  resolution errors) are language labels in `locallang_mod.xlf` instead of
  hardcoded English. `ViewState` carries `bannerDetailKey` and
  `bannerDetailArguments` instead of a rendered `bannerDetail` string.
- `JsonLdFetcher` logs the original exception before downgrading it to a
  message-only `JsonLdResponse`, so the cause is no longer lost.
- TYPO3 coding guidelines: copyright notice added to all PHP files.

### Removed

- **BC break:** the static `Client` facade (`Client::setApiKey()`,
  `Client::jsonld()`, …). It held global mutable state and was only used
  internally by the backend module, which now uses the request-scoped
  `HttpClientFactory`. Integrators calling `Client` directly should switch to
  `HttpClientFactory::create()->postJsonLd()`.
- **BC break:** `UrlResolverInterface::expectedWebsiteTitle()` — moved to the new
  `SiteTitleProviderInterface`, since resolving a URL and reading a site title
  are unrelated responsibilities.
- **BC break:** the unused `$forceRefresh` parameter of
  `JsonLdFetcherInterface::fetch()`. It never had an effect; cache bypassing is
  handled by the caller clearing its entry.
- **BC break:** `JsonLdMiddleware::writeCachePayload()` — the backend module
  called this static method on a frontend middleware. Replaced by
  `JsonLdCache::write()`.

### Known limitations

- The API is still called synchronously in the frontend render path, and the
  ETag is revalidated on every request even for cached pages. Decoupling this
  (asynchronous fetch in the backend plus a change trigger) and adding an
  editorial approval step before AI content goes live are open design questions,
  not defects — see Redmine #245854.
- `SanityChecker` matches `@type` on the decoded graph without JSON-LD
  expansion, so documents using non-default `@context` aliases are not
  recognized.

## [1.4.7] and earlier

See `RELEASE.md` and the Git history.
