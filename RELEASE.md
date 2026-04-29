# Release Process

This document describes how a new version of the **enhancely** TYPO3 extension
is published to:

1. [Packagist](https://packagist.org/packages/enhancely/enhancely-for-typo3)
   (Composer)
2. [TER](https://extensions.typo3.org/package/enhancely/enhancely-for-typo3)
   (TYPO3 Extension Repository)
3. [GitHub Releases](https://github.com/dkd-dobberkau/enhancely-typo3/releases)

## Prerequisites (one-time)

### TER credentials

The TER upload happens via the
[`tomasnorre/typo3-upload-ter`](https://github.com/tomasnorre/typo3-upload-ter)
GitHub Action (which uses `typo3/tailor` under the hood). It needs two GitHub
Actions secrets in this repository:

| Secret name             | Source                                                                              |
|-------------------------|-------------------------------------------------------------------------------------|
| `TYPO3_API_USERNAME`    | typo3.org login of an account that owns the extension key `enhancely`               |
| `TYPO3_API_TOKEN`       | Created at <https://extensions.typo3.org/> → *My Account → Access Tokens → Create*  |

Set them at:
**Repo → Settings → Secrets and variables → Actions → New repository secret**

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
