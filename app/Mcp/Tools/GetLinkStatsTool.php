<?php

namespace App\Mcp\Tools;

use App\Models\Statistic;
use App\Models\Url;
use App\Services\StatisticsAggregator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
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

        // The Statistic table has no single "device" column, and the app
        // never aggregates browser+os as one combined metric anywhere (the
        // canonical Statistics page and Links/Show both expose browser and
        // os as separate breakdowns — topBrowsers / topOs). So we reuse the
        // aggregator's single-column breakdown() for each, rather than
        // inventing a combined-tuple metric that doesn't exist elsewhere.
        $byBrowser = $stats->breakdown($url->project_id, $url->id, 'browser', $since)
            ->map(fn (Statistic $row): array => ['browser' => $row->browser, 'count' => (int) $row->count])
            ->values()
            ->all();

        $byOs = $stats->breakdown($url->project_id, $url->id, 'os', $since)
            ->map(fn (Statistic $row): array => ['os' => $row->os, 'count' => (int) $row->count])
            ->values()
            ->all();

        $payload = [
            'clicks' => $stats->totalClicks($url->project_id, $url->id, $since),
            'unique_clicks' => $stats->uniqueClicks($url->project_id, $url->id, $since),
            'by_country' => $byCountry,
            'by_browser' => $byBrowser,
            'by_os' => $byOs,
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
