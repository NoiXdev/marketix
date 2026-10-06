import { LinkButton } from '@/Components/ui';
import { formatMoney } from '@/lib/format';
import { goalMatchLabel } from '@/lib/goals';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import AnalyticsEmptyState from '@/Pages/Analytics/partials/AnalyticsEmptyState';
import FunnelCard from '@/Pages/Analytics/partials/FunnelCard';
import { FunnelReport, GoalCard } from '@/Pages/Analytics/types';
import { Link } from '@inertiajs/react';
import { Filter, Plus } from 'lucide-react';

export type ConversionsData = {
  goals: GoalCard[];
  funnels: FunnelReport[];
};

export default function ConversionsTab({ goals, funnels }: ConversionsData) {
  const { t, locale } = useTranslation();
  const { projectId, site } = useAnalytics();
  const createFunnel = route('app.project.analytics.funnels.create', { project: projectId, site: site.id });

  return (
    <>
      <div className="mb-3 flex items-center justify-between gap-3">
        <h2 className="text-muted text-sm font-semibold tracking-wide uppercase">{t('analytics.dashboard.funnels.title')}</h2>
        {funnels.length > 0 && (
          <Link href={createFunnel} className="text-accent-soft-foreground inline-flex items-center gap-1 text-sm hover:underline">
            <Plus className="h-4 w-4" />
            {t('analytics.dashboard.funnels.create')}
          </Link>
        )}
      </div>
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
        <div className="mb-8 grid grid-cols-1 gap-3.5 xl:grid-cols-2">
          {funnels.map((funnel) => (
            <FunnelCard key={funnel.id} funnel={funnel} />
          ))}
        </div>
      )}

      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-muted text-sm font-semibold tracking-wide uppercase">{t('analytics.dashboard.goals.title')}</h2>
        <Link href={route('app.project.analytics.goals.index', { project: projectId, site: site.id })} className="text-accent-soft-foreground text-sm hover:underline">
          {t('analytics.dashboard.goals.manage')}
        </Link>
      </div>
      {goals.length === 0 ? (
        <p className="text-muted text-sm">
          {t('analytics.dashboard.goals.empty')}{' '}
          <Link href={route('app.project.analytics.goals.create', { project: projectId, site: site.id })} className="text-accent-soft-foreground hover:underline">
            {t('analytics.dashboard.goals.create_one')}
          </Link>
        </p>
      ) : (
        <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
          {goals.map((g) => (
            <div key={g.id} className="border-line bg-surface rounded-[var(--radius)] border p-4 shadow-[var(--shadow-sm)]">
              <div className="mb-2 flex items-baseline justify-between">
                <h3 className="text-foreground text-sm font-semibold">{g.name}</h3>
                <span className="text-foreground text-2xl font-bold tabular-nums">{g.rate.toLocaleString(locale)} %</span>
              </div>
              <p className="text-muted mb-3 text-xs">
                {t('analytics.dashboard.goals.stats', { conversions: g.conversions, visitors: g.visitors })} <span className="font-mono break-all">{goalMatchLabel(g)}</span>
              </p>
              {g.value !== null && (
                <p className="text-muted mb-3 text-xs">
                  {t('analytics.dashboard.goals.value', {
                    value: formatMoney(g.value, g.currency ?? '', locale),
                    unit: formatMoney(g.unit_value ?? 0, g.currency ?? '', locale),
                  })}
                </p>
              )}
              {g.byCampaign.length > 0 && (
                <ul className="border-line space-y-1 border-t pt-2">
                  {g.byCampaign.map((c, i) => (
                    <li key={i} className="text-muted flex justify-between text-xs">
                      <span className="truncate">{c.value}</span>
                      <span className="text-foreground font-semibold">{c.conversions}</span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          ))}
        </div>
      )}
    </>
  );
}
