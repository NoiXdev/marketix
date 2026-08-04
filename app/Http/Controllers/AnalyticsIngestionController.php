<?php

namespace App\Http\Controllers;

use App\Enums\TrackingMode;
use App\Jobs\RecordPageViewJob;
use App\Models\Site;
use App\Services\GeoIpService;
use App\Support\VisitorHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AnalyticsIngestionController extends Controller
{
    public function __construct(private GeoIpService $geoIp) {}

    public function config(string $trackingId): JsonResponse
    {
        $site = Site::where('tracking_id', $trackingId)->firstOrFail();

        return response()->json([
            'tracking_mode' => $site->tracking_mode->value,
            'consent_mode' => $site->consent_mode->value,
            'consent_signal' => $site->consent_signal,
            'respect_dnt' => (bool) $site->respect_dnt,
        ])->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Serve the tracking snippet from a stable URL (/mx.js).
     *
     * Vendors (GA, Plausible, Fathom) never hash or version this URL — it lives
     * in customer HTML we can't edit — so updates roll out via cache headers,
     * not URL changes: a short max-age plus an ETag makes expiry a cheap 304
     * revalidation, and stale-while-revalidate keeps it instant meanwhile.
     * Bump the file, deploy, and every site picks it up within max-age.
     */
    public function snippet(Request $request): Response
    {
        $content = file_get_contents(resource_path('analytics/mx.js'));

        $response = response($content)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');

        $response->setEtag(md5($content));

        // If the client's If-None-Match matches, this swaps to 304 + empty body.
        $response->isNotModified($request);

        return $response;
    }

    public function event(Request $request): Response
    {
        // The beacon is sent as Content-Type: text/plain (a CORS "simple"
        // content type, so the browser skips the preflight). Laravel does not
        // auto-parse that into the input bag, so decode the raw JSON body here.
        // Harmless for application/json callers too (same bytes, same values).
        $payload = json_decode($request->getContent(), true);
        if (is_array($payload)) {
            $request->merge($payload);
        }

        $data = $request->validate([
            'site' => ['required', 'string'],
            'path' => ['required', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'visitor_id' => ['nullable', 'string', 'max:255'],
        ]);

        $noop = response('', 204);

        $site = Site::where('tracking_id', $data['site'])->first();
        if ($site === null) {
            return $noop; // unknown/deleted site — silent, no info leak
        }

        if ($site->respect_dnt && $request->header('DNT') === '1') {
            return $noop;
        }

        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';

        if ($site->tracking_mode === TrackingMode::Cookie) {
            $visitorId = $data['visitor_id'] ?? null;
            if ($visitorId === null || $visitorId === '') {
                return $noop; // cookie mode requires consent-provided id; no silent fallback
            }
            $visitorHash = hash('sha256', $visitorId.'|'.$site->id);
        } else {
            $visitorHash = VisitorHash::for($ip, $userAgent);
        }

        // Path only, query string stripped for privacy.
        $path = '/'.ltrim(parse_url($data['path'], PHP_URL_PATH) ?: '/', '/');

        RecordPageViewJob::dispatch(
            $site->id,
            $site->project_id,
            $visitorHash,
            $userAgent,
            $path,
            $data['referrer'] ?? null,
            substr($request->header('Accept-Language', ''), 0, 2) ?: null,
            $this->geoIp->lookup($ip),
        );

        return response('', 202);
    }
}
