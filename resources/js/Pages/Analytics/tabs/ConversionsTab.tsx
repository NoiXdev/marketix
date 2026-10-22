import { LinkButton } from '@/Components/ui';
import { formatMoney, formatPercent } from '@/lib/format';
import { goalMatchLabel } from '@/lib/goals';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import AnalyticsEmptyState from '@/Pages/Analytics/partials/AnalyticsEmptyState';
import CardColumns from '@/Pages/Analytics/partials/CardColumns';
import FunnelCard from '@/Pages/Analytics/partials/FunnelCard';
import { FunnelReport, GoalCard } from '@/Pages/Analytics/types';
import { Link } from '@inertiajs/react';
import { Filter, Plus } from 'lucide-react';

export type ConversionsData = {
  goals: GoalCard[];
  funnels: FunnelReport[];
};

function GoalTile({ goal }: { goal: GoalCard }) {
  const { t, locale } = useTranslation();
  const { addFilter } = useAnalytics();
  const { channelLabel } = useRowBuilders();
  const number = (n: number) => n.toLocaleString(locale);

  return (
    <div className="border-line bg-surface rounded-[var(--radius)] border p-4 shadow-[var(--shadow-sm)]">
      <div className="mb-2 flex items-baseline justify-between gap-3">
        <h3 className="text-foreground text-sm font-semibold">{goal.name}</h3>
        <span className="text-foreground text-2xl font-bold tabular-nums">{formatPercent(goal.rate, locale)}</span>
      </div>
      <p className="text-muted mb-3 text-xs">
        {t('analytics.dashboard.goals.stats', { conversions: number(goal.conversions), visitors: number(goal.visitors) })}{' '}
        <span className="font-mono break-all">{goalMatchLabel(goal)}</span>
      </p>
      {goal.value !== null && (
        <p className="text-muted mb-3 text-xs">
          {t('analytics.dashboard.goals.value', {
            value: formatMoney(goal.value, goal.currency ?? '', locale),
            unit: formatMoney(goal.unit_value ?? 0, goal.currency ?? '', locale),
          })}
        </p>
      )}
      {goal.byChannel.length > 0 && (
        <div className="border-line border-t pt-2">
          <p className="text-subtle mb-1 flex justify-between text-[11px] font-semibold">
            <span>{t('analytics.dashboard.goals.by_channel')}</span>
            <span>{t('analytics.dashboard.units.conversions')}</span>
          </p>
          <ul className="space-y-0.5">
            {goal.byChannel.map((row) => (
              <li key={row.channel}>
                <button
                  type="button"
                  onClick={() => addFilter('channel', row.channel)}
                  title={t('analytics.dashboard.filters.apply')}
                  className="text-muted hover:bg-elevated -mx-1.5 flex w-[calc(100%+0.75rem)] items-center justify-between gap-3 rounded px-1.5 py-0.5 text-left text-xs focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
                >
                  <span className="truncate">{channelLabel(row.channel)}</span>
                  <span className="whitespace-nowrap tabular-nums">
                    <span className="text-foreground font-semibold">{number(row.conversions)}</span>
                    {goal.conversions > 0 && <span className="text-subtle ml-1.5">{formatPercent((row.conversions / goal.conversions) * 100, locale)}</span>}
                  </span>
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

export default function ConversionsTab({ goals, funnels }: ConversionsData) {
  const { t } = useTranslation();
  const { projectId, site } = useAnalytics();
  const createFunnel = route('app.project.analytics.funnels.create', { project: projectId, site: site.id });

  return (
    <>
      <div className="mb-1 flex items-center justify-between gap-3">
        <h2 className="text-muted text-sm font-semibold tracking-wide uppercase">{t('analytics.dashboard.funnels.title')}</h2>
        {funnels.length > 0 && (
          <Link href={createFunnel} className="text-accent-soft-foreground inline-flex items-center gap-1 text-sm hover:underline">
            <Plus className="h-4 w-4" />
            {t('analytics.dashboard.funnels.create')}
          </Link>
        )}
      </div>
      <p className="text-subtle mb-3 text-xs">{t('analytics.dashboard.funnels.hint')}</p>
      {funnels.length === 0 ? (
        <div className="mb-8">
          <AnalyticsEmptyState
            framed
            icon={Filter}
            title={t('analytics.dashboard.funnels.empty_title')}
            text={t('analytics.dashboard.funnels.empty_text')}
            action={<LinkButton href={createFunnel}>{t('analytics.dashboard.funnels.create')}</LinkButton>}
          />
        </div>
      ) : (
        <div className="mb-8 grid grid-cols-1 items-start gap-3.5 xl:grid-cols-2">
          {funnels.map((funnel) => (
            <FunnelCard key={funnel.id} funnel={funnel} />
          ))}
        </div>
      )}

      <div className="mb-1 flex items-center justify-between">
        <h2 className="text-muted text-sm font-semibold tracking-wide uppercase">{t('analytics.dashboard.goals.title')}</h2>
        <Link href={route('app.project.analytics.goals.index', { project: projectId, site: site.id })} className="text-accent-soft-foreground text-sm hover:underline">
          {t('analytics.dashboard.goals.manage')}
        </Link>
      </div>
      <p className="text-subtle mb-3 text-xs">{t('analytics.dashboard.goals.hint')}</p>
      {goals.length === 0 ? (
        <p className="text-muted text-sm">
          {t('analytics.dashboard.goals.empty')}{' '}
          <Link href={route('app.project.analytics.goals.create', { project: projectId, site: site.id })} className="text-accent-soft-foreground hover:underline">
            {t('analytics.dashboard.goals.create_one')}
          </Link>
        </p>
      ) : (
        <CardColumns
          left={goals
            .filter((_, i) => i % 2 === 0)
            .map((goal) => (
              <GoalTile key={goal.id} goal={goal} />
            ))}
          right={goals
            .filter((_, i) => i % 2 === 1)
            .map((goal) => (
              <GoalTile key={goal.id} goal={goal} />
            ))}
        />
      )}
    </>
  );
}
