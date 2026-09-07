## 1.4.7 — 2026-05-18

### Added
- Copy-to-clipboard button below the raw JSON-LD viewer (uses the modern `navigator.clipboard` API with a `document.execCommand` fallback for older browsers). Visual confirmation via a temporary "Copied!" label.
- "Validate on schema.org" link button — opens `validator.schema.org` pre-filled with the page URL in a new tab.
- "Google Rich Results Test" link button — opens Google's Rich Results validator pre-filled with the page URL.

---

## 1.4.6 — 2026-05-18

### Changed
- "Refresh" button renamed to "Re-fetch from Enhancely" with an explanatory note below it: clearing the local TYPO3 cache and re-requesting JSON-LD does NOT trigger Enhancely to re-crawl the page server-side. Enhancely's stored data only refreshes on its own nightly crawl, or via Enhancely support. Sets correct expectations for editors.

---

## 1.4.5 — 2026-05-18

### Changed
- Backend module layout now matches the visual conventions of EXT:info (Page Information / Translation Status):
  - Added a `Resources/Private/Layouts/Module.fluid.html` so the template can `<f:layout name="Module"/>` and pick up the standard module-body chrome.
  - Replaced the custom banner/sanity-check styling with `<f:be.infobox>` so warnings and errors get the standard BE severity colors and icons.
  - Switched the meta panel to a Bootstrap `table table-striped`, the action button to `btn btn-default`, and status badges to standard `badge badge-*` classes.
  - Page title rendered as `<h1>` at the top of the body.
- Controller now sets the module title and page meta information on the doc header (`setTitle`, `setMetaInformation`, `makeDocHeaderModuleMenu`), so the BE chrome shows the page breadcrumb and shortcut context like the other Status sub-modules.
- Trimmed `Resources/Public/Css/backend.css` to just the dark `<pre>` styling for the raw JSON-LD viewer — everything else is now standard TYPO3 BE styling.

---

## 1.4.4 — 2026-05-18

### Fixed
- Module appears in the v14 backend sidebar. TYPO3 v14 renamed the legacy `web_info` module to `content_status`. The route alias `web_info` still resolves for direct URLs, but TYPO3 does NOT resolve the alias when used as a `parent` reference in `Configuration/Backend/Modules.php` — sub-modules with `parent: web_info` succeed at routing but never show up in the v14 sidebar. Switched to `Typo3Version`-aware parent selection: `content_status` on v14, `web_info` on v13.

---

## 1.4.3 — 2026-05-18

### Fixed
- Module sidebar label: TYPO3 v13/v14 reads `title` / `shortDescription` / `description` keys from the `labels` xlf path, not the legacy `mlang_tabs_tab`. Added the v13+ keys so the module shows as "Enhancely JSON-LD" in the BE sidebar instead of falling back to "Status".

---

## 1.4.2 — 2026-05-18

### Fixed
- Backend status template and Status partial: `f:translate` in a non-Extbase BE-module context throws unless an `extensionName` or full `LLL:` reference is given. Switched all `<f:translate key="…"/>` calls to full `LLL:EXT:enhancely/Resources/Private/Language/locallang_mod.xlf:…` syntax.

---

## 1.4.1 — 2026-05-18

### Fixed
- Backend status template: `f:uri.resource` requires an Extbase request or an explicit `EXT:` path in a BE-module context. Switched to `path="EXT:enhancely/Resources/Public/Css/backend.css"` so the stylesheet resolves correctly.

---

## 1.4.0 — 2026-05-18

### Breaking
- **Drops TYPO3 v12 support.** Minimum supported TYPO3 version is now 13.0. The backend status tab (introduced in 1.3.0) relies on `\TYPO3\CMS\Backend\Template\ModuleTemplate::renderResponse()` and the v13+ `BackendViewFactory`, neither of which exist in v12. Users on TYPO3 v12 must stay on 1.2.3.

### Fixed
- TYPO3 v14 compatibility: replaced `\TYPO3\CMS\Fluid\View\StandaloneView` usage in the backend controller (the class is removed in v14) with `ModuleTemplate::renderResponse()`. Removed the unused `<f:layout name="Module"/>` and `<f:section>` wrapper from `Show.html` — `ModuleTemplate` provides the BE chrome directly.

### Changed
- Promoted `typo3/cms-backend`, `typo3/cms-fluid`, `typo3/cms-frontend` from `require-dev` to `require` — they are runtime dependencies of the FE middleware and the BE module. Composer installs on a real TYPO3 site already provide them transitively, but declaring them explicitly is correct.

---

## 1.3.1 — 2026-05-18

