import AppLayout from '@/Layouts/AppLayout';
import RangeTabs from '@/Pages/Analytics/RangeTabs';
import ActivityHeatmap from '@/Pages/Analytics/partials/ActivityHeatmap';
import BreakdownCard from '@/Pages/Analytics/partials/BreakdownCard';
import FilterBar, { ActiveFilter } from '@/Pages/Analytics/partials/FilterBar';
import LiveVisitors from '@/Pages/Analytics/partials/LiveVisitors';
import VisitorsChart, { SeriesPoint } from '@/Pages/Analytics/partials/VisitorsChart';
import { BackLink } from '@/Components/ui';
import { CountryFlag } from '@/Components/icons/CountryFlag';
import { Favicon } from '@/Components/icons/Favicon';
import { PlatformIcon } from '@/Components/icons/PlatformIcon';
import WorldMap, { CountryDatum } from '@/Components/WorldMap';
import KpiTile from '@/Pages/Dashboard/KpiTile';
import { RankRow } from '@/Pages/Dashboard/RankedList';
import { countryName, languageName } from '@/lib/displayNames';
import { formatDuration, percentChange } from '@/lib/format';
import { HourBucket } from '@/lib/heatmap';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Clock, Megaphone, MousePointerClick, Users } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';

type FilterKey =
  | 'path'
  | 'entry_path'
  | 'exit_path'
  | 'referer_domain'
  | 'channel'
  | 'country_code'
  | 'browser'
  | 'os'
  | 'device'
  | 'language'
  | 'utm_source'
  | 'utm_medium'
  | 'utm_campaign';
type Filters = Partial<Record<FilterKey, string>>;
type Summary = { page_views: number; visitors: number; sessions: number; bounce_rate: number; avg_duration: number; campaign_share: number };
type Rank = Record<string, string | number> & { count: number };
type CampaignRow = { value: string; sessions: number; visitors: number };
type EventRow = { name: string; count: number; visitors: number };
type GoalCard = {
  id: string; name: string; type: string; match_value: string;
  conversions: number; visitors: number; rate: number;
  byCampaign: { value: string; conversions: number }[];
};

