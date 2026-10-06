<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithSiteAnalytics;
use App\Services\AnalyticsAggregator;
use App\Services\GoalAggregator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Rank one dimension of a website\'s web analytics (pages, referrers, channels, UTM parameters, locations, '
    .'technology, events or new vs. returning visitors) for a period, optionally filtered. Use it to drill into get_site_stats.')]
class GetSiteBreakdownTool extends Tool
{
    use InteractsWithSiteAnalytics;

    public const DIMENSIONS = [
        'pages', 'entry_pages', 'exit_pages', 'hostnames', 'referrers', 'channels',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'countries', 'regions', 'cities', 'languages', 'devices', 'browsers', 'operating_systems',
        'events', 'visitor_types',
    ];

    public function __construct(
        private AnalyticsAggregator $analytics,
        private GoalAggregator $goals,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate([
            ...$this->periodRules(),
            'dimension' => ['required', 'string', 'in:'.implode(',', self::DIMENSIONS)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $site = $this->resolveSite($request);

        if ($site === null) {
            return Response::error('Site not found, ambiguous or access denied. Pass the site id from list_sites.');
        }

        $period = $this->resolvePeriod($request);
        $query = $period->query;
        $limit = (int) $request->get('limit', 20);
        $dimension = (string) $request->get('dimension');
        $id = $site->id;

        $rows = match ($dimension) {
            'pages' => $this->analytics->topPaths($id, $query, $limit),
            'entry_pages' => $this->analytics->entryPages($id, $query, $limit),
            'exit_pages' => $this->analytics->exitPages($id, $query, $limit),
            'hostnames' => $this->analytics->hostnames($id, $query, $limit),
            'referrers' => $this->analytics->topReferrers($id, $query, $limit),
            'channels' => $this->analytics->channels($id, $query)->take($limit),
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' => $this->analytics->utmBreakdown($id, $dimension, $query, $limit),
            'countries' => $this->analytics->countriesWithCode($id, $query, $limit),
            'regions' => $this->analytics->locations($id, 'region', $query, $limit),
            'cities' => $this->analytics->locations($id, 'city', $query, $limit),
            'languages' => $this->analytics->breakdown($id, 'language', $query, $limit),
            'devices' => $this->analytics->breakdown($id, 'device', $query, $limit),
            'browsers' => $this->analytics->breakdown($id, 'browser', $query, $limit),
            'operating_systems' => $this->analytics->breakdown($id, 'os', $query, $limit),
            'events' => $this->goals->topEvents($id, $query, $limit),
            'visitor_types' => $this->analytics->visitorTypes($id, $query),
        };

        return $this->json([
            'site' => ['id' => $site->id, 'name' => $site->name, 'domain' => $site->domain],
            'period' => $period->toArray(),
            'filters' => (object) $query->filters,
            'dimension' => $dimension,
            'rows' => $this->rows($rows),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->periodSchema($schema),
            'dimension' => $schema->string()
                ->enum(self::DIMENSIONS)
                ->description('What to rank. Counts are page views for page, location and technology dimensions and sessions for entry/exit pages, channels and UTM parameters.')
                ->required(),
            'limit' => $schema->integer()->min(1)->max(100)->default(20)
                ->description('Maximum number of rows.'),
        ];
    }
}
