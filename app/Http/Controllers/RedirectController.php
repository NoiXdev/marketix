<?php

namespace App\Http\Controllers;

use App\Enums\UrlStatus;
use App\Jobs\RecordClickStatisticJob;
use App\Models\Domain;
use App\Models\QrCode;
use App\Models\Url;
use App\Services\GeoIpService;
use App\Support\UserAgent;
use App\Support\VisitorHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RedirectController extends Controller
{
    public function __construct(private GeoIpService $geoIp) {}

    public function handle(Request $request): mixed
    {
        $host = $request->getHost();
        $slug = trim($request->path(), '/');

        $domain = Domain::where('name', $host)->first();

        if ($slug === '') {
            if ($domain?->redirect_root) {
                return redirect($domain->redirect_root, 301);
            }
            abort(404);
        }

        if (! $domain) {
            abort(404);
        }

        $url = Url::where('domain_id', $domain->id)
            ->where('slug', $slug)
            ->with(['pixels', 'qrCode'])
            ->first();

        if (! $url || $url->status === UrlStatus::DEACTIVATED || $url->archived) {
            if ($domain->redirect_not_found) {
                return redirect($domain->redirect_not_found, 302);
            }
            abort(404);
        }

        if ($url->expired_at && $url->expired_at->isPast()) {
            if ($domain->redirect_not_found) {
                return redirect($domain->redirect_not_found, 302);
            }
            abort(410);
        }

        if ($url->password) {
            $sessionKey = "url_verified_{$url->id}";
            if (! $request->session()->get($sessionKey)) {
                return view('redirect.password', ['slug' => $slug, 'host' => $host]);
            }
        }

        return $this->resolveAndRespond($request, $url);
    }

    public function checkPassword(Request $request, string $slug): mixed
    {
        $host = $request->getHost();
        $domain = Domain::where('name', $host)->firstOrFail();
        $url = Url::where('domain_id', $domain->id)->where('slug', $slug)->with(['pixels', 'qrCode'])->firstOrFail();

        if (! $url->password || ! Hash::check((string) $request->input('password'), $url->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $request->session()->put("url_verified_{$url->id}", true);

        return $this->resolveAndRespond($request, $url);
    }

    /**
     * Resolve the final target (targeting > A/B > default), record the click,
     * and return either the pixel-firing view or a plain redirect.
     */
    private function resolveAndRespond(Request $request, Url $url): mixed
    {
        $geo = $this->geoIp->lookup($request->ip());

        $this->dispatchStat($request, $url, $geo);

        // vCard QRs can't be a 302 target — serve the contact file instead.
        if ($url->qrCode && $url->qrCode->type === 'vcard') {
            return $this->vCardResponse($url->qrCode);
        }

        $targetUrl = $this->resolveTargeting($request, $url, $geo)  // geo/device/lang wins
                  ?? $this->resolveAbTest($url)                       // A/B if no targeting matched
                  ?? $url->url;                                        // default

        if ($url->pixels->isNotEmpty()) {
            return view('redirect.pixels', [
                'pixels' => $url->pixels,
                'targetUrl' => $targetUrl,
            ]);
        }

        return redirect($targetUrl, 302);
    }

    private function vCardResponse(QrCode $qrCode): mixed
    {
        $filename = (Str::slug($qrCode->name) ?: 'contact').'.vcf';

        return response($qrCode->vCardString(), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // -----------------------------------------------------------------
    // Targeting (geo > device > language) — first match wins
    // -----------------------------------------------------------------

    private function resolveTargeting(Request $request, Url $url, array $geo): ?string
    {
        // 1 — Geo
        if (! empty($url->targeting_geo) && $geo['country_code']) {
            foreach ($url->targeting_geo as $rule) {
                if (empty($rule['url'])) {
                    continue;
                }

                $countryMatch = ($rule['country'] ?? '') === $geo['country_code'];
                $stateMatch = empty($rule['state'])
                    || ($rule['state'] === ($geo['subdivision_code'] ?? ''));

                if ($countryMatch && $stateMatch) {
                    return $rule['url'];
                }
            }
        }

        // 2 — Device / OS
        if (! empty($url->targeting_device)) {
            $os = UserAgent::os($request->userAgent() ?? '');

            foreach ($url->targeting_device as $rule) {
                if (! empty($rule['url']) && ($rule['device'] ?? '') === $os) {
                    return $rule['url'];
                }
            }
        }

        // 3 — Language
        if (! empty($url->targeting_language)) {
            $lang = substr($request->header('Accept-Language', ''), 0, 2);

            foreach ($url->targeting_language as $rule) {
                if (! empty($rule['url']) && ($rule['language'] ?? '') === $lang) {
                    return $rule['url'];
                }
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    // A/B rotation — only runs when no other targeting matched
    // -----------------------------------------------------------------

    private function resolveAbTest(Url $url): ?string
    {
        if (empty($url->targeting_ab)) {
            return null;
        }

        // Build full pool: default URL first, then all variants
        $variants = array_merge(
            [['url' => $url->url, 'weight' => null]],
            array_filter($url->targeting_ab, fn ($v) => ! empty($v['url'])),
        );

        if (count($variants) <= 1) {
            return null; // Nothing to rotate
        }

        // Decide if any explicit weight is set
        $hasWeights = collect($variants)
            ->contains(fn ($v) => isset($v['weight']) && $v['weight'] !== null && $v['weight'] !== '');

        if (! $hasWeights) {
            // Equal distribution
            return $variants[random_int(0, count($variants) - 1)]['url'];
        }

        // Weighted distribution
        $explicit = collect($variants)->filter(fn ($v) => isset($v['weight']) && $v['weight'] !== null && $v['weight'] !== '');
        $auto = collect($variants)->filter(fn ($v) => ! isset($v['weight']) || $v['weight'] === null || $v['weight'] === '');
        $explicitSum = $explicit->sum('weight');
        $remainder = max(0.0, 100.0 - (float) $explicitSum);
        $autoWeight = $auto->count() > 0 ? $remainder / $auto->count() : 0;

        $weights = collect($variants)->map(
            fn ($v) => (isset($v['weight']) && $v['weight'] !== null && $v['weight'] !== '')
                ? (float) $v['weight']
                : $autoWeight
        )->toArray();

        $total = array_sum($weights);
        if ($total <= 0) {
            return $variants[0]['url'];
        }

        $rand = (mt_rand() / mt_getrandmax()) * $total;
        $cumulative = 0.0;

        foreach ($variants as $i => $variant) {
            $cumulative += $weights[$i];
            if ($rand <= $cumulative) {
                return $variant['url'];
            }
        }

        return $variants[0]['url'];
    }

    // -----------------------------------------------------------------
    // Stats
    // -----------------------------------------------------------------

    private function dispatchStat(Request $request, Url $url, array $geo): void
    {
        $userAgent = $request->userAgent() ?? '';

        RecordClickStatisticJob::dispatch(
            $url->id,
            $url->project_id,
            VisitorHash::for($request->ip(), $userAgent),
            $userAgent,
            $request->header('Referer') ?: null,
            substr($request->header('Accept-Language', ''), 0, 2) ?: null,
            $geo,
        );
    }
}
