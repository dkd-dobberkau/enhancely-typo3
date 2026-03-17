# Enhancely TYPO3 Extension - TODO

Feedback von Marcel (2026-03-17): Installation scheitert bei TYPO3 v13 / PHP 8.2.

## Fixes

- [x] **README: Installationsanleitung korrigieren** (erledigt 2026-03-17)
  - "Extension Manager" Hinweis entfernt, `database:updateschema` als Pflichtschritt
  - Badge und Requirements um TYPO3 14 ergänzt
  - API Base URL Setting dokumentiert, Excluded Page Types Beispiel korrigiert

- [x] **Middleware-Referenzen v13-kompatibel machen** (erledigt 2026-03-17)
  - `output-compression` und `content-length-headers` entfernt (in TYPO3 v13 nicht mehr vorhanden)
  - Middleware positioniert nach `prepare-tsfe-rendering` (existiert in v12, v13, v14)

- [x] **vendor/ aus Extension-Paket entfernen** (kein Handlungsbedarf)
  - `vendor/` ist bereits in `.gitignore` und wird nicht von Git getrackt
  - Nur lokal vorhanden durch `composer install`

- [x] **Packagist-Veröffentlichung prüfen** (erledigt 2026-03-17)
  - Paket ist auf packagist.org verfügbar (21 Installationen, Version 1.2.1)
  - `composer require enhancely/enhancely-for-typo3` funktioniert grundsätzlich

- [x] **Cache-Tabelle in Doku erwähnen** (erledigt 2026-03-17)
  - `database:updateschema` im README als Installationsschritt dokumentiert

- [x] **ChatGPT-Link von Marcel analysiert** (erledigt 2026-03-17)
  - Kernproblem: Kunde hat falschen Paketnamen verwendet (`dkd/enhancely` statt `enhancely/enhancely-for-typo3`)
  - ChatGPT hat Kunden in die Irre geführt: `extension:activate` ist in Composer Mode nicht nötig
  - Extension war nach `composer require` bereits aktiv, es fehlte nur `database:updateschema`
  - Antwort an Marcel formuliert

## Offen

- [ ] **Klären woher der Paketname `dkd/enhancely` kam** - Hat jemand den falschen Namen kommuniziert? Docs prüfen, ggf. Packagist-Alias anlegen