### Fixed
- TYPO3 14 compatibility: replaced `IconRegistry::class` registration in `ext_localconf.php` (now throws a `RuntimeException (1729784545)` on TYPO3 14) with a `Configuration/Icons.php` provider, which is the supported mechanism on TYPO3 12, 13, and 14.

---

## 1.3.0 — 2026-05-18

### Added
- Backend Info-module tab "Enhancely JSON-LD" showing per-page status, sanity checks, and raw payload.
- `JsonLdResponse::crawledAt()`, `apiStatus()`, `hash()` accessors.
- `Enhancely\Enhancely\Backend\SanityCheck\SanityChecker` with four checks: BreadcrumbList absolute, title mismatch, crawl freshness, payload size.
- `Enhancely\Enhancely\Configuration\ExtensionConfigurationInterface` and `Enhancely\Enhancely\Backend\InfoModule\UrlResolverInterface` / `JsonLdFetcherInterface` to enable mocking of `final` classes in unit tests.

### Changed
- The `enhancely_etag` cache payload now carries an additional `meta` block (`crawled_at`, `status`, `hash`, parsed graph, `cached_at`). The existing `etag` and `jsonld` keys are unchanged. Cache entries written by older versions remain readable by the FE middleware and are treated as a miss by the BE tab.

### Dev
- Added `typo3/cms-backend`, `typo3/cms-fluid`, `dg/bypass-finals` to `require-dev` (test-only, no production deps changed).

### Compatibility
- TYPO3 12.4 – 14.99, PHP 8.2+ (unchanged).

---

# Release Process

This document describes how a new version of the **enhancely** TYPO3 extension
is published to:

1. [Packagist](https://packagist.org/packages/enhancely/enhancely-for-typo3)
   (Composer)
2. [extensions.typo3.org](https://extensions.typo3.org/package/enhancely/enhancely-for-typo3)
   (the TER listing, mirrored from Packagist)
3. [GitHub Releases](https://github.com/dkd-dobberkau/enhancely-typo3/releases)

## Distribution channels

Nothing has to be done per release beyond pushing the tag — all three channels
follow the git tag on their own.

### Packagist

Wired via GitHub service hook:
<https://packagist.org/packages/enhancely/enhancely-for-typo3>. New tags appear
within seconds.

### extensions.typo3.org (TER)

The extension is listed as a **Composer package**, not under a classic extension
key: <https://extensions.typo3.org/package/enhancely/enhancely-for-typo3>

TER mirrors Composer packages of type `typo3-cms-extension` from Packagist, and
the sync runs by itself — 1.5.0 showed up there roughly 50 minutes after its
Packagist release. No token, no upload step, no `typo3/tailor`.

The extension-key URL <https://extensions.typo3.org/extension/enhancely> returns
**404**, and that is expected: no classic key was ever registered, and none is
needed. Do not read that 404 as "the extension is missing from TER" — check the
`/package/` URL above instead.

A `.github/workflows/publish-ter.yml` used to upload via `typo3/tailor` with
`TYPO3_API_USERNAME` / `TYPO3_API_TOKEN`. It never actually ran — the secrets
were never set — and it was removed in September 2026, because that upload path
does not apply to a Composer-only package. It is in the git history should a
classic extension key ever be registered.

### GitHub Releases

Created by `release.sh`, see below.

## Releasing a new version

```bash
# Working tree must be clean and on main, with all changes pushed.
./release.sh 1.2.3
```

`release.sh` performs these steps:

1. Validates the semantic version argument.
2. Bumps `'version' => '...'` in `ext_emconf.php`.
3. Creates a `chore: Bump version to <X>` commit.
4. Creates an annotated git tag `<X>`.
5. Pushes the commit and the tag to `origin`.
6. Generates a changelog from `git log <previous-tag>..HEAD`.
7. Creates a GitHub Release with that changelog.

The tag push automatically triggers:

- **Packagist** — picks up the new version via webhook within seconds.
- **extensions.typo3.org** — mirrors the new Packagist version, typically within
  the hour. Nothing runs in this repository for it.

## Verifying a release

```bash
# Composer
composer show enhancely/enhancely-for-typo3 --all | grep '^versions'

# TER (web UI)
open "https://extensions.typo3.org/package/enhancely/enhancely-for-typo3"
```

Packagist reflects the tag within seconds. The TER listing lags behind it by up
to about an hour. If it has not appeared after that, the cause is on the
Packagist side — there is no TER upload step in this repository that could fail.

`release.sh` keeps the git tag and the `'version'` in `ext_emconf.php` in sync;
if those two ever diverge, the working tree was edited between bump and tag.
