# Enhancely JSON-LD for TYPO3

AI-generated JSON-LD structured data for improved SEO and LLM visibility.

[![TYPO3](https://img.shields.io/badge/TYPO3-12%20%7C%2013%20%7C%2014-orange.svg)](https://typo3.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)

## What is Enhancely?

Enhancely automatically generates Schema.org JSON-LD structured data for your web pages using AI. This helps search engines and AI platforms better understand your content, improving SEO and visibility.

## Installation

### Composer (recommended)

```bash
composer require enhancely/enhancely-for-typo3
vendor/bin/typo3 database:updateschema
```

In TYPO3 v12 (Classic Mode), activate the extension additionally via **Admin Tools > Extensions**.

## Configuration

1. Go to **Admin Tools > Settings > Extension Configuration**
2. Select **enhancely**
3. Configure:

| Setting | Description | Default |
|---------|-------------|---------|
| API Key | Your Enhancely API key from [enhancely.ai](https://enhancely.ai) | - |
| Enabled | Enable/disable JSON-LD generation | true |
| API Base URL | Base URL without path (leave empty for default) | `https://api.enhancely.ai` |
| Excluded Page Types | Comma-separated doktypes to skip (e.g., `404,403`) | - |
| Cache Lifetime | Cache duration in seconds | 86400 (24h) |

## How It Works

```
Request → Middleware → Enhancely API → JSON-LD injected in <head>
```

1. PSR-15 middleware intercepts frontend responses
2. Calls Enhancely API with the page URL
3. API returns AI-generated JSON-LD
4. JSON-LD is injected before `</head>`
5. ETags are cached to minimize API calls

## Features

- **Automatic JSON-LD**: No manual schema markup required
- **ETag Caching**: Conditional requests minimize API usage
- **TYPO3 Cache Integration**: Uses native caching framework
- **Graceful Degradation**: Page renders normally if API fails
- **URL Normalization**: Strips query params and fragments for consistent caching

## API Response Handling

| Status | Meaning | Action |
|--------|---------|--------|
| 200 | JSON-LD ready | Inject and cache |
| 201/202 | Processing | Skip, retry on next request |
| 412 | Not modified | Use cached version |

## Requirements

- TYPO3 12.4+, 13.x or 14.x
- PHP 8.2+
- Enhancely API key

## Security

- The middleware reads the request URI (including the `Host` header) and sends the
  normalized URL to the Enhancely API. Make sure
  `$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern']` is configured to
  prevent host-header injection / cache pollution.
- The configured **API Base URL** must use `https://`. Non-HTTPS values are
  silently rejected and the default endpoint is used instead, so the API key
  (sent as Bearer token) is never transmitted in cleartext.
- API responses are capped at 1 MiB to prevent memory exhaustion.
- JSON-LD payloads are emitted with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`,
  so embedded HTML cannot break out of the surrounding `<script type="application/ld+json">` tag.

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Links

- [Enhancely Website](https://enhancely.ai)
- [API Documentation](https://docs.enhancely.ai)
