# Design: SEO Crawler & Analyse-Tool (v3)

**Datum:** 2026-08-12
**Branch:** `v3`
**Status:** Genehmigt (Design), bereit für Implementierungsplan

## Zweck

Ein eigener Website-Crawler pro Project (Screaming-Frog-artig) für technische SEO- und
On-Page-Analyse. Nutzer geben eine Start-URL an; der Crawler erfasst die Site, führt eine
Analyzer-Pipeline aus und präsentiert Ergebnisse als Issue-Zusammenfassung plus durchsuchbare
URL-Tabelle. Zwei Modi: **Full-Site** und **Single-Page**.

## Getroffene Entscheidungen

| Thema | Entscheidung |
|-------|--------------|
| Crawl-Ziel | Eigenständige Entität, freie Start-URL, nur an `project_id` gebunden (optional `site_id` nullable) |
| Analyse-Scope v1 | Status & Links, On-Page & Meta, Indexierbarkeit, Bilder & Technik, **Heading-Struktur** |
| Rendering | HTTP als Default; Headless Chrome (Browsershot) als **Opt-in pro Crawl** |
| SPA-Handling | Opt-in bleibt — kein Auto-Fallback, keine Auto-Warnung in v1 |
| Domain-Grenze | Crawl bleibt auf der Start-Domain; externe Links nur Status-Check, nicht weiterverfolgt |
| Seitenlimit | Standardmäßig unbegrenzt, optionales konfigurierbares Limit pro Crawl |
| Politeness | robots.txt respektieren (Default an); Rate-Limiting / Delay zwischen Requests |
| Historie | Jeder Lauf als eigener Datensatz; Historie bleibt erhalten (Vergleichs-UI ggf. später) |
| Ergebnis-UI | Kombiniert: Issue-Zusammenfassung + vollständige durchsuchbare URL-Tabelle |
| Crawl-Motor | `spatie/crawler` (Ansatz A) + eigene Analyzer via `CrawlObserver` |

## Architektur-Ansatz

`spatie/crawler` liefert den Crawl-Motor (Guzzle-Concurrency-Pool, robots.txt-Respekt, Delay,
Domain-Begrenzung via `CrawlInternalUrls`-Profil, Total-Crawl-Limit, optionales JS-Rendering
über Browsershot). Wir implementieren einen `CrawlObserver`, der pro Seite unsere
Analyzer-Pipeline ausführt und Ergebnisse persistiert. Der Crawl läuft in einem Queue-Job,
damit die App nicht blockiert.

**Warum:** liefert alle geforderten Crawl-Features fertig, passt zum bestehenden Spatie-Stack
(Browsershot, activitylog, pdf, settings), bringt Symfony DomCrawler mit. Wir bauen die
Wertschöpfung (Analyzer + UI) statt Crawl-Mechanik neu zu erfinden.

## Datenmodell

Alle Tabellen mit ULIDs (wie `Site`) und `project_id` (Multi-Tenant-Muster).

### `crawls` — ein Crawl-Lauf (Historie)

- `id` (ULID), `project_id`, `site_id` (nullable)
- `start_url`, `mode` (`full_site` | `single_page`)
- Config: `render_js` (bool), `respect_robots` (bool), `delay_ms` (int), `max_pages`
  (nullable = unbegrenzt), `include_subdomains` (bool)
- Status: `status` (`queued` | `running` | `completed` | `failed`), `started_at`,
  `finished_at`, `pages_crawled` (Live-Counter), `error` (nullable text)
- `summary` (JSON) — aggregierte Issue-Zähler für das Dashboard,
  z.B. `{ "404": 3, "missing_title": 5, "heading_order": 8 }`

### `crawl_pages` — eine gecrawlte URL

- `id`, `crawl_id`, `url`, `final_url`, `status_code`, `redirect_chain` (JSON),
  `content_type`, `response_time_ms`, `size_bytes`, `depth`
- On-Page: `title`, `title_length`, `meta_description`, `meta_description_length`,
  `canonical`, `meta_robots`, `word_count`
- Headings: `headings` (JSON, geordnete Liste `[{level:1,text:"…"}]`) + abgeleitete Flags
- Indexierbarkeit: `is_indexable` (bool), `indexability_reason` (string), `in_sitemap` (bool)
- Link-Graph-Ableitungen: `inlinks_count` (nach Aggregation), `is_orphan` (bool)
- Technik: `structured_data` (JSON: gefundene Schema.org-Typen),
  `images_missing_alt` (JSON/Count)
- `issues` (JSON: Liste von Issue-Codes dieser Seite) — für Filterung „zeige Seiten mit X"

### `crawl_links` — Link-Graph

