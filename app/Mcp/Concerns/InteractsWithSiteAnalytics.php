<?php

namespace App\Mcp\Concerns;

use App\Models\Site;
use App\Support\Analytics\AnalyticsQuery;
use App\Support\Analytics\PeriodResolver;
use App\Support\Analytics\ResolvedPeriod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Site lookup, reporting period and filters shared by the web analytics
 * tools. Periods and filters mean exactly what they mean on the dashboard.
 */
trait InteractsWithSiteAnalytics
{
    /**
     * Resolve the "site" argument (id, name or domain, case-insensitively)
     * among the sites of projects the caller can access. Null when nothing
     * or more than one site matches.
     */
    protected function resolveSite(Request $request): ?Site
    {
        $user = $request->user();
        $value = trim((string) $request->get('site', ''));

        if ($user === null || $value === '') {
            return null;
        }

        $projectIds = $user->projects->filter(fn ($project) => $user->canAccessProject($project))->pluck('id');

        /** @var Collection<int, Site> $matches */
        $matches = Site::query()->whereIn('project_id', $projectIds)->get()->filter(
            fn (Site $site): bool => $site->id === $value
                || strcasecmp($site->name, $value) === 0
                || strcasecmp($site->domain, $value) === 0,
        );

        return $matches->count() === 1 ? $matches->first() : null;
    }

    protected function resolvePeriod(Request $request): ResolvedPeriod
    {
        $filters = $request->get('filters');

        return PeriodResolver::resolve(
            array_filter([
                'range' => $request->get('range'),
                'from' => $request->get('from'),
                'to' => $request->get('to'),
                'compare' => $request->get('compare'),
            ], fn ($value) => $value !== null),
            is_array($filters) ? $filters : [],
        );
    }

    /** @return array<string, array<int, mixed>> */
    protected function periodRules(): array
    {
        return [
            'site' => ['required', 'string'],
            'range' => ['nullable', 'string', 'in:'.implode(',', PeriodResolver::RANGES)],
            'from' => ['nullable', 'required_if:range,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:range,custom', 'date_format:Y-m-d'],
            'compare' => ['nullable', 'string', 'in:'.implode(',', PeriodResolver::COMPARISONS)],
            'filters' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, Type> */
    protected function periodSchema(JsonSchema $schema): array
    {
        $filters = [];
        foreach (AnalyticsQuery::FILTERS as $key) {
            $filters[$key] = $schema->string();
        }
        $filters['visitor_type'] = $schema->string()->enum(AnalyticsQuery::VISITOR_TYPES);

        return [
            'site' => $schema->string()
                ->description('The website id, name or domain. Use list_sites to find it.')
                ->required(),
            'range' => $schema->string()
                ->enum(PeriodResolver::RANGES)
                ->description('Reporting period. Defaults to 30d. "custom" requires from and to.'),
            'from' => $schema->string()->description('Start date (YYYY-MM-DD) when range is "custom".'),
            'to' => $schema->string()->description('End date (YYYY-MM-DD) when range is "custom".'),
            'compare' => $schema->string()
                ->enum(PeriodResolver::COMPARISONS)
                ->description('Comparison period: the previous period of equal length (default), the same period last year, or none.'),
            'filters' => $schema->object($filters)
                ->description('Optional exact-match filters that narrow every number, e.g. {"country_code": "CH", "channel": "organic_search"}. '
                    .'channel is one of direct, organic_search, paid, social, email, referral, campaign; visitor_type is new or returning.'),
        ];
    }

    /** @param array<string, mixed> $payload */
    protected function json(array $payload): Response
    {
        return Response::text(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Rows from an aggregate query as plain arrays.
     *
     * @param  iterable<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    protected function rows(iterable $rows): array
    {
        return collect($rows)
            ->map(fn ($row) => is_array($row) ? $row : (method_exists($row, 'getAttributes') ? $row->getAttributes() : (array) $row))
            ->values()
            ->all();
    }
}
