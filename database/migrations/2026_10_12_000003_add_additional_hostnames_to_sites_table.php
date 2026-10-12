<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('additional_hostnames', 1000)->nullable()->after('domain');
        });

        // Ingestion now drops hits from foreign hostnames. Keep accepting the
        // hosts a site was already tracked on, so existing multi-domain setups
        // keep collecting data.
        foreach (DB::table('sites')->whereNull('deleted_at')->get(['id', 'domain']) as $site) {
            $domain = $this->normalize($site->domain);

            $hosts = DB::table('page_views')
                ->where('site_id', $site->id)
                ->where('created_at', '>=', now()->subDays(30))
                ->whereNotNull('hostname')
                ->select('hostname', DB::raw('COUNT(*) as hits'))
                ->groupBy('hostname')
                ->orderByDesc('hits')
                ->limit(50)
                ->pluck('hostname')
                ->map(fn (string $host) => $this->normalize($host))
                ->filter(fn (?string $host) => $host !== null
                    && ! $this->isLocal($host)
                    && ($domain === null || ($host !== $domain && ! str_ends_with($host, '.'.$domain))))
                ->unique()
                ->take(20);

            if ($hosts->isNotEmpty()) {
                DB::table('sites')->where('id', $site->id)->update(['additional_hostnames' => $hosts->implode(', ')]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('additional_hostnames');
        });
    }

    private function normalize(?string $host): ?string
    {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return null;
        }

        $host = parse_url(str_contains($host, '://') ? $host : 'https://'.$host, PHP_URL_HOST) ?: $host;

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private function isLocal(string $host): bool
    {
        return $host === 'localhost'
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || preg_match('/\.(localhost|local|test|internal)$/', $host) === 1;
    }
};
