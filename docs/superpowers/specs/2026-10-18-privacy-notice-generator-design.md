# Privacy Notice Generator — Design

Date: 2026-10-18
Status: approved for planning

## Intent

Marketix customers need a paragraph for their own privacy policy that
describes what Marketix does on their site. Today the Data Privacy page
(`/project/{project}/docs/data-privacy`) offers one static sentence,
identical for every project regardless of configuration.

That sentence is incomplete, and in one place it is factually wrong. The
page claims Marketix sets no third-party cookies and does no cross-site
tracking. When retargeting pixels are attached to a short link, the
redirect interstitial loads Meta, Google Ads, TikTok, LinkedIn and others,
which do exactly that. A customer who pastes the current sentence into
their policy publishes a false statement about their own site.

This project replaces the static sentence with a generator: a form,
pre-filled from the project's real configuration and editable, that
produces a complete, legally structured privacy notice in the project's
language.

### Why now

Marketix is operated by agencies that run one project per client. Each
client is a data controller who must publish an accurate privacy policy,
and the agency is the one who has to supply the wording. Doing that by
hand per client does not scale, and the text they currently copy does not
hold up.

### Success

An agency opens the page for a client project, corrects nothing or little,
copies the result, and that text accurately describes every form of
processing that project actually performs — including the parts that are
inconvenient.

## Goals

- One generated notice per project, covering all of its sites and all of
  its short links.
- The form is pre-filled from the stored configuration and every field
  stays editable.
- The notice covers both operating models: the instance operator measuring
  for their own company, and an agency measuring on behalf of a client.
- The notice names every processing activity the codebase actually
  performs, including retargeting pixels.
- Output in German, English, French and Dutch.
- Party details (agency, hosting provider, client) are stored once, not
  retyped per generation.

## Non-goals

- A consent banner on the redirect interstitial. The pixel consent gap is
  **named and warned about**, not closed. That is separate, later work.
- Legal advice or a certification claim. The page keeps its existing
  disclaimer.
- Changing any tracking behaviour. This project reads configuration and
  writes text; it does not alter what is collected.
- Per-site notices. One text per project, settled.
- Storing or versioning generated notices.

## Anti-goals

Things that would make this a failure even if it technically works:

- **A notice that claims consent where none is obtained.** With pixels
  active and no consent gate, text saying "on the basis of your consent"
  is false. The generator must never produce it.
- **A notice that omits the pixels.** A text that reads clean because it
  leaves out the awkward part is worse than the current one, because it
  looks thorough.
- **Silent placeholders.** A missing controller address must be visible,
  not papered over.

## What the system actually does (verified against the code)

The generated text must match this. Every item below was read out of the
codebase, not assumed.

### Short link clicks (`RedirectController`, `Statistic`)

Recorded server-side on every click, always, with no cookie and no consent
gate. Stored per click: `visitor_hash`, country, country code, city,
language (first two characters of `Accept-Language`), the **full referer
URL** (not only the domain), browser, OS, bot flag, timestamp.

The visitor hash is `sha256(ip | user-agent | project id | daily salt)`
(`App\Support\VisitorHash`). The salt is random, rotates daily and expires
from cache after 48 hours, after which the hash can no longer be related
to an IP address. Link click hashes are scoped per project, site hashes per
site, so the same person is not linkable across projects or sites.

### Retargeting pixels (`resources/views/redirect/pixels.blade.php`)

When a link has pixels attached, the click does not 302. Marketix serves an
interstitial HTML page that loads the vendor scripts and then forwards.
Supported providers: Google Tag Manager, Google Analytics, Facebook/Meta,
Google Ads, LinkedIn, Twitter/X, AdRoll, Quora, Pinterest, Bing, Snapchat,
Reddit, TikTok.

**There is no consent gate on this page.** The scripts run on every click.
They set their own cookies and transmit to the vendor, in most cases to the
United States.

### Site analytics (`AnalyticsIngestionController`, `mx.js`)

Per site, configurable:

- `tracking_mode`: `cookieless` (default), `hybrid`, `cookie`
- `consent_mode`: `immediate` (default), `third_party_signal`
  (`own_banner` exists in the enum but is rejected by validation)
- `respect_dnt` (default off), `track_outbound_links` (default on),
  `track_file_downloads`, `site_search_params`, `retention_days`

Stored per page view: path (query string stripped), page title, hostname,
full referer URL plus referer domain, country, country code, city, region,
browser, OS, device, language, UTM parameters, bot flag, engaged seconds,
scroll depth, timestamp. Visits additionally store entry path, exit path,
returning flag and first-touch campaign source.

Custom events store a free-form `props` JSON object up to 1 KB, supplied by
whoever installed the snippet. Site search terms arrive as a `site_search`
event with the lower-cased term truncated to 100 characters, only when
`site_search_params` is configured.

The `mx_vid` cookie is first-party, 365 days, `SameSite=Lax`, `Secure` on
HTTPS. It is set in `cookie` mode and, after consent, in `hybrid` mode.

**`cookie` mode combined with `consent_mode: immediate` sets the cookie
without any consent** (`mx.js`, `shouldTrack`). Both are reachable through
the UI and `immediate` is the default. The generator must treat this
combination as a warning, the same way it treats pixels.

### Geolocation (`GeoIpService`)

MaxMind GeoLite2-City, read from a local `.mmdb` file in storage. No IP
address leaves the instance for geolocation. This is a genuine advantage
and belongs in the notice.

### Retention

- Link click statistics: `STATISTICS_RETENTION_MONTHS`, default 12 months,
  hard-deleted by `statistics:prune` (daily).
- Visits, page views, events: `ANALYTICS_RETENTION_MONTHS`, default 24
  months, hard-deleted by `analytics:prune` (daily). A site's
  `retention_days` overrides this downwards.

The notice must state the value that actually applies, per site where they
differ.

### Data subject requests

`marketix:visitor:export` and `marketix:visitor:forget` operate on a site's
tracking ID plus the visitor's `mx_vid` value. They therefore only work for
visitors in `cookie` or `hybrid` mode. In `cookieless` mode there is no
identifier a visitor could present, and after the 48-hour salt expiry the
stored data is anonymous rather than pseudonymous — Articles 15 and 17 do
not apply to it. The notice must say this plainly rather than promising
rights that cannot be exercised.

## The screen

The six explanatory sections currently on the Data Privacy page stay where
they are. The generator is added below them.

Layout, confirmed from a mockup: two columns. Form on the left, live
preview on the right. The preview re-renders as fields change.

### Form, top to bottom

**Role.** Two-state switch.
- *Own company* — the instance operator is the controller. Controller
  details come from the admin settings.
- *Client project* — the client is the controller, the agency is a
  processor acting on their instruction. Controller details come from the
  project.

**Controller.** Name and address. In client mode these are the project's
client fields. They are maintained **only by super admins**, in the admin
area under Projects — a project-level admin does not get to edit them.
Inside the generator they are read-only for everyone. In own-company mode
they come from the agency settings.

**Processors.** Read-only summary from the admin settings: agency name and
address, hosting provider and server location. Shown so the user can see
what will appear in the text, not edited here.

