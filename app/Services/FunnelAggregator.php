<?php

namespace App\Services;

use App\Enums\GoalType;
use App\Models\Funnel;
use App\Support\Analytics\AnalyticsQuery;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class FunnelAggregator
{
    public function __construct(private GoalAggregator $goals = new GoalAggregator) {}

    /**
     * @return array{entered: int, completed: int, conversion_rate: float, steps: list<array{label: ?string, type: string, value: string, sessions: int, rate: float, step_rate: ?float, drop_off: int}>}
     */
    public function report(Funnel $funnel, AnalyticsQuery|int $range): array
    {
        $query = AnalyticsQuery::from($range);
        $reached = null;
        $steps = [];

        foreach ($funnel->steps as $step) {
            $matches = $this->goals
                ->matches($funnel->site_id, GoalType::from($step['type']), $step['value'], $query)
                ->select('visit_id', 'created_at as reached_at')
                ->toBase();

            $reached = $reached === null ? $this->firstStep($matches) : $this->nextStep($reached, $matches);
            $steps[] = [
                'label' => $step['label'] ?? null,
                'type' => $step['type'],
                'value' => $step['value'],
                'sessions' => DB::query()->fromSub($reached, 'reached')->count(),
            ];
        }

        $entered = $steps[0]['sessions'] ?? 0;
        $previous = $entered;

        foreach ($steps as $i => $step) {
            $steps[$i]['rate'] = $entered > 0 ? round($step['sessions'] / $entered * 100, 1) : 0.0;
            $steps[$i]['step_rate'] = $i === 0 ? null : ($previous > 0 ? round($step['sessions'] / $previous * 100, 1) : 0.0);
            $steps[$i]['drop_off'] = $i === 0 ? 0 : $previous - $step['sessions'];
            $previous = $step['sessions'];
        }

        $completed = $steps === [] ? 0 : end($steps)['sessions'];

        return [
            'entered' => $entered,
            'completed' => $completed,
            'conversion_rate' => $entered > 0 ? round($completed / $entered * 100, 1) : 0.0,
            'steps' => $steps,
        ];
    }

    private function firstStep(Builder $matches): Builder
    {
        return DB::query()
            ->fromSub($matches, 'm')
            ->select('m.visit_id', DB::raw('MIN(m.reached_at) as reached_at'))
            ->groupBy('m.visit_id');
    }

    private function nextStep(Builder $previous, Builder $matches): Builder
    {
        return DB::query()
            ->fromSub($previous, 'p')
            ->joinSub($matches, 'm', fn (JoinClause $join) => $join
                ->on('m.visit_id', '=', 'p.visit_id')
                ->on('m.reached_at', '>=', 'p.reached_at'))
            ->select('p.visit_id', DB::raw('MIN(m.reached_at) as reached_at'))
            ->groupBy('p.visit_id');
    }
}
