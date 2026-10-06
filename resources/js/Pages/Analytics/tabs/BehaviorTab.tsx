import { Favicon } from '@/Components/icons/Favicon';
import { HourBucket } from '@/lib/heatmap';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import ActivityHeatmap from '@/Pages/Analytics/partials/ActivityHeatmap';
import BreakdownCard from '@/Pages/Analytics/partials/BreakdownCard';
import { EventRow, Interactions, Rank } from '@/Pages/Analytics/types';
import { Link } from '@inertiajs/react';
import { LinkButton } from '@/Components/ui';
import AnalyticsEmptyState from '@/Pages/Analytics/partials/AnalyticsEmptyState';
import { FileDown, FileX, Search } from 'lucide-react';

export type BehaviorData = {
  topPaths: (Rank & { title: string | null; avg_engaged: number | null; avg_scroll: number | null })[];
  entryPages: (Rank & { title: string | null; bounce_rate: number })[];
  exitPages: (Rank & { title: string | null })[];
  hostnames: (Rank & { hostname: string })[];
  interactions: Interactions;
  topEvents: EventRow[];
  hourlyActivity: HourBucket[];
};

export default function BehaviorTab({ topPaths, entryPages, exitPages, hostnames, interactions, topEvents, hourlyActivity }: BehaviorData) {
  const { t } = useTranslation();
  const { projectId, site, period } = useAnalytics();
  const { rows, valueRows, titled, engagementSub } = useRowBuilders();
  const noData = t('analytics.dashboard.no_data');

  return (
    <>
      <div className="mb-6 grid grid-cols-1 gap-3.5 md:grid-cols-2">
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.pages')}
          emptyLabel={noData}
          tabs={[
            {
              key: 'top',
              label: t('analytics.dashboard.breakdown.top_pages'),
              rows: rows(topPaths, 'path', 'path', (r) => ({ ...titled(r.path, r.title), note: engagementSub(r.avg_engaged, r.avg_scroll) })),
            },
            {
              key: 'entry',
              label: t('analytics.dashboard.breakdown.entry_pages'),
              rows: rows(entryPages, 'entry_path', 'entry_path', (r) => ({
                ...titled(r.entry_path, r.title),
                note: t('analytics.dashboard.breakdown.bounce_sub', { rate: r.bounce_rate }),
              })),
            },
            { key: 'exit', label: t('analytics.dashboard.breakdown.exit_pages'), rows: rows(exitPages, 'exit_path', 'exit_path', (r) => titled(r.exit_path, r.title)) },
            // Only worth a tab when the snippet runs on more than one host (subdomains, staging, …)
            ...(hostnames.length > 1
              ? [{ key: 'hostnames', label: t('analytics.dashboard.breakdown.hostnames'), rows: rows(hostnames, 'hostname', 'hostname') }]
              : []),
          ]}
        />

        <section className="border-line bg-surface rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
          <div className="border-line border-b px-4 py-3">
            <h2 className="text-foreground text-sm font-semibold">{t('analytics.dashboard.events.title')}</h2>
          </div>
          {topEvents.length === 0 ? (
            <p className="text-subtle px-4 py-6 text-center text-sm">{t('analytics.dashboard.events.empty')}</p>
          ) : (
            <ul className="p-1.5">
              {topEvents.map((e, i) => (
                <li key={i} className="hover:bg-elevated flex items-center justify-between gap-3 rounded-lg px-2.5 py-2">
                  <Link
                    href={route('app.project.analytics.events.show', { project: projectId, site: site.id, name: e.name })}
                    className="text-accent-soft-foreground truncate font-mono text-[13.5px] font-semibold hover:underline"
                  >
                    {e.name}
                  </Link>
                  <span className="text-foreground text-sm font-bold whitespace-nowrap tabular-nums">
                    {e.count.toLocaleString()}
                    <span className="text-subtle ml-1 text-xs font-normal">({e.visitors.toLocaleString()})</span>
                  </span>
                </li>
              ))}
            </ul>
          )}
        </section>

        <BreakdownCard
          title={t('analytics.dashboard.interactions.title')}
          emptyLabel={noData}
          tabs={[
            {
              key: 'outbound',
              label: t('analytics.dashboard.interactions.outbound'),
              rows: valueRows(interactions.outbound, (r) => ({ prefix: <Favicon domain={r.value.split('/')[0]} /> })),
            },
            {
              key: 'downloads',
              label: t('analytics.dashboard.interactions.downloads'),
              rows: valueRows(interactions.downloads, (r) => ({
                label: r.value.split('/').pop() || r.value,
                sub: r.value,
                prefix: <FileDown className="text-subtle mx-auto h-4 w-4" />,
              })),
            },
          ]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.interactions.search_title')}
          emptyLabel={noData}
          tabs={[
            {
              key: 'searches',
              label: t('analytics.dashboard.interactions.searches'),
              rows: valueRows(interactions.searches, () => ({ prefix: <Search className="text-subtle mx-auto h-4 w-4" /> })),
              empty: site.search_enabled ? undefined : (
                <AnalyticsEmptyState
                  icon={Search}
                  title={t('analytics.dashboard.interactions.search_disabled_title')}
                  text={t('analytics.dashboard.interactions.search_disabled_text')}
                  action={
                    <LinkButton variant="secondary" size="sm" href={route('app.project.sites.edit', { project: projectId, site: site.id })}>
                      {t('analytics.dashboard.interactions.open_settings')}
                    </LinkButton>
                  }
                />
              ),
            },
            {
              key: 'not_found',
              label: t('analytics.dashboard.interactions.not_found'),
              rows: valueRows(interactions.notFound, () => ({ prefix: <FileX className="text-subtle mx-auto h-4 w-4" /> })),
              empty: (
                <AnalyticsEmptyState
                  icon={FileX}
                  title={t('analytics.dashboard.interactions.not_found_empty_title')}
                  text={t('analytics.dashboard.interactions.not_found_empty_text')}
                  code="marketix('404');"
                />
              ),
            },
          ]}
        />
      </div>

      {period.days >= 7 && <ActivityHeatmap buckets={hourlyActivity} />}
    </>
  );
}
