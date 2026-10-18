# Privacy Notice Generator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single static privacy sentence on the Data Privacy page with a generator that produces a complete GDPR notice from the project's real configuration, in German, English, French and Dutch.

**Architecture:** A `ProcessingSnapshot` reads what a project actually does (links, sites and their modes, pixels, flags, retention, GeoIP availability). A `NoticeComposer` turns that snapshot plus the user's form answers into an ordered list of sections, rendered from per-locale translation files. A `NoticeWarnings` class derives the UI warning list from the same snapshot. Composition runs **server-side** behind a GET endpoint: the Inertia frontend only ever receives the catalog for the current UI locale (`HandleInertiaRequests` shares `Translations::forLocale(App::getLocale())`), so a client-side build could not honour the language selector. The React page debounces form changes against that endpoint and renders the returned sections.

**Tech Stack:** Laravel 13 / PHP 8.3, Inertia + React 19 + TypeScript, `spatie/laravel-settings` for instance-level party details, PHPUnit (class-based, not Pest) for backend tests, Vitest for frontend units.

**Spec:** `docs/superpowers/specs/2026-10-18-privacy-notice-generator-design.md`

## Global Constraints

- **All PHP, Composer and npm commands run through DDEV**: `ddev php artisan …`, `ddev composer …`, `ddev npm …`. Never invoke `php`/`composer`/`npm` directly.
- Backend tests: `ddev php artisan test --filter=<TestClass>`. Full suite: `ddev composer test`.
- Formatting runs as its own CI job: `ddev ./vendor/bin/pint` before each commit. `npm run lint` is known-broken on ESLint 9 — do not gate on it.
- Frontend unit tests: `ddev npm run test:js`.
- All code, comments and documentation in English. All user-facing strings come from `lang/{de,en,fr,nl}/` — never hardcoded in TSX.
- Ziggy `route()` always takes the object form: `route('app.project.docs.privacy', { project: project.id })`, never a bare value.
- Use semantic design tokens (`bg-surface`, `text-foreground`, `border-line`, `text-muted`, `bg-accent`) and the `@/Components/ui` kit. No raw palette classes.
- Tests are PHPUnit classes extending `Tests\TestCase` with `RefreshDatabase`, following `tests/Feature/Docs/DataPrivacyPageTest.php`.
- Settings migrations live in `database/settings/`, not `database/migrations/`.

## Review Focus

Input classes the spec implies but no task's happy path exercises. Each has a test added to the task that owns the code.

1. **HTML-escaping of party details in the HTML output** — client name and address are free text typed by a super admin and land in the copy-as-HTML string; unescaped `<` breaks or injects. Owned by Task 5.
2. **A project with sites but no links, or links but no sites** — the notice must omit the absent activity's section rather than emit an empty heading. Owned by Task 5.
3. **Sites with differing retention** — two sites, one with `retention_days` set and one without, must both be stated; a single figure would be wrong for one of them. Owned by Task 4.
4. **Soft-deleted sites and links** — `Site` and `Url` both use `SoftDeletes`; a trashed site must not appear in the notice as an active processing activity. Owned by Task 4.
5. **Requested language differs from UI locale** — asking for `fr` while the session runs in `de` must return French, which is the whole reason composition is server-side. Owned by Task 8.

---

### Task 1: Correct the false statements on the existing page

The page currently tells customers things that are not true. This is independent of the generator and ships first.