**What is active.** Pre-filled from configuration, every row toggleable:
- Short link click statistics (with the project's link count)
- Site analytics, listing each site with its tracking and consent mode
- Retargeting pixels — flagged, listing the providers in use and the
  number of links affected
- Outbound link and file download tracking
- Site search
- Custom events

**Legal basis.** Legitimate interest (Art. 6(1)(f)) or consent
(Art. 6(1)(a)). The choice governs measurement — link clicks and site
analytics. Pixels are not covered by it; see below.

**Language.** DE, EN, FR, NL.

### Warnings

A warning block sits above the preview and lists, in plain language, what
makes the current configuration legally risky:

1. Pixels fire without consent. § 25 TDDDG requires consent before those
   scripts run; the generated text will describe this honestly, which means
   it documents a gap the operator needs to close.
2. A site in `cookie` mode with `consent_mode: immediate` sets a 365-day
   cookie without consent.
3. Required party details are missing.

### Output

Fließtext with sub-headings. Two copy buttons: plain text, and HTML using
`<h3>` and `<p>` for pasting into a CMS.

Nothing is persisted. The text is rebuilt from current configuration on
every page load so it cannot go stale.

### Missing data

Missing mandatory details are emitted as visible placeholders —
`[Name des Verantwortlichen]` and the like — and listed in the warning
block. The generator never refuses to produce a text.

## Where things are stored

**Agency and hosting details** — a new `spatie/laravel-settings` group
alongside `App\Settings\BrandingSettings`, edited in the admin area
following the pattern of `Admin/BrandingController` and
`resources/js/Pages/Admin/Branding`. Fields: agency name, address, hosting
provider name, server location.

The agency is also the instance operator; these are one party in two roles.
A future white-label case where a third party hosts for the agency is left
unmodelled — the hosting field carries the sub-processor that matters today.

**Client details** — new columns on `projects`, edited in the admin area's
project screens behind the `super_admin` middleware (`Admin/ProjectController`,
`resources/js/Pages/Admin/Projects`). Fields: client name, client address.
`ProjectRole::Admin` deliberately does not grant access: these identify the
agency's client, not the project's working configuration.

## The generated text

Structure, in order:

1. Controller, named with address
2. Processor chain — agency on the controller's instruction, hosting
   provider with server location, and whether data leaves the EU
3. What is processed, per activity that is switched on
4. How the IP address is handled — hashing, daily salt, 48-hour expiry,
   local geolocation with no transfer. When the GeoLite2 database is not
   installed on the instance, no geolocation takes place and the passage is
   omitted rather than describing something that does not happen.
5. Cookies, named, with lifetime and purpose, per mode in use
6. Retargeting pixels, named per provider, with their recipients and the
   transfer to third countries. This section cites no legal basis and in
   particular never claims consent. It describes what happens — that
   clicking a short link embeds services by the named providers, which set
   their own cookies and transmit to the provider, in most cases to the
   United States. The blunt statement that this currently happens without
   a consent query belongs in the UI warning block, not in text the client
   publishes.
7. Legal basis, matching the choice, per activity
8. Retention, with the values that actually apply
9. Data subject rights, with an honest statement of what can and cannot be
   exercised in cookieless mode, plus the right to complain to a
   supervisory authority

Translations live in `lang/{de,en,fr,nl}/` following the existing
convention. The existing `docs.php` privacy strings stay; the generator's
strings are additional.

## Corrections to existing content

Independent of the generator, the current page states things that are not
true and must be fixed in all four languages:

- `docs.privacy.nocollect.cookies` — "no third-party cookies and no
  cross-site tracking" is false whenever pixels are attached.
- `docs.privacy.modes.cookie` — "only after the visitor's consent signal is
  present" is false under `consent_mode: immediate`.
- `docs.privacy.collect.referrer` — says referrer domain; the full referer
  URL is stored.
- The data list omits page title and hostname.
- `docs.privacy.modes` does not mention hybrid mode at all.

## Left to the builder

- Component decomposition within the page, and how preview state is held.
- Whether generation runs client-side from props or server-side on change.
  Either satisfies the requirements; no persistence is involved.
- The exact German, English, French and Dutch wording, within the structure
  above.
- Validation rules and admin form layout for the new settings and project
  fields.
- Test strategy, following the project's existing Pest conventions.

## Calls made during write-up

These were not discussed; they are the author's judgment and can be
overridden:

- Processor details are shown read-only in the generator rather than
  editable there, so that the admin settings stay the single source.
- The role switch defaults to client mode when the project carries client
  details and to own-company mode otherwise.
- The language selector defaults to the user's current UI locale.
- Supervisory-authority complaint rights are included in the generated
  text as a standard mandatory element.
- The hosting provider is modelled as one field pair rather than a full
  sub-processor list.
