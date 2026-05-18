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
2. [TER](https://extensions.typo3.org/package/enhancely/enhancely-for-typo3)
   (TYPO3 Extension Repository)
3. [GitHub Releases](https://github.com/dkd-dobberkau/enhancely-typo3/releases)

## TER publishing — two paths

The Composer/Packagist + GitHub Release flow is fully automated. The TER
upload has **two paths**; pick whichever fits the credential ownership:

### Path A — Automated via GitHub Action (token in repo secrets)

Used when the TER token can live in this repository's secrets.

| Secret name             | Source                                                                              |
|-------------------------|-------------------------------------------------------------------------------------|
| `TYPO3_API_USERNAME`    | typo3.org login of an account that owns the extension key `enhancely`               |
| `TYPO3_API_TOKEN`       | <https://extensions.typo3.org/> → *My Account → Access Tokens → Create* (scopes: `extension:read,extension:write`) |

Set at: **Repo → Settings → Secrets and variables → Actions → New repository secret**.

When both secrets are present, `.github/workflows/publish-ter.yml` runs on
every semver tag push and uploads automatically. When they are absent, the
workflow skips the upload step gracefully (no red X).

### Path B — Manual upload by the token owner (current setup)

Used when the TER token stays with the customer / extension-key owner. After
we push the git tag, the customer runs:

```bash
# Once: install Tailor globally
composer global require typo3/tailor

# Per release:
git clone https://github.com/dkd-dobberkau/enhancely-typo3.git
cd enhancely-typo3
git checkout 1.2.3                                # or whatever was just tagged

export TYPO3_API_USERNAME='<typo3.org-username>'
export TYPO3_API_TOKEN='<token-from-extensions.typo3.org>'

~/.composer/vendor/bin/tailor ter:publish \
  --comment "Security release: XSS hardening, HTTPS enforcement, response size limit" \
  1.2.3
```

The tag and the source on GitHub are immutable, so the customer can run this
at any time after the tag has been pushed — there is no race window.

### Packagist

Already wired: <https://packagist.org/packages/enhancely/enhancely-for-typo3>.
A GitHub service hook syncs new tags automatically — no manual action needed
per release.

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
- **`.github/workflows/publish-ter.yml`** — uploads the tagged source to TER
  (requires the secrets from the prerequisites section).

## Verifying a release

```bash
# Composer
composer show enhancely/enhancely-for-typo3 --all | grep '^versions'

# TER (web UI)
open "https://extensions.typo3.org/package/enhancely/enhancely-for-typo3"
```

The TER upload shows up in the Actions tab of the repository as
"Publish to TER". If it fails, the most common causes are:

- Secrets not set or expired token.
- TER rejects the version because `ext_emconf.php` version does not match the
  git tag — `release.sh` keeps these in sync, so this should not happen unless
  the working tree was edited between bump and tag.
- The first upload of a brand-new extension key cannot be done by the API and
  must be done once via the web UI; subsequent uploads via API work.
