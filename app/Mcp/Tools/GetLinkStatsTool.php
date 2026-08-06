<?php

namespace App\Mcp\Tools;

use App\Models\Statistic;
use App\Models\Url;
use App\Services\StatisticsAggregator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get click statistics for a single link over a trailing window, scoped to projects the caller belongs to.')]
class GetLinkStatsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            'link_id' => ['required', 'string'],
            'range_days' => ['sometimes', 'integer', 'min:1'],
        ]);

        $user = $request->user();

        if ($user === null) {
            return Response::error('Not authenticated.');
        }

        $linkId = (string) $request->get('link_id');
        $rangeDays = (int) $request->get('range_days', 30);

        // Resolve the Url only within the caller's own projects — never allow
        // a link from a project the user doesn't belong to.
        $projectIds = $user->projects->pluck('id');

        /** @var Url|null $url */
        $url = Url::whereIn('project_id', $projectIds)->find($linkId);

        if ($url === null) {
            return Response::error('Link not found or access denied.');
        }

        $stats = new StatisticsAggregator;
        $since = now()->subDays($rangeDays - 1)->startOfDay();

        // Mirrors UrlController@show's per-link range aggregation: totalClicks /
        // uniqueClicks / breakdown() scoped to this project + url + window.
        $byCountry = $stats->breakdown($url->project_id, $url->id, 'country', $since)
            ->map(fn (Statistic $row): array => ['country' => $row->country, 'count' => (int) $row->count])
            ->values()
            ->all();

        $byReferrer = $stats->breakdown($url->project_id, $url->id, 'domain', $since)
            ->map(fn (Statistic $row): array => ['referrer' => $row->domain, 'count' => (int) $row->count])
            ->values()
            ->all();

        // The Statistic table has no single "device" column — the Links/Show
        // page renders the device column as `[browser, os].join(' · ')`
        // (resources/js/Pages/Links/Show.tsx). We reuse that exact pairing
        // here rather than inventing a new metric.
        $byDevice = Statistic::query()
            ->where('project_id', $url->project_id)
            ->where('url_id', $url->id)
            ->where('is_bot', false)
            ->where('created_at', '>=', $since)
            ->select('browser', 'os', DB::raw('COUNT(*) as count'))
            ->groupBy('browser', 'os')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(function (Statistic $row): array {
                $device = collect([$row->browser, $row->os])->filter()->implode(' · ');

                return ['device' => $device !== '' ? $device : 'Unknown', 'count' => (int) $row->count];
            })
            ->values()
            ->all();

        $payload = [
            'clicks' => $stats->totalClicks($url->project_id, $url->id, $since),
            'unique_clicks' => $stats->uniqueClicks($url->project_id, $url->id, $since),
            'by_country' => $byCountry,
            'by_device' => $byDevice,
            'by_referrer' => $byReferrer,
        ];

        return Response::text(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'link_id' => $schema->string()
                ->description('The id of the link to get statistics for.')
                ->required(),
            'range_days' => $schema->integer()
                ->description('Number of trailing days to aggregate statistics over.')
                ->default(30),
        ];
    }
}