function CampaignList({
  title,
  rows,
  emptyLabel,
  onSelect,
}: {
  title: string;
  rows: CampaignRow[];
  emptyLabel: string;
  onSelect?: (value: string) => void;
}) {
  return (
    <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
      <div className="border-b border-line px-4 py-3">
        <h2 className="text-sm font-semibold text-foreground">{title}</h2>
      </div>
      {rows.length === 0 ? (
        <p className="px-4 py-6 text-center text-sm text-subtle">{emptyLabel}</p>
      ) : (
        <ul className="p-1.5">
          {rows.map((r, i) => {
            const content = (
              <>
                <span className="truncate text-[13.5px] font-semibold text-foreground">{r.value}</span>
                <span className="whitespace-nowrap text-sm font-bold tabular-nums text-foreground">
                  {r.sessions.toLocaleString()}
                  <span className="ml-1 text-xs font-normal text-subtle">({r.visitors.toLocaleString()})</span>
                </span>
              </>
            );
            return (
              <li key={i}>
                {onSelect ? (
                  <button
                    type="button"
                    onClick={() => onSelect(r.value)}
                    className="flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
                  >
                    {content}
                  </button>
                ) : (
                  <div className="flex items-center justify-between gap-3 rounded-lg px-2.5 py-2 hover:bg-elevated">{content}</div>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}

export default function AnalyticsIndex({
  site,
  days,
  filters,
  summary,
  previousSummary,
  liveVisitors,
  timeseries,
  topPaths,
  entryPages,
  exitPages,
  topReferrers,
  channels,
  countries,
  languages,
  clicksByCountry,
  browsers,
  operatingSystems,
  devices,
  hourlyActivity,
  utmSources,
  utmMediums,
  utmCampaigns,
  utmSourceMediums,
  utmTerms,
  utmContents,
  topEvents,
  goals,
}: {
  site: { id: string; name: string; domain: string };
  days: number;
  filters: Filters;
  summary: Summary;
  previousSummary: Summary;
  liveVisitors: number;
  timeseries: SeriesPoint[];
  topPaths: Rank[];
  entryPages: (Rank & { bounce_rate: number })[];
  exitPages: Rank[];
  topReferrers: Rank[];
  channels: (Rank & { channel: string; visitors: number })[];
  countries: Rank[];
  languages: Rank[];
  clicksByCountry: CountryDatum[];
  browsers: Rank[];
  operatingSystems: Rank[];
  devices: Rank[];
  hourlyActivity: HourBucket[];
  utmSources: CampaignRow[];
  utmMediums: CampaignRow[];
  utmCampaigns: CampaignRow[];
  utmSourceMediums: CampaignRow[];
  utmTerms: CampaignRow[];
  utmContents: CampaignRow[];
  topEvents: EventRow[];
  goals: GoalCard[];
}) {
  const { project } = usePage<PageProps>().props;
  const { t, locale } = useTranslation();
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const offStart = router.on('start', (event) => {
      if (event.detail.visit.only.length === 0) setLoading(true);
    });
    const offFinish = router.on('finish', () => setLoading(false));
    return () => {
      offStart();
      offFinish();
    };
  }, []);

  function go(nextDays: number, nextFilters: Filters) {
    router.get(
      route('app.project.analytics.show', { project: project!.id, site: site.id }),
      Object.keys(nextFilters).length > 0 ? { days: nextDays, filters: nextFilters } : { days: nextDays },
      { preserveState: true, preserveScroll: true },
    );
  }

  const addFilter = (key: FilterKey, value: string) => go(days, { ...filters, [key]: value });
  const removeFilter = (key: string) => go(days, Object.fromEntries(Object.entries(filters).filter(([k]) => k !== key)));

  const channelLabel = (channel: string) => t(`analytics.dashboard.channels.${channel}`);

  function filterValue(key: FilterKey, value: string): ReactNode {
    switch (key) {
      case 'channel':
        return channelLabel(value);
      case 'country_code':
        return (
          <span className="inline-flex items-center gap-1.5">
            <CountryFlag code={value} />
            {countryName(value, locale)}
          </span>
        );
      case 'language':
        return languageName(value, locale);
      default:
        return value;
    }
  }

  const activeFilters: ActiveFilter[] = (Object.entries(filters) as [FilterKey, string][]).map(([key, value]) => ({
    key,
    label: t(`analytics.dashboard.filters.keys.${key}`),
    value: filterValue(key, value),
  }));

  function rows<T extends Rank>(
    items: T[],
    labelKey: string,
    filterKey: FilterKey | null,
    extra: (row: T) => Partial<RankRow> = () => ({}),
  ): RankRow[] {
    return items.map((r, i) => {
      const raw = String(r[labelKey] ?? '');
      return {
        key: `${raw}-${i}`,
        label: raw || '—',
        value: Number(r.count),
        onClick: filterKey && raw ? () => addFilter(filterKey, raw) : undefined,
        title: filterKey && raw ? t('analytics.dashboard.filters.apply') : undefined,
        ...extra(r),
      };
    });
  }

  const noData = t('analytics.dashboard.no_data');
  const delta = (key: keyof Summary) => percentChange(summary[key], previousSummary[key]);

  return (
    <AppLayout title={t('analytics.dashboard.title', { name: site.name })}>
      <div className="px-8 py-8">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
          <div>
            <BackLink href={route('app.project.sites.index', { project: project!.id })}>{t('analytics.dashboard.back')}</BackLink>
            <div className="mt-1 flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-bold tracking-tight text-foreground">{site.name}</h1>
              <LiveVisitors count={liveVisitors} />
            </div>
            <p className="mt-1 text-sm text-muted">{site.domain}</p>
          </div>
          <RangeTabs days={days} onChange={(d) => go(d, filters)} />
        </div>

        <FilterBar items={activeFilters} onRemove={removeFilter} onClear={() => go(days, {})} />

        <div className={`transition-opacity duration-200 ${loading ? 'opacity-60' : ''}`}>
          <div className="mb-6 grid grid-cols-2 gap-3.5 md:grid-cols-5">
            <KpiTile compact={false} label={t('analytics.dashboard.kpi.page_views')} value={summary.page_views} deltaPct={delta('page_views')} icon={BarChart3} />
            <KpiTile compact={false} label={t('analytics.dashboard.kpi.unique_visitors')} value={summary.visitors} deltaPct={delta('visitors')} icon={Users} />
            <KpiTile
              compact={false}
              label={t('analytics.dashboard.kpi.bounce_rate')}
              value={`${summary.bounce_rate} %`}
              deltaPct={delta('bounce_rate')}
              lowerIsBetter
              icon={MousePointerClick}
            />
            <KpiTile
              compact={false}
              label={t('analytics.dashboard.kpi.avg_duration')}
              value={formatDuration(summary.avg_duration)}
              deltaPct={delta('avg_duration')}
              icon={Clock}
            />
            <KpiTile
              compact={false}
              label={t('analytics.dashboard.kpi.from_campaigns')}
              value={`${summary.campaign_share} %`}
              deltaPct={delta('campaign_share')}
              icon={Megaphone}
            />
          </div>

          <VisitorsChart title={t('analytics.dashboard.chart_title')} data={timeseries} />

          <div className="mb-6">
            <WorldMap data={clicksByCountry} title={t('analytics.dashboard.map_title')} />
          </div>

          <div className="mb-6 grid grid-cols-1 gap-3.5 md:grid-cols-2">
            <BreakdownCard
              title={t('analytics.dashboard.breakdown.pages')}
              emptyLabel={noData}
              tabs={[
                { key: 'top', label: t('analytics.dashboard.breakdown.top_pages'), rows: rows(topPaths, 'path', 'path') },
                {
                  key: 'entry',
                  label: t('analytics.dashboard.breakdown.entry_pages'),
                  rows: rows(entryPages, 'entry_path', 'entry_path', (r) => ({
                    sub: t('analytics.dashboard.breakdown.bounce_sub', { rate: r.bounce_rate }),
                  })),
                },
                { key: 'exit', label: t('analytics.dashboard.breakdown.exit_pages'), rows: rows(exitPages, 'exit_path', 'exit_path') },
              ]}
            />
            <BreakdownCard
              title={t('analytics.dashboard.breakdown.sources')}
              emptyLabel={noData}
              tabs={[
                {
                  key: 'channels',
                  label: t('analytics.dashboard.breakdown.channels'),
                  rows: rows(channels, 'channel', 'channel', (r) => ({ label: channelLabel(String(r.channel)) })),
                },
                {
                  key: 'referrers',
                  label: t('analytics.dashboard.breakdown.top_referrers'),
                  rows: rows(topReferrers, 'referer_domain', 'referer_domain', (r) => ({
                    prefix: <Favicon domain={String(r.referer_domain ?? '')} />,
                  })),
                },
              ]}
            />
            <BreakdownCard
              title={t('analytics.dashboard.breakdown.locations')}
              emptyLabel={noData}
              tabs={[
                {
                  key: 'countries',
                  label: t('analytics.dashboard.breakdown.countries'),
                  rows: rows(countries, 'country', null, (r) => {
                    const code = String(r.country_code ?? '');
                    return {
                      label: countryName(code, locale, String(r.country || '—')),
                      prefix: <CountryFlag code={code} />,
                      onClick: code ? () => addFilter('country_code', code) : undefined,
                      title: code ? t('analytics.dashboard.filters.apply') : undefined,
                    };
                  }),
                },
                {
                  key: 'languages',
                  label: t('analytics.dashboard.breakdown.languages'),
                  rows: rows(languages, 'language', 'language', (r) => ({
                    label: languageName(String(r.language), locale),
                    sub: String(r.language),
                  })),
                },
              ]}
            />
            <BreakdownCard
              title={t('analytics.dashboard.breakdown.technology')}
              emptyLabel={noData}
              tabs={[
                {
                  key: 'devices',
                  label: t('analytics.dashboard.breakdown.devices'),
                  rows: rows(devices, 'device', 'device', (r) => ({ prefix: <PlatformIcon kind="device" name={String(r.device ?? '')} /> })),
                },
                {
                  key: 'browsers',
                  label: t('analytics.dashboard.breakdown.browsers'),
                  rows: rows(browsers, 'browser', 'browser', (r) => ({ prefix: <PlatformIcon kind="browser" name={String(r.browser ?? '')} /> })),
                },
                {
                  key: 'os',
                  label: t('analytics.dashboard.breakdown.os'),
                  rows: rows(operatingSystems, 'os', 'os', (r) => ({ prefix: <PlatformIcon kind="os" name={String(r.os ?? '')} /> })),
                },
              ]}
            />
          </div>

          {days >= 7 && <ActivityHeatmap buckets={hourlyActivity} />}

          <h2 className="mb-1 mt-8 text-sm font-semibold uppercase tracking-wide text-muted">{t('analytics.dashboard.campaigns.title')}</h2>
          <p className="mb-4 text-xs text-subtle">{t('analytics.dashboard.campaigns.hint')}</p>
          <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
            <CampaignList
              title={t('analytics.dashboard.campaigns.sources')}
              rows={utmSources}
              emptyLabel={t('analytics.dashboard.campaigns.no_data')}
              onSelect={(v) => addFilter('utm_source', v)}
            />
            <CampaignList
              title={t('analytics.dashboard.campaigns.mediums')}
              rows={utmMediums}
              emptyLabel={t('analytics.dashboard.campaigns.no_data')}
              onSelect={(v) => addFilter('utm_medium', v)}
            />
            <CampaignList
              title={t('analytics.dashboard.campaigns.campaigns')}
              rows={utmCampaigns}
              emptyLabel={t('analytics.dashboard.campaigns.no_data')}
              onSelect={(v) => addFilter('utm_campaign', v)}
            />
            <CampaignList
              title={t('analytics.dashboard.campaigns.source_medium')}
              rows={utmSourceMediums}
              emptyLabel={t('analytics.dashboard.campaigns.no_data')}
            />
            <CampaignList title={t('analytics.dashboard.campaigns.terms')} rows={utmTerms} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
            <CampaignList title={t('analytics.dashboard.campaigns.content')} rows={utmContents} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
          </div>

          <h2 className="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-muted">{t('analytics.dashboard.events.title')}</h2>
          <section className="mb-6 rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
            {topEvents.length === 0 ? (
              <p className="px-4 py-6 text-center text-sm text-subtle">{t('analytics.dashboard.events.empty')}</p>
            ) : (
              <ul className="p-1.5">
                {topEvents.map((e, i) => (
                  <li key={i} className="flex items-center justify-between gap-3 rounded-lg px-2.5 py-2 hover:bg-elevated">
                    <Link
                      href={route('app.project.analytics.events.show', { project: project!.id, site: site.id, name: e.name })}
                      className="truncate font-mono text-[13.5px] font-semibold text-accent-soft-foreground hover:underline"
                    >
                      {e.name}
                    </Link>
                    <span className="whitespace-nowrap text-sm font-bold tabular-nums text-foreground">
                      {e.count.toLocaleString()}
                      <span className="ml-1 text-xs font-normal text-subtle">({e.visitors.toLocaleString()})</span>
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <div className="mb-3 mt-8 flex items-center justify-between">
            <h2 className="text-sm font-semibold uppercase tracking-wide text-muted">{t('analytics.dashboard.goals.title')}</h2>
            <Link
              href={route('app.project.analytics.goals.index', { project: project!.id, site: site.id })}
              className="text-sm text-accent-soft-foreground hover:underline"
            >
              {t('analytics.dashboard.goals.manage')}
            </Link>
          </div>
          {goals.length === 0 ? (
            <p className="text-sm text-muted">
              {t('analytics.dashboard.goals.empty')}{' '}
              <Link
                href={route('app.project.analytics.goals.create', { project: project!.id, site: site.id })}
                className="text-accent-soft-foreground hover:underline"
              >
                {t('analytics.dashboard.goals.create_one')}
              </Link>
            </p>
          ) : (
            <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
              {goals.map((g) => (
                <div key={g.id} className="rounded-[var(--radius)] border border-line bg-surface p-4 shadow-[var(--shadow-sm)]">
                  <div className="mb-2 flex items-baseline justify-between">
                    <h3 className="text-sm font-semibold text-foreground">{g.name}</h3>
                    <span className="text-2xl font-bold tabular-nums text-foreground">{g.rate} %</span>
                  </div>
                  <p className="mb-3 text-xs text-muted">
                    {t('analytics.dashboard.goals.stats', { conversions: g.conversions, visitors: g.visitors })}{' '}
                    <span className="font-mono">{g.match_value}</span>
                  </p>
                  {g.byCampaign.length > 0 && (
                    <ul className="space-y-1 border-t border-line pt-2">
                      {g.byCampaign.map((c, i) => (
                        <li key={i} className="flex justify-between text-xs text-muted">
                          <span className="truncate">{c.value}</span>
                          <span className="font-semibold text-foreground">{c.conversions}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