- `id`, `crawl_id`, `from_page_id`, `to_url`, `type` (`internal` | `external`), `anchor`,
  `rel`, `status_code` (nullable — in v1 nur für intern gecrawlte Ziele befüllt; das aktive
  Abprüfen externer/nicht gecrawlter Ziele kommt mit der späteren Broken-Link-Detection)

**Issues** werden nicht als eigene Tabelle materialisiert, sondern pro Seite als Code-Liste
gespeichert und in `crawls.summary` aggregiert. Reicht für „Dashboard + Tabelle + Filter"
und bleibt schlank.

## Komponenten

### Crawl-Orchestrierung

- **`RunCrawlJob`** (Queue-Job) — konfiguriert `spatie/crawler` aus dem `Crawl`-Record:
  Delay (`setDelayBetweenRequests`), robots.txt (Default respektiert), Domain-Grenze
  (`CrawlInternalUrls`-Profil), `max_pages` (`setTotalCrawlLimit`), JS
  (`executeJavaScript` via Browsershot). Hängt den Observer an, startet den Crawl. Setzt
  Status `running` → `completed`/`failed`. **Single-Page-Modus** = Limit 1 / Tiefe 0, keine
  Link-Verfolgung, sonst identische Pipeline.
- **`CrawlObserver`** (implementiert Spaties `CrawlObserver`) — Callback pro URL: baut
  `DomCrawler`, ruft Analyzer-Pipeline auf, persistiert `crawl_page` + `crawl_links`, erhöht
  `pages_crawled`. Fehler pro Seite werden gefangen (eine kaputte Seite killt nicht den
  Crawl) und als Page mit Error-Status gespeichert.

### Analyzer-Pipeline

Pure Klassen: `HTML`/`DomCrawler` rein → Findings raus, ohne DB/HTTP → isoliert unit-testbar.
Jeder Analyzer liefert Felder **und** Issue-Codes in ein `PageAnalysis`-DTO.

- `MetaAnalyzer` — Title/Description/Canonical/Meta-Robots/Wortanzahl
- `HeadingAnalyzer` — H1–H6-Reihenfolge, fehlendes/mehrfaches H1, Level-Sprünge (z.B. H1→H3)
- `IndexabilityAnalyzer` — noindex, Canonical-Konflikt, robots.txt-Block → `is_indexable` + Grund
- `LinkExtractor` — interne/externe Links, Anchor, rel
- `ImageAnalyzer` — Bilder ohne Alt
- `StructuredDataAnalyzer` — JSON-LD / Schema.org-Typen

### Aggregation (nach Crawl-Ende)

**`AggregateCrawlJob`**:
- Inlinks aus `crawl_links` zählen → `inlinks_count`; Seiten mit 0 Inlinks (außer Start-URL)
  = `is_orphan`
