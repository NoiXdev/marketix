import { Favicon } from '@/Components/icons/Favicon';
import { LinkButton } from '@/Components/ui';
import { HourBucket } from '@/lib/heatmap';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import ActivityHeatmap from '@/Pages/Analytics/partials/ActivityHeatmap';
import AnalyticsEmptyState from '@/Pages/Analytics/partials/AnalyticsEmptyState';
import BreakdownCard, { breakdownTab } from '@/Pages/Analytics/partials/BreakdownCard';
import CardColumns from '@/Pages/Analytics/partials/CardColumns';
import { EventRow, Interactions, Rank, ValueRow } from '@/Pages/Analytics/types';
import { router } from '@inertiajs/react';
import { FileDown, FileX, Mail, Phone, Search, Zap } from 'lucide-react';

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
  const { t, locale } = useTranslation();
  const { projectId, site, period } = useAnalytics();
  const { rows, valueRows, titled, engagementSub } = useRowBuilders();
  const noData = t('analytics.dashboard.no_data');
  const unit = (key: string) => t(`analytics.dashboard.units.${key}`);
  const visitorsNote = (r: { visitors: number }) => ({ note: t('analytics.dashboard.units.visitors_count', { count: Number(r.visitors).toLocaleString(locale) }) });

  const eventRows = (items: EventRow[]) =>
    items.map((e, i) => ({
      key: `${e.name}-${i}`,
      label: e.name,
      value: Number(e.count),
      prefix: <Zap className="text-subtle mx-auto h-4 w-4" />,
      ...visitorsNote(e),
      onClick: () => router.visit(route('app.project.analytics.events.show', { project: projectId, site: site.id, name: e.name })),
      title: t('analytics.dashboard.events.open'),
    }));

  return (
    <>
      <CardColumns
        className="mb-6"
        left={
          <>
            <BreakdownCard
              title={t('analytics.dashboard.breakdown.pages')}
              emptyLabel={noData}
              tabs={[
                breakdownTab({
                  key: 'top',
                  label: t('analytics.dashboard.breakdown.top_pages'),
                  unit: unit('page_views'),
                  dimension: 'pages',
                  items: topPaths,
                  toRows: (items) => rows(items, 'path', 'path', (r) => ({ ...titled(r.path, r.title), note: engagementSub(r.avg_engaged, r.avg_scroll) })),
                }),
                breakdownTab({
                  key: 'entry',
                  label: t('analytics.dashboard.breakdown.entry_pages'),
                  unit: unit('sessions'),
                  dimension: 'entry_pages',
                  items: entryPages,
                  toRows: (items) =>
                    rows(items, 'entry_path', 'entry_path', (r) => ({
                      ...titled(r.entry_path, r.title),
                      note: t('analytics.dashboard.breakdown.bounce_sub', {
                        rate: Number(r.bounce_rate).toLocaleString(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
                      }),
                    })),
                }),
                breakdownTab({
                  key: 'exit',
                  label: t('analytics.dashboard.breakdown.exit_pages'),
                  unit: unit('sessions'),
                  dimension: 'exit_pages',
                  items: exitPages,
                  toRows: (items) => rows(items, 'exit_path', 'exit_path', (r) => titled(r.exit_path, r.title)),
                }),
                // Only worth a tab when the snippet runs on more than one host (subdomains, staging, …)
                ...(hostnames.length > 1
                  ? [
                      breakdownTab({
                        key: 'hostnames',
                        label: t('analytics.dashboard.breakdown.hostnames'),
                        unit: unit('page_views'),
                        dimension: 'hostnames',
                        items: hostnames,
                        toRows: (items) => rows(items, 'hostname', 'hostname'),
                      }),
                    ]
                  : []),
              ]}
            />
            <BreakdownCard
              title={t('analytics.dashboard.interactions.title')}
              emptyLabel={noData}
              tabs={[
                breakdownTab({
                  key: 'outbound',
                  label: t('analytics.dashboard.interactions.outbound'),
                  unit: unit('clicks'),
                  dimension: 'outbound',
                  items: interactions.outbound,
                  toRows: (items: ValueRow[]) => valueRows(items, (r) => ({ prefix: <Favicon domain={r.value.split('/')[0]} /> })),
                }),
                breakdownTab({
                  key: 'downloads',
                  label: t('analytics.dashboard.interactions.downloads'),
                  unit: unit('downloads'),
                  dimension: 'downloads',
                  items: interactions.downloads,
                  toRows: (items: ValueRow[]) =>
                    valueRows(items, (r) => ({
                      label: r.value.split('/').pop() || r.value,
                      sub: r.value,
                      prefix: <FileDown className="text-subtle mx-auto h-4 w-4" />,
                    })),
                }),
                breakdownTab({
                  key: 'phone',
                  label: t('analytics.dashboard.interactions.phone'),
                  unit: unit('clicks'),
                  dimension: 'phone',
                  items: interactions.phone,
                  toRows: (items: ValueRow[]) => valueRows(items, () => ({ prefix: <Phone className="text-subtle mx-auto h-4 w-4" /> })),
                }),
                breakdownTab({
                  key: 'email',
                  label: t('analytics.dashboard.interactions.email'),
                  unit: unit('clicks'),
                  dimension: 'email',
                  items: interactions.email,
                  toRows: (items: ValueRow[]) => valueRows(items, () => ({ prefix: <Mail className="text-subtle mx-auto h-4 w-4" /> })),
                }),
              ]}
            />
          </>
        }
        right={
          <>
            <BreakdownCard
              title={t('analytics.dashboard.events.title')}
              emptyLabel={t('analytics.dashboard.events.empty')}
              tabs={[breakdownTab({ key: 'events', label: '', unit: unit('events'), dimension: 'events', items: topEvents, toRows: eventRows })]}
            />
            <BreakdownCard
              title={t('analytics.dashboard.interactions.search_title')}
              emptyLabel={noData}
              tabs={[
                {
                  ...breakdownTab({
                    key: 'searches',
                    label: t('analytics.dashboard.interactions.searches'),
                    unit: unit('searches'),
                    dimension: 'searches',
                    items: interactions.searches,
                    toRows: (items: ValueRow[]) => valueRows(items, () => ({ prefix: <Search className="text-subtle mx-auto h-4 w-4" /> })),
                  }),
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
                  ...breakdownTab({
                    key: 'not_found',
                    label: t('analytics.dashboard.interactions.not_found'),
                    unit: unit('page_views'),
                    dimension: 'not_found',
                    items: interactions.notFound,
                    toRows: (items: ValueRow[]) => valueRows(items, () => ({ prefix: <FileX className="text-subtle mx-auto h-4 w-4" /> })),
                  }),
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
          </>
        }
      />

      {period.days >= 7 && <ActivityHeatmap buckets={hourlyActivity} timezone={site.timezone} />}
    </>
  );
}
