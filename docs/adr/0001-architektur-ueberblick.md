# ADR-001: Architektur-Überblick — EXT:enhancely (Enhancely JSON-LD for TYPO3)

**Status:** Accepted · **Datum:** 2026-07-21 · **Kontext:** TYPO3-Extension `enhancely/enhancely-for-typo3`, TYPO3 v13/v14, PHP ^8.2

## Zweck
Die Extension reichert Frontend-Seiten automatisch mit **Schema.org JSON-LD** an, das von der externen **Enhancely-API** (KI-generiert) geliefert wird — zur Verbesserung von SEO und LLM-Sichtbarkeit. Kein manuelles Schema-Markup nötig; Seite rendert bei API-Fehler unverändert weiter (Graceful Degradation).

## Namespace & Autoload
`Enhancely\Enhancely\` → `Classes/` (PSR-4). Extension-Key: `enhancely`. Abhängigkeiten: cms-core/backend/frontend/fluid, `guzzlehttp/guzzle ^7.5`.

## Schichten / Komponenten (echter Code in `Classes/`)
Der Knowledge-Graph enthält daneben ~900 Nodes aus `var/cache/code/di` (generierter TYPO3-DI-Container) — **Rauschen**, nicht Teil der Architektur.

- **Middleware** (`Classes/Middleware/JsonLdMiddleware.php`) — PSR-15-Middleware, Frontend-Einstiegspunkt. `process()` → `shouldProcess()` (Enabled-Flag, Doktype-Ausschluss) → holt JSON-LD über den Client → `injectJsonLd()` fügt es vor `</head>` ein → `writeCache()`/ETag-Handling.
- **Client-Layer** (`Classes/Client/`) — Kapselt die HTTP-Kommunikation zur Enhancely-API. Höchster Fan-in (`core`-Layer, 118 eingehende Calls).
  - `Client` — Fassade: `setApiKey`/`setApiBaseUrl`/`setApiEndpoint`/`jsonld()`. Konfigurierbar, delegiert an `HttpClient`.
  - `HttpClient` (+ `HttpClientFactory`) — Guzzle-Aufruf `postJsonLd()`, `handleSuccessResponse()`, begrenztes Body-Lesen (`readBounded…`).
  - `JsonLdResponse` — Value-Object der API-Antwort.
  - `UrlNormalizer` — entfernt Query-Params/Fragmente für konsistentes Caching.
  - `Exception/ApiException` — typisierte Fehler des Client-Layers.
- **Backend / Info-Modul** (`Classes/Backend/InfoModule/`) — read-only Info-Modul-Tab, zeigt den Enhancely-Status der aktuell gewählten Seite.
  - `EnhancelyStatusController` (`__invoke`, `resolveDoktype`, `buildViewState`, `call…`) — Fluid-basierter Controller.
  - `JsonLdFetcher.fetch()` — holt JSON-LD für die Backend-Vorschau (nutzt den Client-Layer).
  - `UrlResolver` — ermittelt die Frontend-URL der Seite. `ViewState` — DTO fürs Template.
- **Backend / SanityCheck** (`Classes/Backend/SanityCheck/`) — `SanityChecker.check()` mit Einzelprüfungen (`checkBreadcrumbAbsolute`, `checkTitleMismatch`, …) → `CheckResult`. Validiert das gelieferte JSON-LD gegen die Seite.
- **Configuration** (`Classes/Configuration/ExtensionConfiguration.php`) — typisierter Zugriff auf die Extension-Config (API-Key, Enabled, Base-URL, Excluded Doktypes, Cache-Lifetime). `core`-Layer, reiner Consumer.

## Laufzeit-Fluss (Frontend)
```
Request → JsonLdMiddleware.process()
        → shouldProcess() (enabled? doktype nicht excluded?)
        → Client.jsonld(url) → HttpClient.postJsonLd() → Enhancely API
        → JsonLdResponse → injectJsonLd() vor </head>
        → ETag-Cache (enhancely_etag) für Folge-Requests
```
Boundary-Calls im Graph bestätigen: Middleware→Client (16), Backend→Client (13), Backend→Configuration (6).

## Backend-Fluss
Info-Modul-Tab → `EnhancelyStatusController.__invoke` → `JsonLdFetcher.fetch` (Client) + `SanityChecker.check` → `ViewState` → Fluid-Template (read-only Anzeige inkl. schema.org-/Google-Validator-Links, Copy-Buttons).

## Querschnitt / Entscheidungen
- **Caching:** eigener TYPO3-Cache `enhancely_etag` (`VariableFrontend` + `Typo3DatabaseBackend`, Default-Lifetime 24 h, Cache-Gruppe `pages`), registriert in `ext_localconf.php`. Bedingte ETag-Requests minimieren API-Aufrufe.
- **Fehlerbehandlung:** `ApiException`; API-Fehler brechen das Rendering nicht ab (Graceful Degradation).
- **Konfiguration:** zentral über `ExtensionConfiguration` statt verstreuter `$GLOBALS`-Zugriffe.
- **Trennung Frontend/Backend:** beide Einstiegspunkte teilen sich den **Client-Layer** als einzige API-Schnittstelle (DRY, single source of truth für HTTP).
- **URL-Normalisierung** als eigene Verantwortlichkeit (`UrlNormalizer`) für Cache-Konsistenz.

## Tests
`Tests/` (PSR-4 `Enhancely\Tests\`), PHPUnit ^10.5/^11, `dg/bypass-finals`. Zwei kohäsive Test-Cluster im Graph (Cohesion 0.82–0.89) rund um Client (`fromApiResponse`, `createHttpClient`, `injectJsonLd`) und SanityCheck (`SanityChecker`, `buildViewState`).

## Konsequenzen
- Externe Laufzeitabhängigkeit von der Enhancely-API (Key + Endpoint erforderlich) — bewusst hinter dem Client-Layer isoliert.
- Kompatibilität v13 **und** v14 gleichzeitig (composer-Constraint `^13 || ^14`); v12 nur Classic-Mode-Aktivierung.

---

_Dieser ADR ist zusätzlich im codebase-memory Knowledge-Graph hinterlegt (`manage_adr`, Projekt `enhancely-typo3`)._
