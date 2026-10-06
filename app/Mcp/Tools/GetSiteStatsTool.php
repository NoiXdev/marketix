<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithSiteAnalytics;
use App\Models\Goal;
use App\Services\AnalyticsAggregator;
use App\Services\GoalAggregator;
use App\Services\RevenueAggregator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get a web analytics report for a website: page views, visitors, sessions, bounce and engagement rate, '
    .'average duration and campaign share with their relative change against a comparison period, plus top pages, channels, '
    .'referrers, campaigns, countries, devices, new vs. returning visitors, goal conversions and revenue.')]
class GetSiteStatsTool extends Tool
{
    use InteractsWithSiteAnalytics;

    private const LIMIT = 10;

    public function __construct(
        private AnalyticsAggregator $analytics,
        private GoalAggregator $goals,
        private RevenueAggregator $revenue,
    ) {}

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $request->validate($this->periodRules());

        $site = $this->resolveSite($request);

        if ($site === null) {
            return Response::error('Site not found, ambiguous or access denied. Pass the site id from list_sites.');
        }

        $period = $this->resolvePeriod($request);
        $query = $period->query;
        $comparison = $period->comparison();

        $summary = $this->analytics->summary($site->id, $query);
        $previous = $comparison === null ? null : $this->analytics->summary($site->id, $comparison);
        $currency = $this->revenue->currencies($site->id, $query)[0]['currency'] ?? null;

        return $this->json([
            'site' => ['id' => $site->id, 'name' => $site->name, 'domain' => $site->domain],
            'period' => $period->toArray(),
            'filters' => (object) $query->filters,
            'summary' => $summary,
            'previous_summary' => $previous,
            'change_percent' => $previous === null ? null : collect($summary)->map(
                fn ($current, string $key) => $previous[$key] == 0 ? null : round(($current - $previous[$key]) / $previous[$key] * 100, 1),
            )->all(),
            'top_pages' => $this->rows($this->analytics->topPaths($site->id, $query, self::LIMIT)),
            'channels' => $this->rows($this->analytics->channels($site->id, $query)),
            'top_referrers' => $this->rows($this->analytics->topReferrers($site->id, $query, self::LIMIT)),
            'campaigns' => $this->rows($this->analytics->utmBreakdown($site->id, 'utm_campaign', $query, self::LIMIT)),
            'countries' => $this->rows($this->analytics->countriesWithCode($site->id, $query, self::LIMIT)),
            'devices' => $this->rows($this->analytics->breakdown($site->id, 'device', $query)),
            'visitor_types' => $this->analytics->visitorTypes($site->id, $query),
            'goals' => $site->goals()->get()->map(fn (Goal $goal): array => [
                'name' => $goal->name,
                'type' => $goal->type->value,
                'match_value' => $goal->match_value,
                ...$this->goals->conversions($goal, $query),
            ])->all(),
            'revenue' => $currency === null ? null : [
                'currency' => $currency,
                ...$this->revenue->summary($site->id, $query, $currency),
            ],
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->periodSchema($schema);
    }
}