**Files:**
- Modify: `lang/de/docs.php`, `lang/en/docs.php`, `lang/fr/docs.php`, `lang/nl/docs.php`
- Modify: `resources/js/Pages/Docs/DataPrivacy.tsx`
- Test: `tests/Feature/Docs/DataPrivacyPageTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: translation keys `docs.privacy.collect.title_hostname`, `docs.privacy.modes.hybrid`, and corrected bodies for `docs.privacy.nocollect.cookies`, `docs.privacy.modes.cookie`, `docs.privacy.collect.referrer`.

- [ ] **Step 1: Write the failing test**

Add to `DataPrivacyPageTest`:

```php
public function test_all_locales_define_the_same_privacy_doc_keys(): void
{
    $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
        $out = [];
        foreach ($a as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix.'.'.$k;
            $out = array_merge($out, is_array($v) ? $flatten($v, $key) : [$key]);
        }
        return $out;
    };

    $reference = $flatten(require base_path('lang/de/docs.php'));
    sort($reference);

    foreach (['en', 'fr', 'nl'] as $locale) {
        $keys = $flatten(require base_path("lang/{$locale}/docs.php"));
        sort($keys);
        $this->assertSame($reference, $keys, "lang/{$locale}/docs.php key set differs from German");
    }
}

public function test_the_page_no_longer_claims_there_are_no_third_party_cookies(): void
{
    $de = require base_path('lang/de/docs.php');

    $this->assertStringNotContainsString('Keine Third-Party-Cookies', $de['privacy']['nocollect']['cookies']);
    $this->assertArrayHasKey('hybrid', $de['privacy']['modes']);
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `ddev php artisan test --filter=DataPrivacyPageTest`
Expected: FAIL — the German string still contains the claim and `modes.hybrid` is missing.

- [ ] **Step 3: Correct the five wrong statements in `lang/de/docs.php`**

Spec section "Corrections to existing content" fixes exactly these:
- `privacy.nocollect.cookies` — third-party cookies **do** occur when retargeting pixels are attached to a link; state that, and that pixel-free projects set none.
- `privacy.modes.cookie` — the cookie is only gated on consent under `consent_mode: third_party_signal`; under the default `immediate` it is set without one.
- `privacy.collect.referrer` — the **full** referring URL is stored, not only its domain.
- `privacy.collect.title_hostname` — new: page title and hostname are stored.
- `privacy.modes.hybrid` — new: hybrid mode, anonymous until consent, then recognised by `mx_vid`.

- [ ] **Step 4: Mirror the corrections into `lang/en`, `lang/fr`, `lang/nl`**

- [ ] **Step 5: Render the two new keys in `DataPrivacy.tsx`**

Add `docs.privacy.collect.title_hostname` to the `collect` bullet list and `docs.privacy.modes.hybrid` to the `modes` section, between the cookieless and cookie bullets.

- [ ] **Step 6: Run the tests and the formatter**

Run: `ddev php artisan test --filter=DataPrivacyPageTest` → PASS
Run: `ddev ./vendor/bin/pint`

- [ ] **Step 7: Commit**

```bash
git add lang resources/js/Pages/Docs/DataPrivacy.tsx tests/Feature/Docs/DataPrivacyPageTest.php
git commit -m "fix(docs): correct false privacy claims about cookies and referrers"
```

---

### Task 2: Operator settings — agency and hosting provider

**Files:**
- Create: `app/Settings/OperatorSettings.php`
- Create: `database/settings/2026_10_18_000000_create_operator_settings.php`
- Create: `app/Http/Controllers/Admin/OperatorController.php`
- Create: `app/Http/Requests/Admin/OperatorSettingsRequest.php`
- Create: `resources/js/Pages/Admin/Operator/Edit.tsx`
- Modify: `routes/web.php` (super-admin group, next to the branding routes)
- Modify: `lang/{de,en,fr,nl}/admin.php`
- Test: `tests/Feature/Admin/OperatorSettingsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Settings\OperatorSettings` with nullable string properties `agency_name`, `agency_address`, `hosting_provider`, `hosting_location`; `group(): string` returns `'operator'`. Route names `app.admin.operator.edit` (GET `/admin/operator`) and `app.admin.operator.update` (PUT).

- [ ] **Step 1: Write the failing test**

```php
public function test_a_super_admin_can_store_operator_details(): void
{
    $admin = User::factory()->create(['super_admin' => true]);

    $this->actingAs($admin)
        ->put(route('app.admin.operator.update'), [
            'agency_name' => '3B Agentur GmbH',
            'agency_address' => 'Hohenzollernring 1, 50672 Köln',
            'hosting_provider' => 'Hetzner Online GmbH',
            'hosting_location' => 'Nürnberg, Deutschland',
        ])
        ->assertRedirect();

    $this->assertSame('3B Agentur GmbH', app(OperatorSettings::class)->agency_name);
}

public function test_a_normal_user_cannot_reach_the_operator_settings(): void
{
    $this->actingAs(User::factory()->create())
        ->get(route('app.admin.operator.edit'))
        ->assertForbidden();
}

public function test_blank_values_are_stored_as_null(): void
{
    // posting '' must not leave an empty string that renders as a blank party name
}
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `ddev php artisan test --filter=OperatorSettingsTest`
Expected: FAIL — route `app.admin.operator.update` is not defined.

- [ ] **Step 3: Create the settings class and its migration**

Follow `app/Settings/BrandingSettings.php` and `database/settings/2026_06_19_000000_create_branding_settings.php`. Each property registers via `$this->migrator->add('operator.<name>', null)`.

- [ ] **Step 4: Add the request, controller and routes**

`OperatorSettingsRequest` validates all four as `['nullable', 'string', 'max:255']`, except `agency_address` which is `max:500`. The controller mirrors `Admin/BrandingController`: `edit()` returns `inertia('Admin/Operator/Edit', [...])`, `update()` assigns `?: null` per field, saves, redirects back with a flash. Register both routes inside the existing `super_admin` group.

- [ ] **Step 5: Build the admin screen**

`Admin/Operator/Edit.tsx` using `FormSection`, `Field`, `Input` and `Button` from `@/Components/ui`, following the Branding screen. Strings from `admin.operator.*` in all four locales. Add the entry to the admin navigation alongside Branding.

- [ ] **Step 6: Run the tests and the formatter**

Run: `ddev php artisan test --filter=OperatorSettingsTest` → PASS
Run: `ddev ./vendor/bin/pint`

- [ ] **Step 7: Commit**

```bash
git add app/Settings app/Http/Controllers/Admin/OperatorController.php app/Http/Requests/Admin database/settings routes/web.php resources/js/Pages/Admin/Operator lang tests/Feature/Admin/OperatorSettingsTest.php
git commit -m "feat(admin): store agency and hosting provider details"
```

---

### Task 3: Client details on the project

Maintained by super admins only. `ProjectRole::Admin` deliberately does not grant access — these identify the agency's client, not the project's working configuration.

**Files:**
- Create: `database/migrations/2026_10_18_000001_add_client_details_to_projects_table.php`
- Modify: `app/Models/Project.php`
- Modify: `app/Http/Requests/ProjectRequest.php`
- Modify: `app/Http/Controllers/Admin/ProjectController.php`
- Modify: `resources/js/Pages/Admin/Projects/Edit.tsx`, `resources/js/Pages/Admin/Projects/Create.tsx`
- Modify: `lang/{de,en,fr,nl}/admin.php`
- Test: `tests/Feature/Admin/ProjectClientDetailsTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `projects.client_name` (string, nullable) and `projects.client_address` (string, nullable), both `$fillable` and both in the model's `getActivitylogOptions()->logOnly([...])` list.

- [ ] **Step 1: Write the failing test**

```php
public function test_a_super_admin_can_store_client_details_on_a_project(): void
public function test_a_project_admin_cannot_reach_the_admin_project_screen(): void
public function test_changing_the_client_name_is_written_to_the_activity_log(): void
```

Assert the stored values round-trip, that a user who is `ProjectRole::Admin` on the project but not `super_admin` gets 403 from `route('app.admin.projects.edit', ...)`, and that the activity log records the change.

- [ ] **Step 2: Run the test and confirm it fails**

Run: `ddev php artisan test --filter=ProjectClientDetailsTest`
Expected: FAIL — unknown column `client_name`.

- [ ] **Step 3: Add the migration and extend the model**

Both columns nullable, `after('name')`. Add to `$fillable` and to the activity log's `logOnly` list.

- [ ] **Step 4: Extend validation, controller and the two admin screens**

`ProjectRequest`: `client_name` → `['nullable', 'string', 'max:255']`, `client_address` → `['nullable', 'string', 'max:500']`. Pass both from `ProjectController::edit()` into the Inertia props and render them in a "Client" `FormSection` on both screens.

- [ ] **Step 5: Run the tests and the formatter**

Run: `ddev php artisan test --filter=ProjectClientDetailsTest` → PASS
Run: `ddev ./vendor/bin/pint`

- [ ] **Step 6: Commit**

```bash
git add database/migrations app/Models/Project.php app/Http/Requests/ProjectRequest.php app/Http/Controllers/Admin/ProjectController.php resources/js/Pages/Admin/Projects lang tests/Feature/Admin/ProjectClientDetailsTest.php
git commit -m "feat(admin): record the client a project is run for"
```

---

### Task 4: Processing snapshot

Reads what a project actually does. Pure read, no formatting, no translation.

**Files:**
- Create: `app/Support/Privacy/ProcessingSnapshot.php`
- Create: `app/Support/Privacy/SiteProcessing.php`
- Test: `tests/Feature/Privacy/ProcessingSnapshotTest.php`

**Interfaces:**
- Consumes: `App\Models\Project`, `App\Services\GeoIpService`.
- Produces:

```php
final class SiteProcessing
{
    public function __construct(
        public readonly string $domain,
        public readonly TrackingMode $trackingMode,
        public readonly ConsentMode $consentMode,
        public readonly bool $respectsDnt,
        public readonly bool $tracksOutboundLinks,
        public readonly bool $tracksFileDownloads,
        public readonly bool $tracksSiteSearch,
        public readonly int $retentionDays,
    ) {}
}

final class ProcessingSnapshot
{
    public static function for(Project $project): self;

    /** @return list<SiteProcessing> */
    public function sites(): array;
    public function linkCount(): int;
    /** @return list<PixelProvider> */
    public function pixelProviders(): array;
    public function pixelLinkCount(): int;
    public function clickRetentionDays(): int;
    public function geolocationAvailable(): bool;
    public function usesCookies(): bool;          // any site in cookie or hybrid mode
    public function hasConsentlessCookies(): bool; // any site: cookie mode + consent_mode immediate

    /** Shape handed to the frontend as the `snapshot` prop (Task 8). */
    public function toArray(): array;
}
```

`retentionDays` resolves `Site::$retention_days` and falls back to `config('analytics.retention_months') * 30`. `clickRetentionDays()` is `config('statistics.retention_months') * 30`. `pixelProviders()` returns distinct providers of pixels that are attached to at least one link in the project. `geolocationAvailable()` reports whether the GeoLite2 database is present.

- [ ] **Step 1: Write the failing test**

```php
public function test_it_lists_each_site_with_its_modes(): void
public function test_a_soft_deleted_site_is_not_reported(): void
public function test_a_soft_deleted_link_is_not_counted(): void
public function test_sites_keep_their_own_retention_when_they_differ(): void
public function test_a_pixel_attached_to_no_link_is_not_reported_as_active(): void
public function test_cookie_mode_with_immediate_consent_is_flagged(): void
public function test_a_project_with_no_sites_reports_an_empty_site_list(): void
public function test_to_array_exposes_every_site_and_the_pixel_providers(): void
```

`test_sites_keep_their_own_retention_when_they_differ` builds one site with `retention_days = 90` and one with `null`, and asserts 90 and `config('analytics.retention_months') * 30` respectively (Review Focus 3). `test_a_soft_deleted_site_is_not_reported` and `test_a_soft_deleted_link_is_not_counted` cover Review Focus 4.

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `ddev php artisan test --filter=ProcessingSnapshotTest`
Expected: FAIL — class `ProcessingSnapshot` not found.

- [ ] **Step 3: Implement both classes**

Eloquent's default scopes already exclude soft-deleted rows; do not add `withTrashed()`. Resolve `GeoIpService` from the container rather than constructing it.

- [ ] **Step 4: Run the tests and the formatter**

Run: `ddev php artisan test --filter=ProcessingSnapshotTest` → PASS
Run: `ddev ./vendor/bin/pint`

- [ ] **Step 5: Commit**

```bash
git add app/Support/Privacy tests/Feature/Privacy/ProcessingSnapshotTest.php
git commit -m "feat(privacy): describe what a project actually processes"
```

---

### Task 5: Notice composer and the German catalog

**Files:**
- Create: `app/Enums/ControllerRole.php`
- Create: `app/Enums/LegalBasis.php`
- Create: `app/Support/Privacy/NoticeOptions.php`
- Create: `app/Support/Privacy/NoticeSection.php`
- Create: `app/Support/Privacy/NoticeComposer.php`
- Create: `lang/de/privacy_notice.php`
- Test: `tests/Feature/Privacy/NoticeComposerTest.php`

**Interfaces:**
- Consumes: `ProcessingSnapshot`, `SiteProcessing` (Task 4).
- Produces:

```php
// app/Enums/ControllerRole.php, app/Enums/LegalBasis.php — each with the
// `label(): string` method the codebase's other enums carry.
enum ControllerRole: string { case OwnCompany = 'own_company'; case Client = 'client'; }
enum LegalBasis: string { case LegitimateInterest = 'legitimate_interest'; case Consent = 'consent'; }

final class NoticeOptions
{
    public function __construct(
        public readonly ControllerRole $role,
        public readonly LegalBasis $legalBasis,
        public readonly string $locale,
        public readonly ?string $controllerName,
        public readonly ?string $controllerAddress,
        public readonly ?string $agencyName,
        public readonly ?string $agencyAddress,
        public readonly ?string $hostingProvider,
        public readonly ?string $hostingLocation,
        /** @var list<string> keys of activities the user switched off */
        public readonly array $disabledActivities,
    ) {}
}

final class NoticeSection
{
    public function __construct(
        public readonly string $heading,
        /** @var list<string> */
        public readonly array $paragraphs,
    ) {}
}

final class NoticeComposer
{
    /** @return list<NoticeSection> */
    public function compose(ProcessingSnapshot $snapshot, NoticeOptions $options): array;

    /** @return list<string> placeholder labels that appeared in the output */
    public function placeholders(): array;

    public function toPlainText(array $sections): string;
    public function toHtml(array $sections): string;   // <h3> + <p>, every value escaped
}
```

Section order is fixed by the spec: controller; processor chain; what is processed; IP handling; cookies; retargeting pixels; legal basis; retention; data subject rights.

Rules the tests pin:
- The pixel section **never** cites a legal basis and never contains a consent claim. It states that clicking a short link embeds services by the named providers, which set their own cookies and transmit to the provider, in most cases to the United States.
- `LegalBasis` governs link clicks and site analytics only.
- When `geolocationAvailable()` is false the geolocation passage is omitted.
- A section whose activity is absent or switched off is omitted entirely, headings included.
- Missing party details emit a visible placeholder (`[Name des Verantwortlichen]`) and are recorded by `placeholders()`.
- Data subject rights state plainly that in cookieless mode there is no identifier a visitor could present and the stored data is anonymous after the 48-hour salt expiry, so Articles 15 and 17 do not apply to it. In cookie and hybrid mode they name the `mx_vid` value as the identifier a visitor supplies to exercise them.
- The rights section always carries the right to complain to a supervisory authority (Art. 77), regardless of mode or configuration.

- [ ] **Step 1: Write the failing test**

```php
public function test_the_pixel_section_never_claims_consent(): void
public function test_the_pixel_section_names_every_provider_in_use(): void
public function test_a_project_without_pixels_has_no_pixel_section(): void
public function test_a_project_with_no_sites_omits_the_analytics_section(): void
public function test_a_project_with_no_links_omits_the_click_section(): void
public function test_a_cookieless_only_project_has_no_cookie_section(): void
public function test_the_geolocation_passage_is_omitted_when_the_database_is_absent(): void
public function test_a_missing_controller_name_becomes_a_visible_placeholder(): void
public function test_both_retention_periods_appear_when_sites_differ(): void
public function test_the_rights_section_always_names_the_right_to_complain(): void
public function test_html_output_escapes_party_details(): void
```

`test_html_output_escapes_party_details` passes `controllerName` of `Müller <GmbH> & Co.` and asserts the HTML contains `&lt;GmbH&gt;` and `&amp;` and not the raw `<GmbH>` (Review Focus 1). `test_a_project_with_no_sites_omits_the_analytics_section` and its link counterpart cover Review Focus 2.

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `ddev php artisan test --filter=NoticeComposerTest`
Expected: FAIL — class `NoticeComposer` not found.

- [ ] **Step 3: Implement the three classes**

Compose sections by reading translation keys under `privacy_notice.*` with `trans(..., $replacements, $options->locale)` so the requested locale wins over the app locale. `toHtml()` escapes every interpolated value with `e()`; `toPlainText()` joins heading and paragraphs with blank lines.

- [ ] **Step 4: Write `lang/de/privacy_notice.php`**

The German legal prose for all nine sections, following the structure and the wording rules above. This is the reference text the other three locales are translated from.

- [ ] **Step 5: Run the tests and the formatter**

Run: `ddev php artisan test --filter=NoticeComposerTest` → PASS
Run: `ddev ./vendor/bin/pint`

- [ ] **Step 6: Commit**

```bash
git add app/Support/Privacy lang/de/privacy_notice.php tests/Feature/Privacy/NoticeComposerTest.php
git commit -m "feat(privacy): compose a GDPR notice from a project's configuration"
```

---

### Task 6: English, French and Dutch catalogs

**Files:**
- Create: `lang/en/privacy_notice.php`, `lang/fr/privacy_notice.php`, `lang/nl/privacy_notice.php`
- Test: `tests/Feature/Privacy/NoticeTranslationsTest.php`

**Interfaces:**
- Consumes: `lang/de/privacy_notice.php` (Task 5) as the reference key set and reference text.
- Produces: nothing new in code.

- [ ] **Step 1: Write the failing test**

```php
public function test_every_locale_defines_the_same_notice_keys(): void
public function test_every_locale_uses_the_same_placeholder_tokens_per_key(): void
public function test_a_notice_can_be_composed_in_each_locale(): void
```

The second test extracts `:token` occurrences per key and asserts the sets match German — a translation that drops `:provider` would silently render a sentence with a missing name. The third composes a full notice per locale against a fixture project and asserts the output is non-empty and contains no untranslated key paths (no substring `privacy_notice.`).

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `ddev php artisan test --filter=NoticeTranslationsTest`
Expected: FAIL — `lang/en/privacy_notice.php` does not exist.

- [ ] **Step 3: Write the three catalogs**

Translate from the German reference. Keep the legal substance identical; adapt only terminology that differs by jurisdiction (for example TDDDG is German law — in the other locales refer to Article 5(3) of the ePrivacy Directive and its national implementation rather than naming a German statute).

- [ ] **Step 4: Run the tests and the formatter**

Run: `ddev php artisan test --filter=NoticeTranslationsTest` → PASS
Run: `ddev ./vendor/bin/pint`

- [ ] **Step 5: Commit**

```bash
git add lang tests/Feature/Privacy/NoticeTranslationsTest.php
git commit -m "feat(privacy): translate the notice into English, French and Dutch"
```

---

### Task 7: Warnings

**Files:**
- Create: `app/Support/Privacy/NoticeWarnings.php`
- Modify: `lang/{de,en,fr,nl}/privacy_notice.php` (a `warnings.*` block)
- Test: `tests/Feature/Privacy/NoticeWarningsTest.php`

**Interfaces:**
- Consumes: `ProcessingSnapshot` (Task 4), `NoticeOptions` (Task 5).
- Produces:

```php
final class NoticeWarnings
{
    /** @return list<array{key: string, message: string}> */
    public static function for(ProcessingSnapshot $snapshot, NoticeOptions $options): array;
}
```

Three rules, in this order:
1. `pixels_without_consent` — pixels are in use. § 25 TDDDG requires consent before those scripts run; they currently fire on every click.
2. `cookie_without_consent` — a site runs `cookie` mode with `consent_mode: immediate`, so a 365-day cookie is set without consent. Names the affected domains.
3. `missing_party_details` — mandatory party fields are empty. Names which.

Unlike the notice, these are blunt: they are shown in the UI, not published by the customer.

- [ ] **Step 1: Write the failing test**

```php
public function test_active_pixels_produce_a_consent_warning(): void
public function test_cookie_mode_with_immediate_consent_names_the_affected_domains(): void
public function test_missing_party_details_are_listed(): void
public function test_a_fully_configured_cookieless_project_without_pixels_has_no_warnings(): void
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `ddev php artisan test --filter=NoticeWarningsTest`
Expected: FAIL — class `NoticeWarnings` not found.

- [ ] **Step 3: Implement the class and add the `warnings.*` strings to all four catalogs**

- [ ] **Step 4: Run the tests and the formatter**

Run: `ddev php artisan test --filter=NoticeWarningsTest` → PASS
Run: `ddev ./vendor/bin/pint`

- [ ] **Step 5: Commit**

```bash
git add app/Support/Privacy/NoticeWarnings.php lang tests/Feature/Privacy/NoticeWarningsTest.php
git commit -m "feat(privacy): warn about consentless pixels and cookies"
```

---

### Task 8: Endpoint and page props

**Files:**
- Create: `app/Http/Controllers/PrivacyNoticeController.php`
- Create: `app/Http/Requests/PrivacyNoticeRequest.php`
- Modify: `app/Http/Controllers/DocsController.php`
- Modify: `routes/web.php` (project group, next to the existing privacy route)
- Test: `tests/Feature/Privacy/PrivacyNoticeEndpointTest.php`, `tests/Feature/Docs/DataPrivacyPageTest.php`

**Interfaces:**
- Consumes: everything from Tasks 2–7.
- Produces: route `app.project.docs.privacy.notice` (GET `/project/{project}/docs/data-privacy/notice`) returning JSON `{sections: [{heading, paragraphs}], text: string, html: string, placeholders: string[], warnings: [{key, message}]}`.

`DocsController::dataPrivacy` gains props: `snapshot` (the serialised `ProcessingSnapshot`), `parties` (agency and hosting from `OperatorSettings`, client from the project), `canEditClient` (`$request->user()->super_admin`), and `defaults` (`role` — client when the project carries client details, own company otherwise; `locale` — the current UI locale; `legalBasis` — legitimate interest).

- [ ] **Step 1: Write the failing test**

```php
public function test_a_member_can_request_the_notice(): void
public function test_the_notice_is_returned_in_the_requested_language_regardless_of_ui_locale(): void
public function test_a_non_member_cannot_request_another_projects_notice(): void
public function test_an_unknown_locale_is_rejected(): void
public function test_the_page_exposes_the_snapshot_and_party_defaults(): void
public function test_a_non_super_admin_sees_can_edit_client_false(): void
```

`test_the_notice_is_returned_in_the_requested_language_regardless_of_ui_locale` sets the session locale to `de`, requests `locale=fr`, and asserts the body matches the French catalog, not the German one (Review Focus 5). Build request URLs with `route()`, never bare paths — the test suite's `APP_DOMAIN` differs from `APP_URL`'s host.

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `ddev php artisan test --filter=PrivacyNoticeEndpointTest`
Expected: FAIL — route `app.project.docs.privacy.notice` is not defined.

- [ ] **Step 3: Implement the request, controller and route**

`PrivacyNoticeRequest` validates `role` and `legal_basis` against their enums, `locale` against `['de','en','fr','nl']`, the party strings as nullable, and `disabled_activities` as an array of strings. The route sits inside the existing `auth` + `ProjectBindingMiddleware` group, so tenant access is already enforced; resolve the project from `$request->get('project')`.

- [ ] **Step 4: Extend `DocsController::dataPrivacy` with the new props**

- [ ] **Step 5: Run the tests and the formatter**

Run: `ddev php artisan test --filter=PrivacyNoticeEndpointTest` → PASS
Run: `ddev php artisan test --filter=DataPrivacyPageTest` → PASS
Run: `ddev ./vendor/bin/pint`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers app/Http/Requests/PrivacyNoticeRequest.php routes/web.php tests/Feature/Privacy/PrivacyNoticeEndpointTest.php tests/Feature/Docs/DataPrivacyPageTest.php
git commit -m "feat(privacy): serve the generated notice from a project endpoint"
```

---

### Task 9: The generator UI

**Files:**
- Create: `resources/js/Pages/Docs/partials/NoticeGenerator.tsx`
- Create: `resources/js/Pages/Docs/partials/NoticeForm.tsx`
- Create: `resources/js/Pages/Docs/partials/NoticePreview.tsx`
- Create: `resources/js/lib/privacyNotice.ts`
- Create: `resources/js/lib/privacyNotice.test.ts`
- Modify: `resources/js/Pages/Docs/DataPrivacy.tsx`
- Modify: `resources/js/types/index.d.ts`
- Modify: `lang/{de,en,fr,nl}/docs.php` (a `privacy.generator.*` block)

**Interfaces:**
- Consumes: the props and endpoint from Task 8.
- Produces: `buildNoticeQuery(state: GeneratorState): Record<string, string | string[]>` in `privacyNotice.ts`, converting form state into the endpoint's query parameters.

Layout, settled from the mockup: two columns, form left, preview right. The generator mounts **below** the six existing explanatory sections, which stay untouched.

Form, top to bottom: role switch (own company / client project); controller name and address (read-only unless `canEditClient`); a read-only processor summary from `parties`; the activity toggles pre-filled from `snapshot`, with the pixel row visually flagged; legal basis; language. Warnings render above the preview. The preview carries two copy buttons — plain text and HTML — reusing the `copied` pattern already in `DataPrivacy.tsx`.

- [ ] **Step 1: Write the failing frontend test**

```ts
describe('buildNoticeQuery', () => {
  it('maps form state onto the endpoint parameters', () => {})
  it('omits party fields that are empty', () => {})
  it('sends every switched-off activity', () => {})
})
```

- [ ] **Step 2: Run the test and confirm it fails**

Run: `ddev npm run test:js -- privacyNotice`
Expected: FAIL — module not found.

- [ ] **Step 3: Implement `buildNoticeQuery` and the `GeneratorState` type**

- [ ] **Step 4: Build the three components and mount them in `DataPrivacy.tsx`**

Fetch the endpoint on state change, debounced at 300 ms, aborting the previous request so a slow response cannot overwrite a newer one. Build the URL with `route('app.project.docs.privacy.notice', { project: currentProject.id })`. Use `@/Components/ui` and semantic tokens throughout; all labels from `docs.privacy.generator.*` in four locales.

- [ ] **Step 5: Verify**

Run: `ddev npm run test:js` → PASS
Run: `ddev npm run build` → PASS (TypeScript check)
Run: `ddev composer test` → PASS
Run: `ddev ./vendor/bin/pint`

- [ ] **Step 6: Commit**

```bash
git add resources/js lang
git commit -m "feat(privacy): add the notice generator to the data privacy page"
```
