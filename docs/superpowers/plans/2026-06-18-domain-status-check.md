# Domain Status Check Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a CNAME setup info box and a three-stage (DNS / reachable / SSL) status check to the domain section, surfaced in the UI and refreshed on-demand and on a schedule.

**Architecture:** Domains gain nullable status columns. A `DomainStatusChecker` service runs three independent, fault-isolated checks behind injectable DNS/cert seams (so it is unit-testable without network). A queued `CheckDomainStatusJob` is the single write path, dispatched on create, every 15 minutes by the scheduler, and synchronously by an on-demand controller endpoint. A public `/.well-known/marketix` signature route lets the reachability check prove the Traefik→app path. React shows the info box and per-domain status.

**Tech Stack:** Laravel 13 / PHP 8.3, React 19 + TypeScript + Inertia.js, MariaDB (DDEV), PHPUnit-style tests, Tailwind, lucide-react icons.

## Global Constraints

- Run all php/composer/npm/artisan via DDEV: `ddev php`, `ddev composer`, `ddev npm`, `ddev artisan`, `ddev exec`. Never bare `php`/`npm`.
- Tests are **PHPUnit class style** (`extends Tests\TestCase`, `use RefreshDatabase`, `public function test_*(): void`). Not Pest.
- Run the full suite with `ddev composer run test`.
- Frontend gate is `ddev npm run build` (TS check + Vite). `ddev npm run lint` is known-broken — do not use it.
- Ziggy `route()` calls always use object params: `route('name', { project: id, domain: id })` — never a bare value.
- `Domain` uses `HasUlids` + `SoftDeletes`; IDs are ULID strings.
- The app domain comes from `config('app.domain')` (env `APP_DOMAIN`, default `localhost`).
- The reachability signature is the literal JSON `{"app":"marketix"}` at path `/.well-known/marketix`.
- Status booleans semantics: `true` = pass, `false` = fail, `null` = not yet checked / unknown.

---

### Task 1: Data layer — migration, model fields, status accessor, factory

**Files:**
- Create: `database/migrations/2026_06_18_120000_add_status_to_domains_table.php`
- Modify: `app/Models/Domain.php`
- Create: `database/factories/DomainFactory.php`
- Test: `tests/Unit/DomainStatusTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `domains` columns `dns_ok bool null`, `reachable_ok bool null`, `ssl_ok bool null`, `check_details json null`, `last_checked_at timestamp null`.
  - `Domain` model casts those columns; appends a derived `status` string attribute returning `'healthy' | 'error' | 'pending'`.
  - `Domain::factory()` available with `name`, status columns default `null`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DomainStatusTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Domain;
use Tests\TestCase;

class DomainStatusTest extends TestCase
{
    public function test_status_is_pending_when_unchecked(): void
    {
        $domain = new Domain(['name' => 'go.example.com']);

        $this->assertSame('pending', $domain->status);
    }

    public function test_status_is_healthy_when_all_checks_pass(): void
    {
        $domain = new Domain([
            'name' => 'go.example.com',
            'dns_ok' => true,
            'reachable_ok' => true,
            'ssl_ok' => true,
        ]);

        $this->assertSame('healthy', $domain->status);
    }

    public function test_status_is_error_when_any_check_fails(): void
    {
        $domain = new Domain([
            'name' => 'go.example.com',
            'dns_ok' => true,
            'reachable_ok' => false,
            'ssl_ok' => null,
        ]);

        $this->assertSame('error', $domain->status);
    }

    public function test_status_is_pending_when_partially_checked_without_failure(): void
    {
        $domain = new Domain([
            'name' => 'go.example.com',
            'dns_ok' => true,
            'reachable_ok' => null,
            'ssl_ok' => null,
        ]);

        $this->assertSame('pending', $domain->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --filter=DomainStatusTest`
Expected: FAIL — `status` attribute is null / column not in fillable.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_06_18_120000_add_status_to_domains_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('dns_ok')->nullable()->after('redirect_not_found');
            $table->boolean('reachable_ok')->nullable()->after('dns_ok');
            $table->boolean('ssl_ok')->nullable()->after('reachable_ok');
            $table->json('check_details')->nullable()->after('ssl_ok');
            $table->timestamp('last_checked_at')->nullable()->after('check_details');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['dns_ok', 'reachable_ok', 'ssl_ok', 'check_details', 'last_checked_at']);
        });
    }
};
```

- [ ] **Step 4: Update the Domain model**

Edit `app/Models/Domain.php` to add `HasFactory`, the new fillable fields, casts, the appended `status`, and the accessor. Full file:

```php
<?php

namespace App\Models;