- Statuscodes der gecrawlten Seiten werden erfasst (jede intern gecrawlte URL kennt ihren
  `status_code`). Ein **aktives Abprüfen jedes Link-Ziels** auf 404/403 findet in v1 **nicht**
  statt (siehe Out of Scope — „Broken-Link-Detection").
- **Sitemap-Abgleich**: `SitemapReader` holt `sitemap.xml` (aus robots.txt oder
  `/sitemap.xml`), parst URLs → `in_sitemap`-Flag + Report „im Sitemap aber nicht gecrawlt /
  gecrawlt aber nicht im Sitemap"
- Duplicate-Erkennung (gleiche Title/Description über Seiten)
- Alle Issue-Codes zu `crawls.summary` zusammenzählen, Status `completed`

## Datenfluss

1. `CrawlController@store` validiert Eingaben (inkl. SSRF-Schutz), erstellt `Crawl` (`queued`),
   dispatcht `RunCrawlJob`.
2. `RunCrawlJob` setzt `running`, konfiguriert & startet den Crawler.
3. `CrawlObserver` schreibt `crawl_pages` + `crawl_links` live, erhöht `pages_crawled`.
4. Bei Crawl-Ende dispatcht `RunCrawlJob` den `AggregateCrawlJob`.
5. `AggregateCrawlJob` berechnet Inlinks/Orphans/Broken-Links/Sitemap/Duplicates/Summary,
   setzt `completed`.
6. Die Show-Seite pollt währenddessen `status` + `pages_crawled` (Inertia partial reload).

## HTTP-Layer & Routes

Unter `/project/{project}/`, geschützt durch `auth` + `ProjectBindingMiddleware`.

| Methode | Pfad | Aktion |
|---------|------|--------|
| GET | `crawls` | Index — Liste aller Läufe |
| GET | `crawls/create` | Konfig-Formular |
| POST | `crawls` | Validieren, `Crawl` erstellen, `RunCrawlJob` dispatchen |
| GET | `crawls/{crawl}` | Show — Dashboard + Tabelle |
| GET | `crawls/{crawl}/pages/{page}` | Seiten-Detail |
| DELETE | `crawls/{crawl}` | Lauf löschen (Historie aufräumen) |
| GET | `crawls/{crawl}/export` | CSV-Export der Seiten-Tabelle |

- `CrawlController` + `CrawlPolicy` (Crawl gehört zum aktuellen Project → sonst 403).
- Start-URL-Validierung inkl. **SSRF-Schutz** (keine internen IPs / localhost / Nicht-HTTP-Schemata).
- Route-Aufrufe konsistent über `route()` mit Objekt-Param (`{ project: id, crawl: id }`).

## Frontend (React / Inertia)

Bestehendes `@/Components/ui`-Kit, `AppLayout`, Design-Tokens (semantische Tokens, nicht
Roh-Palette).

- **`Crawls/Index`** — Tabelle der Läufe: Status-Badge, Start-URL, Datum, Seitenzahl,
  „Neuer Crawl"-Button.
- **`Crawls/Create`** — Konfig-Formular: Start-URL, Modus (Full-Site/Single-Page),
  JS-Rendering-Toggle (mit Hinweis „für SPAs/Nuxt aktivieren"), robots-Toggle, Delay,
  optionales Limit, Subdomains.
- **`Crawls/Show`** — zweigeteilt:
  1. **Issue-Zusammenfassung** oben: Karten/Badges nach Schweregrad (Fehler/Warnung/Hinweis)
     aus `summary`, klickbar → filtert die Tabelle.
  2. **URL-Tabelle** darunter: durchsuchbar/filterbar/sortierbar (Status, Title, Indexierbar,
     Inlinks, Issues …), serverseitig paginiert; Zeile → Detailseite. Bei laufendem Crawl:
     Live-Fortschritt via Polling.
- **`Crawls/Page`** — alle erfassten Daten einer URL: Redirect-Kette, Headings-Baum, Meta,
  ein-/ausgehende Links, Bilder ohne Alt, Structured Data, Issue-Liste.

## Fehlerbehandlung

- Fetch-Fehler (Timeout / DNS / Connection) → Page mit Error-Status statt Crash.
- Analyzer-Exception pro Seite gefangen → eine kaputte Seite killt den Crawl nicht.
- Job-Fehler → Crawl `failed` mit `error`-Text, sichtbar in der UI.
- robots.txt nicht ladbar → als „allow" behandeln + Hinweis.

## Teststrategie (TDD, wo sinnvoll)

- **Analyzer = Unit-Tests** (Kern): jede Klasse gegen HTML-Fixtures — Heading-Sprung H1→H3,
  fehlendes H1, noindex, Canonical-Konflikt, Bild ohne Alt, JSON-LD-Typ.
- **Aggregation = Feature-Tests** auf geseedeten `crawl_pages`/`crawl_links`: Inlink-Zählung,
  Orphan-Erkennung, Duplicate-Titles, Sitemap-Abgleich, Summary-Zähler.
- **Controller = Feature-Tests**: Auth/Policy (fremdes Project → 403), Validierung inkl.
  SSRF-Schutz, Job-Dispatch (`Queue::fake`), Show liefert Daten.
- **Crawl-Orchestrierung** nur leicht getestet (Konfig-Mapping `Crawl` → Crawler-Optionen);
  echter Crawl gegen kleines lokales Fixture-HTML-Set, nicht gegen das echte Web.
- Route-Aufrufe in Tests über `route()` mit Objekt-Param.

## Dependencies & Werkzeuge

- **Neu:** `spatie/crawler` (bringt Guzzle + Symfony DomCrawler; JS-Rendering nutzt vorhandenes
  Browsershot/Chromium). Installation via `ddev composer require spatie/crawler`.
- Alle Befehle via DDEV (`ddev composer`, `ddev php artisan`, `ddev npm`).
- Frontend-Gate: `ddev npm run build` (Lint ist im Projekt defekt).

## Out of Scope (v1)

- **Broken-Link-Detection** — aktives Auffinden fehlerhafter URLs / kaputter Links im Content,
  die zu 404/403 führen (aktives Abprüfen jedes Link-Ziels). Kommt als eigenes Folge-Feature;
  das `crawl_links`-Datenmodell ist bereits darauf vorbereitet (`status_code`-Spalte).
- Vergleichs-/Trend-UI zwischen Läufen (Datenmodell unterstützt Historie; UI ggf. später).
- SPA-Auto-Erkennung / Auto-Fallback auf Chrome.
- Externen Links über die Start-Domain hinaus folgen.
- Log-File-Analyse, GA4/Search-Console-Integration (Screaming-Frog-Features späterer Phasen).
