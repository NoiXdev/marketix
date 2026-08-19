# Demo instance deployment

Marketix ships a "demo mode" that turns one running instance into a public,
self-service demo: a single shared account, seeded with a fictional company's
data, that any visitor can enter with one click and explore. It is wiped and
rebuilt from scratch every night.

This is **not a mode a production instance switches into**. It is a
**separate deployment** — its own container(s), its own database, its own
domain — that happens to run the exact same application image with
`DEMO_MODE=true`. The production instance never sets this flag and is
byte-for-byte unaffected by any of the code described here (every demo-only
code path — `DemoServiceProvider`, `DemoGuard`, the `/demo/login` route, the
nightly reset schedule entry — is gated behind `config('demo.enabled')` and
simply does not exist when it is false).

See `docs/superpowers/specs/2026-08-18-demo-mode-design.md` for the full
design rationale; this document is the operational how-to-run-it note.

## Why a separate service

- The demo account is a shared, unauthenticated-entry, `super_admin` account.
  It must never be reachable from the real production database.
- `marketix:demo:reset` runs `migrate:fresh` — an unrecoverable schema wipe.
  Running it against the production database would destroy every customer's
  data. The command already refuses to run unless `config('demo.enabled')` is
  true, but a separate database is the layer that makes a misconfiguration
  survivable.
- Demo visitors get real, if bounded, side effects: they can create links, QR
  codes, and crawls that resolve/execute for real (against an allowlist — see
  below). None of that should ever touch a paying customer's project.

## Required environment

