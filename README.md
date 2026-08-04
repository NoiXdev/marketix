# Marketix

## Deployment / Troubleshooting

### GeoIP stats (country/city) are empty in production

**Symptom:** New `statistics` rows are written, but `country`, `country_code`
and `city` are `NULL` even though the GeoLite2 database resolves correctly when
tested directly:

```bash
docker exec marketix-app-1 php artisan marketix:geoip:test 94.114.71.204
# -> Germany / Bocholt / DE  (DB and lookup code are fine)
```

**Root cause:** This is **not** an application bug. The stack runs
`client → Traefik → Caddy/FrankenPHP → PHP`, and `bootstrap/app.php` already
trusts the reverse proxy (`trustProxies(at: '*')`). The real client IP is lost
one layer lower, in Docker's networking:

With Docker's `userland-proxy` enabled (the default when `/etc/docker/daemon.json`
is empty/`{}`), the `docker-proxy` relay handles inbound `:80`/`:443` and rewrites
the source IP to the bridge gateway (e.g. `172.20.0.1`). Traefik therefore never
sees the real client and sets `X-Forwarded-For: 172.20.0.1`. Laravel reads that
correctly → a private IP → GeoIP returns null. This tends to surface after the
containers are recreated on a newer Docker engine (28/29).

**Diagnose:** drop a temporary probe into `public/`, hit it from a real external
IP, and check what PHP actually receives (remember to remove it afterwards):

```php
// public/ipdebug.php  — TEMPORARY, delete after use
<?php
header('Content-Type: text/plain');
echo 'REMOTE_ADDR='.($_SERVER['REMOTE_ADDR'] ?? '')."\n";
echo 'X-Forwarded-For='.($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')."\n";
```

If `X-Forwarded-For` shows a `172.20.0.x` gateway address for an external
request, it is this issue.

**Fix:** disable the Docker userland proxy so the kernel uses pure DNAT and
preserves the source IP, then restart the daemon:

```bash
echo '{"userland-proxy": false}' > /etc/docker/daemon.json && systemctl restart docker
```

> ⚠️ `systemctl restart docker` restarts **all** containers (a few seconds of
> downtime). The `daemon.json` change is persistent and survives reboots.

**Verify:** after the restart, an external request must arrive with the real
`X-Forwarded-For`, and a real click must record geo data:

```bash
docker exec marketix-app-1 php artisan tinker --execute="\
use App\Models\Statistic; \$s = Statistic::latest()->first(); \
echo \$s->country.' / '.\$s->country_code.' / '.\$s->city.PHP_EOL;"
```

Existing `NULL` rows cannot be backfilled — only the visitor hash is stored, not
the raw IP.
