import { useTranslation } from '@/lib/i18n';
import { useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import { GoalCard } from '@/Pages/Analytics/types';
import { Link } from '@inertiajs/react';

export type ConversionsData = {
  goals: GoalCard[];
};

export default function ConversionsTab({ goals }: ConversionsData) {
  const { t } = useTranslation();
  const { projectId, site } = useAnalytics();

  return (
    <>
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
                <span className="text-foreground text-2xl font-bold tabular-nums">{g.rate} %</span>
              </div>
              <p className="text-muted mb-3 text-xs">
                {t('analytics.dashboard.goals.stats', { conversions: g.conversions, visitors: g.visitors })} <span className="font-mono">{g.match_value}</span>
              </p>
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