These are read by `config/demo.php` and by the standard Laravel/Docker
Compose environment already used for production (see
`x-app-env` in the repo's `docker-compose.yml`). Set them on the **demo
service only**:

| Variable | Value | Why |
|---|---|---|
| `DEMO_MODE` | `true` | Master switch. Everything demo-specific — `DemoServiceProvider`'s mail severance, `DemoGuard`, the `/demo/login` route, the nightly reset schedule entry, the link/QR/crawl-quota validation rules — is gated on `config('demo.enabled')` and is otherwise entirely inert. |
| `DEMO_EMAIL` | e.g. `demo@marketix.de` | Email of the single shared account. Defaults to `demo@marketix.de` if unset. `DemoSeeder` creates the user with this email; `DemoLoginController` looks it up by it. |
| `DEMO_PASSWORD` | a real password | Password for the shared account. Only matters if someone logs in via the normal form instead of the one-click button; defaults to `demo` if unset — pick something less guessable since the account is `super_admin`. |
| `DEMO_RESET_AT` | e.g. `04:00` | Local time (instance timezone) of the nightly `marketix:demo:reset` run, via `Schedule::command(...)->dailyAt(config('demo.reset_at'))`. Defaults to `04:00`. |
| `MAIL_MAILER` | `log` | Belt-and-suspenders. `DemoServiceProvider` already cancels every outgoing message at the framework level (a `MessageSending` listener that returns `false`, driver-independent) whenever demo mode is on, so this is a second, cheaper line of defence, not the only one. |
| `SESSION_DRIVER` | `file` | Production's `docker-compose.yml` defaults `SESSION_DRIVER` to `database`. On the demo service this **must** be overridden — see "Why `SESSION_DRIVER=file`" below. |
| `APP_ENV` | `production` | Same as the real production service — this is a public-facing deployment, not a dev environment. |
| `APP_DOMAIN` | its own domain, e.g. `demo.marketix.de` | The demo's own hostname. `DemoAllowedTarget` (the link/QR target allowlist) automatically appends `config('app.domain')` to the allowed host list, so short links on the demo domain always resolve to themselves without any extra config. |
| `DB_*` | its own database | A dedicated database (own MariaDB instance/container, or at minimum its own schema/credentials on a shared server) — see "Why a separate service" above. |

Everything else (`QUEUE_CONNECTION`, `REDIS_*`, etc.) can follow the same
pattern as the production service in the repo's `docker-compose.yml`.

### Queue worker and scheduler are both required, not optional

The demo service needs the same two long-running processes production has,
for reasons specific to demo mode:

- **A queue worker (Horizon).** Creating a link or QR code is synchronous, but
  starting a crawl (`RunCrawlJob`) and sending a scheduled report
  (`SendScheduledReport`, a no-op under mail severance, but it still runs) are
  dispatched to the queue. Without a worker running, every crawl a visitor
  starts sits in `status: queued` forever and the crawler feature looks
  broken.
- **The scheduler (`php artisan schedule:work`, matching the `scheduler`
  service in the repo's `docker-compose.yml`).** The nightly reset is *only*
  registered as a scheduled task (`routes/console.php`):

  ```php
  if (config('demo.enabled')) {
      Schedule::command('marketix:demo:reset')
          ->dailyAt(config('demo.reset_at'))
          ->withoutOverlapping()
          ->name('demo:reset');
  }
  ```

  There is no cron entry, no external trigger — if `schedule:work` (or an
  external `* * * * * php artisan schedule:run`) is not running continuously
  against this service, the demo simply never resets and quietly accumulates
  whatever visitors leave behind.

## Why `SESSION_DRIVER=file`

Production's `docker-compose.yml` defaults sessions to the `database` driver.
`marketix:demo:reset` runs `migrate:fresh`, which drops **every** table,
including `sessions`. If the demo service kept sessions in its own database,
the nightly reset would silently log out every visitor who happened to have
an active session at 04:00 — not a security problem, just a rough edge (they
would land back on the login page, which still offers the one-click demo
button, so it's a papercut rather than a defect). Setting
`SESSION_DRIVER=file` keeps sessions on local disk, outside the database
`migrate:fresh` touches, so a reset only replaces application data.

(If the demo service runs multiple app replicas behind a load balancer, use a
shared session store that isn't the app's own database — e.g. Redis — rather
than `file`, for the same underlying reason: whatever holds sessions must
survive `migrate:fresh`.)

## Settings pages reflect the demo instance's own `.env`, not the seeder

`Admin\MailerController`, `StorageController`, and `BrandingController` read
and write `spatie/laravel-settings` records backed by a generic `settings`
table (`group`/`name`/`payload`). `DemoSeeder` does **not** populate this
table. Instead, the settings migrations in `database/settings/` re-create
every property from `config()`/`env()` on every `migrate:fresh`:

```php
// database/settings/2026_06_18_000001_create_mail_settings.php
$this->migrator->add('mail.default_mailer', config('mail.default', 'log'));
$this->migrator->add('mail.smtp_host', (string) config('mail.mailers.smtp.host', ''));
// ...
```

(and correspondingly for `branding.*` in
`2026_06_19_000000_create_branding_settings.php` and `storage.*` in
`2026_06_20_000000_create_storage_settings.php`, the latter reading from
`config('filesystems.disks.s3.*')`).

The practical consequence: **whatever `MAIL_*` and `FILESYSTEM_*` variables
are set in the demo instance's own `.env` at the moment `migrate:fresh` runs
are exactly what the Admin → Mailer / Storage / Branding pages display after
every nightly reset.** There is nothing else feeding those pages on a demo
instance — set `MAIL_MAILER=log` there (as above) and leave the S3 vars empty
unless the demo is meant to showcase S3 storage specifically. This is also
why the seeder doesn't need to (and doesn't try to) seed these three settings
groups itself.

## The hand-edited production `docker-compose.yml`

**The `docker-compose.yml` on the production server is hand-edited and
already diverges from the copy in this repository** (see the project's
`server_compose_divergence` note — hardcoded env values, no `.env` file
present server-side, `APP_ENV` pinned to `production`, a renamed `web`
network, an XDG fix). A `git pull` plus redeploy does **not** pick up a new
service automatically.

Adding the demo service therefore has to be done **by hand, deliberately, on
the server**:

1. Add a new service block (own `environment` map with the table above, own
   image tag or the same `noixdev/marketix:latest` image), following the
   pattern of the existing `app` service — Traefik router rule on the demo's
   own `APP_DOMAIN`, `com.centurylinklabs.watchtower.enable=true` if it should
   auto-update with the same image.
2. Add its own `horizon` and `scheduler` service blocks (same image, same
   `command` overrides as production's), pointed at the demo's own queue
   connection — see "Queue worker and scheduler are both required" above.
3. Add its own database service (or provision a separate schema/credentials
   on an existing MariaDB server) and point `DB_*` at it. Do not point it at
   the production database.
4. Reuse the existing shared `traefik` and `watchtower` services and the
   `web` network — those are edge infrastructure, not per-tenant.
5. Keep this addition in sync with the repo's `docker-compose.yml` the same
   way the rest of the hand-edited file is kept in sync: manually, on
   purpose, each time either one changes.

### First bring-up

The container entrypoint (`docker/entrypoint.sh`) only ever runs
`php artisan migrate --force` on start (when `RUN_MIGRATIONS=true`) — it does
not seed. After the demo service's containers are up for the first time,
before pointing DNS at it, run once, inside the demo `app` container:

```bash
php artisan marketix:demo:reset
```

This performs the same `migrate:fresh` + `DemoSeeder` the nightly schedule
will repeat from then on, so the instance has real data from the moment it
becomes reachable.

## What was verified end to end (2026-08-19)

All of the following were exercised against the local DDEV environment with
`DEMO_MODE=true` set temporarily (never committed — see the accompanying
task report for exactly how `.env` was restored). Full detail, exact
commands, and raw output are in
`.superpowers/sdd/2026-08-18-demo-mode/task-17-report.md`; summary:

- One click on "Demo starten" (`POST /demo/login`) authenticates the shared
  account and lands on a fully populated project dashboard (real KPIs, a
  30-day click chart, top links/countries).
- The demo banner prop (`enabled` + `resetAt`) is present — and therefore the
  banner renders — on the dashboard, an admin page (`Admin/Mailer/Edit`), and
  the profile page (`Profile/Edit`); all three layouts (`AppLayout`,
  `AdminLayout`, `ProfileLayout`) render `<DemoBanner />` unconditionally.
- The Mailer "Save" button renders visibly locked (`aria-disabled`, a tooltip,
  and a screen-reader-only explanation) because `app.admin.mailer.update` is
  on `DemoGuard::BLOCKED_ROUTES`; a direct `PUT /admin/mailer` with a changed
  `default_mailer` was refused server-side (redirected back with a flash
  error) and the underlying `settings` row was confirmed unchanged.
- Creating a link to `https://evil-phishing.test` was rejected with a 422 and
  a message listing the allowed hosts; a link to `https://example.com/x` was
  accepted and persisted.
- Five crawls of `example.com` (the seeded two plus three created for this
  test) were accepted; a sixth was refused with a 422
  ("In der Demo kann jede Domain 5 Mal pro Tag gecrawlt werden.") and no row
  was created.
- Seeded data renders with real numbers throughout: the dashboard (18 links,
  13,232 statistics rows), the project statistics page (13,232 total clicks,
  9,537 unique), a seeded crawl's issue report (24 pages crawled, a
  `summary` breaking down duplicate titles, a client error, a broken link,
  etc.), and the site analytics dashboard (1,100 page views over 30 days,
  top paths, countries, browsers, devices).

One environment limitation encountered: the sandboxed browser tool used for
earlier tasks in this session blocks all `/build/assets/*` requests to
`*.ddev.site` (`net::ERR_BLOCKED_BY_CLIENT`), so the pages never render
client-side JS in that tool. Verification was done instead via direct HTTP
requests (reading the Inertia `data-page` JSON payload) and `artisan
tinker`/raw SQL against the seeded data, which is what the numbers above come
from.
