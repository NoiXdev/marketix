import type { GoalCondition } from '@/types';

/** "purchase · plan = pro": the goal's match value followed by its property conditions. */
export function goalMatchLabel(goal: { match_value: string; conditions: GoalCondition[] }): string {
  return [goal.match_value, ...goal.conditions.map((c) => `${c.property} = ${c.value}`)].join(' · ');
}