use App\Observers\DomainObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([DomainObserver::class])]
class Domain extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'project_id',
        'name',
        'redirect_root',
        'redirect_not_found',
        'dns_ok',
        'reachable_ok',
        'ssl_ok',
        'check_details',
        'last_checked_at',
    ];

    protected $casts = [
        'dns_ok' => 'boolean',
        'reachable_ok' => 'boolean',
        'ssl_ok' => 'boolean',
        'check_details' => 'array',
        'last_checked_at' => 'datetime',
    ];

    protected $appends = ['status'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getStatusAttribute(): string
    {
        if ($this->dns_ok && $this->reachable_ok && $this->ssl_ok) {
            return 'healthy';
        }

        if ($this->dns_ok === false || $this->reachable_ok === false || $this->ssl_ok === false) {
            return 'error';
        }

        return 'pending';
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/DomainFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->domainName(),
            'redirect_root' => null,
            'redirect_not_found' => null,
            'dns_ok' => null,
            'reachable_ok' => null,
            'ssl_ok' => null,
            'check_details' => null,
            'last_checked_at' => null,
        ];
    }
}
```

- [ ] **Step 6: Run migration and test**

Run: `ddev exec php artisan migrate && ddev exec php artisan test --filter=DomainStatusTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Domain.php database/migrations/2026_06_18_120000_add_status_to_domains_table.php database/factories/DomainFactory.php tests/Unit/DomainStatusTest.php
git commit -m "feat(domains): add status columns, derived status accessor, factory"
```

---

### Task 2: App signature route

**Files:**
- Modify: `routes/web.php` (add near the top, before `Route::fallback(...)` at line ~157)
- Test: `tests/Feature/DomainSignatureRouteTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `GET /.well-known/marketix` → `200` JSON `{"app":"marketix"}`, public (no auth), named `marketix.signature`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DomainSignatureRouteTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class DomainSignatureRouteTest extends TestCase
{
    public function test_signature_route_returns_marker(): void
    {
        $this->get('/.well-known/marketix')
            ->assertOk()
            ->assertExactJson(['app' => 'marketix']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --filter=DomainSignatureRouteTest`
Expected: FAIL — route falls through to `RedirectController@handle` (likely a redirect/404, not the JSON).

- [ ] **Step 3: Add the route**

Edit `routes/web.php`. Immediately after the opening `use` block / before the first route group (anywhere above `Route::fallback(...)`), add:

```php
// Public marker used by the domain reachability check to confirm the
// Traefik -> app path resolves to this application.
Route::get('/.well-known/marketix', fn () => response()->json(['app' => 'marketix']))
    ->name('marketix.signature');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --filter=DomainSignatureRouteTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php tests/Feature/DomainSignatureRouteTest.php
git commit -m "feat(domains): add /.well-known/marketix signature route"
```

---

### Task 3: DNS and certificate seams

**Files:**
- Create: `app/Services/DnsResolver.php` (interface)
- Create: `app/Services/SystemDnsResolver.php`
- Create: `app/Services/CertificateReader.php` (interface)
- Create: `app/Services/SystemCertificateReader.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Consumes: nothing.
- Produces:
  - `interface DnsResolver { public function resolveIps(string $host): array; }` — returns a list of IPv4 strings (`[]` on failure).
  - `interface CertificateReader { public function read(string $host, int $port = 443): ?array; }` — returns `['valid_to' => int, 'cn' => string, 'san' => string[]]` or `null`.
  - Container bindings: `DnsResolver` → `SystemDnsResolver`, `CertificateReader` → `SystemCertificateReader`.

> These wrap real network/system calls and are not unit-tested directly (they are the seams that let Task 4 be tested with fakes). No test step here; verification is "code compiles and binds", confirmed by Task 4's suite.

- [ ] **Step 1: Create the DnsResolver interface**

Create `app/Services/DnsResolver.php`:

```php
<?php

namespace App\Services;

interface DnsResolver
{
    /**
     * Resolve a hostname to its list of IPv4 addresses.
     *
     * @return string[] Empty array if the host cannot be resolved.
     */
    public function resolveIps(string $host): array;
}
```

- [ ] **Step 2: Create the SystemDnsResolver**

Create `app/Services/SystemDnsResolver.php`:

```php
<?php

namespace App\Services;

class SystemDnsResolver implements DnsResolver
{
    public function resolveIps(string $host): array
    {
        $ips = gethostbynamel($host);

        return $ips === false ? [] : $ips;
    }
}
```

- [ ] **Step 3: Create the CertificateReader interface**

Create `app/Services/CertificateReader.php`:

```php
<?php

namespace App\Services;

interface CertificateReader
{
    /**
     * Read the TLS certificate served at host:port.
     *
     * @return array{valid_to: int, cn: string, san: string[]}|null Null if no cert could be read.
     */
    public function read(string $host, int $port = 443): ?array;
}
```

- [ ] **Step 4: Create the SystemCertificateReader**

Create `app/Services/SystemCertificateReader.php`:

```php
<?php

namespace App\Services;

class SystemCertificateReader implements CertificateReader
{
    public function read(string $host, int $port = 443): ?array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (! $cert) {
            return null;
        }

        $parsed = openssl_x509_parse($cert);

        $san = [];
        if (! empty($parsed['extensions']['subjectAltName'])) {
            foreach (explode(',', $parsed['extensions']['subjectAltName']) as $entry) {
                $entry = trim($entry);
                if (str_starts_with($entry, 'DNS:')) {
                    $san[] = substr($entry, 4);
                }
            }
        }

        return [
            'valid_to' => $parsed['validTo_time_t'] ?? 0,
            'cn' => $parsed['subject']['CN'] ?? '',
            'san' => $san,
        ];
    }
}
```

- [ ] **Step 5: Bind the implementations**

Edit `app/Providers/AppServiceProvider.php` `register()` method. Add the imports and bindings:

```php
use App\Services\CertificateReader;
use App\Services\DnsResolver;
use App\Services\SystemCertificateReader;
use App\Services\SystemDnsResolver;
```

Inside `register()`, after `URL::forceScheme('https');`:

```php
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
        $this->app->bind(CertificateReader::class, SystemCertificateReader::class);
```

- [ ] **Step 6: Verify it compiles**

Run: `ddev exec php artisan config:clear && ddev exec php -l app/Services/SystemCertificateReader.php`
Expected: `No syntax errors detected`.

- [ ] **Step 7: Commit**

```bash
git add app/Services/DnsResolver.php app/Services/SystemDnsResolver.php app/Services/CertificateReader.php app/Services/SystemCertificateReader.php app/Providers/AppServiceProvider.php
git commit -m "feat(domains): add DNS resolver and certificate reader seams"
```

---

### Task 4: DomainStatusChecker service

**Files:**
- Create: `app/Services/DomainStatusChecker.php`
- Test: `tests/Unit/DomainStatusCheckerTest.php`

**Interfaces:**
- Consumes: `DnsResolver`, `CertificateReader` (Task 3); `config('app.domain')`; `Illuminate\Support\Facades\Http`.
- Produces:
  - `class DomainStatusChecker { public function __construct(DnsResolver $dns, CertificateReader $certs) {} public function check(Domain $domain): array; }`
  - `check()` returns `['dns_ok' => bool, 'reachable_ok' => bool, 'ssl_ok' => bool, 'check_details' => array]`.
  - `check()` is overridable (not `final`) so later tasks can subclass it as a fake.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DomainStatusCheckerTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Domain;
use App\Services\CertificateReader;
use App\Services\DnsResolver;
use App\Services\DomainStatusChecker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainStatusCheckerTest extends TestCase
{
    private function resolver(array $map): DnsResolver
    {
        return new class($map) implements DnsResolver {
            public function __construct(private array $map) {}

            public function resolveIps(string $host): array
            {
                return $this->map[$host] ?? [];
            }
        };
    }

    private function certReader(?array $cert): CertificateReader
    {
        return new class($cert) implements CertificateReader {
            public function __construct(private ?array $cert) {}

            public function read(string $host, int $port = 443): ?array
            {
                return $this->cert;
            }
        };
    }

    public function test_all_checks_pass(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['https://go.example.com/.well-known/marketix' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertTrue($result['dns_ok']);
        $this->assertTrue($result['reachable_ok']);
        $this->assertTrue($result['ssl_ok']);
    }

    public function test_dns_fails_when_ips_disjoint(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['9.9.9.9'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['dns_ok']);
    }

    public function test_reachable_fails_on_wrong_signature(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'someone-else'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->addYear()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['reachable_ok']);
    }

    public function test_ssl_fails_when_expired(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(['valid_to' => Carbon::now()->subDay()->timestamp, 'cn' => 'go.example.com', 'san' => ['go.example.com']]),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['ssl_ok']);
    }

    public function test_ssl_fails_when_no_certificate(): void
    {
        config(['app.domain' => 'app.test']);
        Http::fake(['*' => Http::response(['app' => 'marketix'], 200)]);

        $checker = new DomainStatusChecker(
            $this->resolver(['go.example.com' => ['1.2.3.4'], 'app.test' => ['1.2.3.4']]),
            $this->certReader(null),
        );

        $result = $checker->check(new Domain(['name' => 'go.example.com']));

        $this->assertFalse($result['ssl_ok']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --filter=DomainStatusCheckerTest`
Expected: FAIL — `DomainStatusChecker` class does not exist.

- [ ] **Step 3: Write the service**

Create `app/Services/DomainStatusChecker.php`:

```php
<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Http;
use Throwable;

class DomainStatusChecker
{
    public function __construct(
        private DnsResolver $dns,
        private CertificateReader $certs,
    ) {}

    /**
     * @return array{dns_ok: bool, reachable_ok: bool, ssl_ok: bool, check_details: array}
     */
    public function check(Domain $domain): array
    {
        $details = [];

        return [
            'dns_ok' => $this->checkDns($domain->name, $details),
            'ssl_ok' => $this->checkSsl($domain->name, $details),
            'reachable_ok' => $this->checkReachable($domain->name, $details),
            'check_details' => $details,
        ];
    }

    private function checkDns(string $host, array &$details): bool
    {
        try {
            $domainIps = $this->dns->resolveIps($host);
            $appIps = $this->dns->resolveIps(config('app.domain'));
            $details['dns'] = ['domain_ips' => $domainIps, 'app_ips' => $appIps];

            return count(array_intersect($domainIps, $appIps)) > 0;
        } catch (Throwable $e) {
            $details['dns'] = ['error' => $e->getMessage()];

            return false;
        }
    }

    private function checkSsl(string $host, array &$details): bool
    {
        try {
            $cert = $this->certs->read($host);

            if ($cert === null) {
                $details['ssl'] = ['error' => 'Could not read certificate'];

                return false;
            }

            $notExpired = ($cert['valid_to'] ?? 0) > now()->timestamp;
            $names = array_filter(array_merge([$cert['cn'] ?? ''], $cert['san'] ?? []));
            $covers = $this->certCoversHost($host, $names);
            $details['ssl'] = ['expires_at' => $cert['valid_to'] ?? 0, 'names' => array_values($names)];

            return $notExpired && $covers;
        } catch (Throwable $e) {
            $details['ssl'] = ['error' => $e->getMessage()];

            return false;
        }
    }

    private function checkReachable(string $host, array &$details): bool
    {
        try {
            $response = Http::timeout(5)->get("https://{$host}/.well-known/marketix");
            $details['reachable'] = ['status' => $response->status()];

            return $response->ok() && $response->json('app') === 'marketix';
        } catch (Throwable $e) {
            $details['reachable'] = ['error' => $e->getMessage()];

            return false;
        }
    }

    /**
     * @param  string[]  $names
     */
    private function certCoversHost(string $host, array $names): bool
    {
        foreach ($names as $name) {
            if (strcasecmp($name, $host) === 0) {
                return true;
            }

            if (str_starts_with($name, '*.')) {
                $suffix = substr($name, 1); // ".example.com"
                if (str_ends_with(strtolower($host), strtolower($suffix))) {
                    return true;
                }
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --filter=DomainStatusCheckerTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/DomainStatusChecker.php tests/Unit/DomainStatusCheckerTest.php
git commit -m "feat(domains): add DomainStatusChecker service"
```

---

### Task 5: CheckDomainStatusJob (single write path)

**Files:**
- Create: `app/Jobs/CheckDomainStatusJob.php`
- Test: `tests/Feature/CheckDomainStatusJobTest.php`

**Interfaces:**
- Consumes: `DomainStatusChecker` (resolved from container in `handle()`), `Domain`.
- Produces:
  - `class CheckDomainStatusJob implements ShouldQueue { public function __construct(public Domain $domain) {} public function handle(DomainStatusChecker $checker): void; }`
  - On `handle()`, persists `dns_ok`, `reachable_ok`, `ssl_ok`, `check_details`, `last_checked_at = now()` onto the domain.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CheckDomainStatusJobTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\CheckDomainStatusJob;
use App\Models\Domain;
use App\Models\Project;
use App\Services\DomainStatusChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckDomainStatusJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_persists_checker_results(): void
    {
        $this->app->instance(DomainStatusChecker::class, new class extends DomainStatusChecker {
            public function __construct() {}

            public function check(Domain $domain): array
            {
                return [
                    'dns_ok' => true,
                    'reachable_ok' => false,
                    'ssl_ok' => true,
                    'check_details' => ['dns' => ['domain_ips' => ['1.2.3.4']]],
                ];
            }
        });

        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);

        (new CheckDomainStatusJob($domain))->handle(app(DomainStatusChecker::class));

        $domain->refresh();
        $this->assertTrue($domain->dns_ok);
        $this->assertFalse($domain->reachable_ok);
        $this->assertTrue($domain->ssl_ok);
        $this->assertNotNull($domain->last_checked_at);
        $this->assertSame(['1.2.3.4'], $domain->check_details['dns']['domain_ips']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --filter=CheckDomainStatusJobTest`
Expected: FAIL — `CheckDomainStatusJob` class does not exist.

- [ ] **Step 3: Write the job**

Create `app/Jobs/CheckDomainStatusJob.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\DomainStatusChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDomainStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Domain $domain) {}

    public function handle(DomainStatusChecker $checker): void
    {
        $result = $checker->check($this->domain);

        $this->domain->forceFill([
            'dns_ok' => $result['dns_ok'],
            'reachable_ok' => $result['reachable_ok'],
            'ssl_ok' => $result['ssl_ok'],
            'check_details' => $result['check_details'],
            'last_checked_at' => now(),
        ])->save();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --filter=CheckDomainStatusJobTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/CheckDomainStatusJob.php tests/Feature/CheckDomainStatusJobTest.php
git commit -m "feat(domains): add CheckDomainStatusJob as single status write path"
```

---

### Task 6: Triggers — observer dispatch on create + scheduler

**Files:**
- Modify: `app/Observers/DomainObserver.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/DomainStatusTriggerTest.php`

**Interfaces:**
- Consumes: `CheckDomainStatusJob` (Task 5).
- Produces: a `created()` observer hook dispatching `CheckDomainStatusJob`; a 15-minute schedule entry dispatching the job for every domain.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DomainStatusTriggerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\CheckDomainStatusJob;
use App\Models\Domain;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DomainStatusTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_domain_dispatches_a_status_check(): void
    {
        Queue::fake();

        $project = Project::factory()->create();
        $domain = Domain::factory()->create(['project_id' => $project->id]);

        Queue::assertPushed(CheckDomainStatusJob::class, fn (CheckDomainStatusJob $job) => $job->domain->is($domain));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --filter=DomainStatusTriggerTest`
Expected: FAIL — no `CheckDomainStatusJob` pushed on create.

- [ ] **Step 3: Add the observer hook**

Edit `app/Observers/DomainObserver.php`. Add the import and a `created()` method. Full file:

```php
<?php

namespace App\Observers;

use App\Jobs\CheckDomainStatusJob;
use App\Jobs\RegenerateTraefikConfigJob;
use App\Models\Domain;

class DomainObserver
{
    public function creating(Domain $domain): void
    {
        RegenerateTraefikConfigJob::dispatch();
    }

    public function created(Domain $domain): void
    {
        CheckDomainStatusJob::dispatch($domain);
    }

    public function updating(Domain $domain): void
    {
        if ($domain->isDirty('name')) {
            RegenerateTraefikConfigJob::dispatch();
        }
    }

    public function deleted(Domain $domain): void
    {
        RegenerateTraefikConfigJob::dispatch();
    }

    public function forceDeleted(Domain $domain): void
    {
        RegenerateTraefikConfigJob::dispatch();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `ddev exec php artisan test --filter=DomainStatusTriggerTest`
Expected: PASS.

- [ ] **Step 5: Add the scheduler entry**

Edit `routes/console.php`. Add imports at the top and the schedule entry after the existing `geoip:update` line:

```php
use App\Jobs\CheckDomainStatusJob;
use App\Models\Domain;
```

```php
Schedule::call(function () {
    Domain::query()->each(fn (Domain $domain) => CheckDomainStatusJob::dispatch($domain));
})->everyFifteenMinutes()->name('domains:check-status')->withoutOverlapping();
```

- [ ] **Step 6: Verify the schedule registers**

Run: `ddev exec php artisan schedule:list`
Expected: output lists the new entry running every 15 minutes (alongside `geoip:update`).

- [ ] **Step 7: Commit**

```bash
git add app/Observers/DomainObserver.php routes/console.php tests/Feature/DomainStatusTriggerTest.php
git commit -m "feat(domains): check status on create and every 15 minutes"
```

---

### Task 7: On-demand check endpoint + expose appDomain

**Files:**
- Modify: `app/Http/Controllers/DomainController.php`
- Modify: `routes/web.php` (Domains group, after line ~93)
- Test: `tests/Feature/DomainCheckEndpointTest.php`

**Interfaces:**
- Consumes: `CheckDomainStatusJob` (Task 5, via `dispatchSync`); `ProjectBindingMiddleware` providing `$request->get('project')`.
- Produces:
  - Route `POST /project/{project}/domains/{domain}/check`, named `app.project.domains.check`.
  - `DomainController@check(Request $request, string $domain)` runs the check synchronously, then redirects to the index with a success flash.
  - `index`, `create`, `edit` now also pass `appDomain => config('app.domain')` as an Inertia prop.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/DomainCheckEndpointTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use App\Services\DomainStatusChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainCheckEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function fakeChecker(): void
    {
        $this->app->instance(DomainStatusChecker::class, new class extends DomainStatusChecker {
            public function __construct() {}

            public function check(Domain $domain): array
            {
                return [
                    'dns_ok' => true,
                    'reachable_ok' => true,
                    'ssl_ok' => true,
                    'check_details' => [],
                ];
            }
        });
    }

    public function test_check_endpoint_runs_checker_and_persists(): void
    {
        $this->fakeChecker();

        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'member', 'active' => true]);
        $domain = Domain::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user)
            ->post(route('app.project.domains.check', ['project' => $project->id, 'domain' => $domain->id]))
            ->assertRedirect(route('app.project.domains.index', ['project' => $project->id]));

        $domain->refresh();
        $this->assertTrue($domain->dns_ok);
        $this->assertNotNull($domain->last_checked_at);
    }

    public function test_cannot_check_another_projects_domain(): void
    {
        $this->fakeChecker();

        $user = User::factory()->create();
        $project = Project::factory()->create();
        $project->users()->attach($user, ['role' => 'member', 'active' => true]);

        $otherProject = Project::factory()->create();
        $otherDomain = Domain::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($user)
            ->post(route('app.project.domains.check', ['project' => $project->id, 'domain' => $otherDomain->id]))
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `ddev exec php artisan test --filter=DomainCheckEndpointTest`
Expected: FAIL — route `app.project.domains.check` not defined.

- [ ] **Step 3: Add the route**

Edit `routes/web.php`. In the `// Domains` block (after the `destroy` route, line ~93), add:

```php
            Route::post('/domains/{domain}/check', [DomainController::class, 'check'])->name('app.project.domains.check');
```

- [ ] **Step 4: Update the controller**

Edit `app/Http/Controllers/DomainController.php`. Add the `CheckDomainStatusJob` import, the `appDomain` prop on `index`/`create`/`edit`, and the `check` method. Full file:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\DomainRequest;
use App\Jobs\CheckDomainStatusJob;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $project = $request->get('project');

        return inertia('Domains/Index', [
            'domains' => $project->domains()->latest()->get(),
            'appDomain' => config('app.domain'),
        ]);
    }

    public function create()
    {
        return inertia('Domains/Create', [
            'appDomain' => config('app.domain'),
        ]);
    }

    public function store(DomainRequest $request)
    {
        $project = $request->get('project');

        $project->domains()->create($request->validated());

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain created.');
    }

    public function edit(Request $request, string $domain)
    {
        $project = $request->get('project');

        return inertia('Domains/Edit', [
            'domain' => $project->domains()->findOrFail($domain),
            'appDomain' => config('app.domain'),
        ]);
    }

    public function update(DomainRequest $request, string $domain)
    {
        $project = $request->get('project');

        $model = $project->domains()->findOrFail($domain);

        $model->update($request->validated());

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain updated.');
    }

    public function check(Request $request, string $domain)
    {
        $project = $request->get('project');

        $model = $project->domains()->findOrFail($domain);

        CheckDomainStatusJob::dispatchSync($model);

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain status refreshed.');
    }

    public function destroy(Request $request, string $domain)
    {
        $project = $request->get('project');

        $project->domains()->findOrFail($domain)->delete();

        return redirect()->route('app.project.domains.index')
            ->with('success', 'Domain deleted.');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `ddev exec php artisan test --filter=DomainCheckEndpointTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/DomainController.php routes/web.php tests/Feature/DomainCheckEndpointTest.php
git commit -m "feat(domains): add on-demand status check endpoint and expose appDomain"
```

---

### Task 8: Frontend — types, info box, status UI

**Files:**
- Modify: `resources/js/types/index.d.ts`
- Create: `resources/js/Pages/Domains/Partials/DnsInfoBox.tsx`
- Create: `resources/js/Pages/Domains/Partials/StatusPills.tsx`
- Modify: `resources/js/Pages/Domains/Index.tsx`
- Modify: `resources/js/Pages/Domains/Create.tsx`
- Modify: `resources/js/Pages/Domains/Edit.tsx`

**Interfaces:**
- Consumes: `appDomain: string` prop (Task 7); `Domain` status fields (Task 1).
- Produces: updated `Domain` type; `DnsInfoBox` and `StatusPills` components; status column + "Check now" button on Index; info box on Create/Edit; status panel on Edit.

- [ ] **Step 1: Update the Domain type**

Edit `resources/js/types/index.d.ts`. Replace the `Domain` interface with:

```typescript
export interface DomainCheckDetails {
  dns?: { domain_ips?: string[]; app_ips?: string[]; error?: string };
  ssl?: { expires_at?: number; names?: string[]; error?: string };
  reachable?: { status?: number; error?: string };
}

export interface Domain {
  id: string;
  name: string;
  redirect_root: string | null;
  redirect_not_found: string | null;
  status: 'healthy' | 'error' | 'pending';
  dns_ok: boolean | null;
  reachable_ok: boolean | null;
  ssl_ok: boolean | null;
  check_details: DomainCheckDetails | null;
  last_checked_at: string | null;
  created_at: string;
  updated_at: string;
}
```

- [ ] **Step 2: Create the DnsInfoBox component**

Create `resources/js/Pages/Domains/Partials/DnsInfoBox.tsx`:

```tsx
import { Check, Copy, Info } from 'lucide-react';
import { useState } from 'react';

export default function DnsInfoBox({ appDomain }: { appDomain: string }) {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    await navigator.clipboard.writeText(appDomain);
    setCopied(true);
    setTimeout(() => setCopied(false), 1500);
  };

  return (
    <div className="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-900/50 dark:bg-indigo-900/20">
      <div className="flex items-start gap-3">
        <Info className="mt-0.5 h-5 w-5 flex-shrink-0 text-indigo-600 dark:text-indigo-400" />
        <div className="text-sm text-slate-700 dark:text-slate-300">
          <p className="font-semibold text-slate-900 dark:text-white">Connect your domain</p>
          <p className="mt-1">
            At your DNS provider, point your domain to us with a <strong>CNAME</strong> record:
          </p>
          <div className="mt-2 flex flex-wrap items-center gap-2 rounded-md bg-white px-3 py-2 font-mono text-xs dark:bg-slate-800">
            <span className="text-slate-500 dark:text-slate-400">CNAME →</span>
            <span className="font-semibold text-slate-900 dark:text-white">{appDomain}</span>
            <button
              type="button"
              onClick={copy}
              className="ml-auto inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-slate-200"
            >
              {copied ? <Check className="h-3.5 w-3.5 text-green-600" /> : <Copy className="h-3.5 w-3.5" />}
              {copied ? 'Copied' : 'Copy'}
            </button>
          </div>
          <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
            Once DNS propagates we automatically issue an SSL certificate — this can take a few minutes.
          </p>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Create the StatusPills component**

Create `resources/js/Pages/Domains/Partials/StatusPills.tsx`:

```tsx
import { Domain } from '@/types';

function Pill({ label, value }: { label: string; value: boolean | null }) {
  const style =
    value === true
      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
      : value === false
        ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
        : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500';

  const mark = value === true ? '✓' : value === false ? '✗' : '–';

  return (
    <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ${style}`}>
      <span aria-hidden>{mark}</span>
      {label}
    </span>
  );
}

export default function StatusPills({ domain }: { domain: Domain }) {
  return (
    <div className="flex flex-wrap items-center gap-1.5">
      <Pill label="DNS" value={domain.dns_ok} />
      <Pill label="Reachable" value={domain.reachable_ok} />
      <Pill label="SSL" value={domain.ssl_ok} />
    </div>
  );
}
```

- [ ] **Step 4: Wire the status column and "Check now" button into Index**

Edit `resources/js/Pages/Domains/Index.tsx`:

(a) Update the imports line for lucide and add `StatusPills` + `useState`:

```tsx
import StatusPills from '@/Pages/Domains/Partials/StatusPills';
import { Globe, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
```

(b) Change the component signature to receive `appDomain` (kept for type-parity; not rendered here) — replace the function signature:

```tsx
export default function DomainsIndex({ domains }: { domains: Domain[]; appDomain: string }) {
```

(c) Inside the component, after the `destroy` function, add a check handler:

```tsx
  const [checking, setChecking] = useState<string | null>(null);

  function check(domain: Domain) {
    setChecking(domain.id);
    router.post(
      route('app.project.domains.check', { project: project!.id, domain: domain.id }),
      {},
      { preserveScroll: true, onFinish: () => setChecking(null) },
    );
  }

  function relativeTime(iso: string | null): string {
    if (!iso) return 'never checked';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.round(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.round(hrs / 24)}d ago`;
  }
```

(d) Add a `Status` column header. After the `404 redirect` `<th>` (line ~67-69), add:

```tsx
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Status
                  </th>
```

(e) Add the status cell in each row. Before the actions `<td>` (the one with the edit/delete buttons), add:

```tsx
                    <td className="px-4 py-3">
                      <div className="flex flex-col gap-1">
                        <StatusPills domain={domain} />
                        <span className="text-xs text-slate-400 dark:text-slate-500">{relativeTime(domain.last_checked_at)}</span>
                      </div>
                    </td>
```

(f) In the actions cell, add a "Check now" button before the edit `<Link>`:

```tsx
                        <button
                          onClick={() => check(domain)}
                          disabled={checking === domain.id}
                          className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                          title="Check status"
                        >
                          <RefreshCw className={`h-4 w-4 ${checking === domain.id ? 'animate-spin' : ''}`} />
                        </button>
```

- [ ] **Step 5: Add the info box to Create**

Edit `resources/js/Pages/Domains/Create.tsx`:

(a) Add the import:

```tsx
import DnsInfoBox from '@/Pages/Domains/Partials/DnsInfoBox';
```

(b) Change the signature to receive `appDomain`:

```tsx
export default function DomainsCreate({ appDomain }: { appDomain: string }) {
```

(c) Render the info box above the form card. Replace the line `<div className="max-w-lg rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">` with:

```tsx
        <div className="max-w-lg">
          <DnsInfoBox appDomain={appDomain} />
        </div>

        <div className="max-w-lg rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
```

- [ ] **Step 6: Add the info box and status panel to Edit**

Edit `resources/js/Pages/Domains/Edit.tsx`:

(a) Add imports:

```tsx
import DnsInfoBox from '@/Pages/Domains/Partials/DnsInfoBox';
import StatusPills from '@/Pages/Domains/Partials/StatusPills';
import { ArrowLeft, Loader2, RefreshCw } from 'lucide-react';
import { router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
```

(Note: merge these with the existing `@inertiajs/react` import — the final import should be `import { Link, router, useForm, usePage } from '@inertiajs/react';`.)

(b) Change the signature to receive `appDomain`:

```tsx
export default function DomainsEdit({ domain, appDomain }: { domain: Domain; appDomain: string }) {
```

(c) After the `submit` handler, add a check handler:

```tsx
  const [checking, setChecking] = useState(false);

  function check() {
    setChecking(true);
    router.post(
      route('app.project.domains.check', { project: project!.id, domain: domain.id }),
      {},
      { preserveScroll: true, onFinish: () => setChecking(false) },
    );
  }
```

(d) Above the form card `<div className="max-w-lg rounded-xl border ...">`, insert the info box and status panel:

```tsx
        <div className="mb-6 max-w-lg">
          <DnsInfoBox appDomain={appDomain} />

          <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <div className="mb-3 flex items-center justify-between">
              <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Domain status</h2>
              <button
                type="button"
                onClick={check}
                disabled={checking}
                className="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
              >
                <RefreshCw className={`h-3.5 w-3.5 ${checking ? 'animate-spin' : ''}`} />
                Check now
              </button>
            </div>
            <StatusPills domain={domain} />
            <dl className="mt-3 space-y-1 text-xs text-slate-500 dark:text-slate-400">
              {domain.check_details?.dns?.domain_ips && (
                <div>Resolves to: {domain.check_details.dns.domain_ips.join(', ') || '—'}</div>
              )}
              {domain.check_details?.ssl?.error && <div>SSL: {domain.check_details.ssl.error}</div>}
              {domain.check_details?.reachable?.error && <div>Reachable: {domain.check_details.reachable.error}</div>}
              {domain.last_checked_at && <div>Last checked: {new Date(domain.last_checked_at).toLocaleString()}</div>}
            </dl>
          </div>
        </div>
```

- [ ] **Step 7: Run the frontend build**

Run: `ddev npm run build`
Expected: TypeScript check passes, Vite build completes with no errors.

- [ ] **Step 8: Commit**

```bash
git add resources/js/types/index.d.ts resources/js/Pages/Domains/
git commit -m "feat(domains): info box, status pills, and check-now UI"
```

---

### Task 9: Full suite green

**Files:** none (verification task).

- [ ] **Step 1: Run the full backend suite**

Run: `ddev composer run test`
Expected: all tests pass, including the new `DomainStatusTest`, `DomainStatusCheckerTest`, `DomainSignatureRouteTest`, `CheckDomainStatusJobTest`, `DomainStatusTriggerTest`, `DomainCheckEndpointTest`.

- [ ] **Step 2: Run the frontend gate**

Run: `ddev npm run build`
Expected: passes.

- [ ] **Step 3: Final commit (if any fixups were needed)**

```bash
git add -A
git commit -m "test(domains): finalize status check feature" || echo "nothing to commit"
```

---

## Self-Review

**Spec coverage:**
- Data model (5 columns + derived status) → Task 1. ✓
- DomainStatusChecker with three staged, fault-isolated checks + injectable seams → Tasks 3 + 4. ✓
- App signature route `/.well-known/marketix` → Task 2. ✓
- CheckDomainStatusJob single write path → Task 5. ✓
- Triggers: on-create + 15-min scheduler + on-demand endpoint → Tasks 6 + 7. ✓
- Frontend info box (Create/Edit), status pills on Index, status panel on Edit, types, appDomain prop → Tasks 7 (prop) + 8 (UI). ✓
- Lenient DNS (IP-set intersection) → Task 4 `checkDns`. ✓
- Reachability via signature match → Task 4 `checkReachable`. ✓
- Local/DDEV graceful degradation → every check in Task 4 wrapped in try/catch returning false. ✓
- Testing plan (unit checker, feature signature/endpoint/job/observer, build gate) → Tasks 1–9. ✓

**Placeholder scan:** No TBD/TODO; every code step shows full code. ✓

**Type consistency:** `check(Domain): array{dns_ok,reachable_ok,ssl_ok,check_details}` used identically in Task 4 (produced), Task 5 (job consumes), Task 7 (fake). Status accessor `'healthy'|'error'|'pending'` matches the TS union in Task 8. `resolveIps(string): array` and `read(string,int=443): ?array` consistent across Tasks 3–4. Route name `app.project.domains.check` consistent across Tasks 6c-test/7. ✓

## Out of Scope (from spec)

- Apex-domain A-record guidance / server-IP env var.
- Per-domain verification tokens.
- Manual SSL cert upload.
- Faster polling for not-yet-healthy domains.
