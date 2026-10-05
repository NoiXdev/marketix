import { CountryFlag } from '@/Components/icons/CountryFlag';
import { Favicon } from '@/Components/icons/Favicon';
import { PlatformIcon } from '@/Components/icons/PlatformIcon';
import { BackLink } from '@/Components/ui';
import WorldMap, { CountryDatum } from '@/Components/WorldMap';
import AppLayout from '@/Layouts/AppLayout';
import { countryName, languageName } from '@/lib/displayNames';
import { formatDuration, percentChange } from '@/lib/format';
import { HourBucket } from '@/lib/heatmap';
import { useTranslation } from '@/lib/i18n';
import ActivityHeatmap from '@/Pages/Analytics/partials/ActivityHeatmap';
import BreakdownCard from '@/Pages/Analytics/partials/BreakdownCard';
import DateRangePicker from '@/Pages/Analytics/partials/DateRangePicker';
import FilterBar, { ActiveFilter } from '@/Pages/Analytics/partials/FilterBar';
import LiveVisitors from '@/Pages/Analytics/partials/LiveVisitors';
import { COMPARISONS, Comparison, Interval, Period } from '@/Pages/Analytics/partials/period';
import VisitorsChart, { SeriesPoint } from '@/Pages/Analytics/partials/VisitorsChart';
import KpiTile from '@/Pages/Dashboard/KpiTile';
import { RankRow } from '@/Pages/Dashboard/RankedList';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Clock, FileDown, FileX, Megaphone, MousePointerClick, Search, Users } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';

type FilterKey =
  'path' | 'entry_path' | 'exit_path' | 'referer_domain' | 'channel' | 'country_code' | 'browser' | 'os' | 'device' | 'language' | 'utm_source' | 'utm_medium' | 'utm_campaign';
type Filters = Partial<Record<FilterKey, string>>;
type Summary = { page_views: number; visitors: number; sessions: number; bounce_rate: number; avg_duration: number; campaign_share: number };
type Rank = Record<string, string | number> & { count: number };
type CampaignRow = { value: string; sessions: number; visitors: number };
type EventRow = { name: string; count: number; visitors: number };
type ValueRow = { value: string; count: number; visitors: number };
type Interactions = { outbound: ValueRow[]; downloads: ValueRow[]; searches: ValueRow[]; notFound: ValueRow[] };
type GoalCard = {
  id: string;
  name: string;
  type: string;
  match_value: string;
  conversions: number;
  visitors: number;
  rate: number;
  byCampaign: { value: string; conversions: number }[];
};

function CampaignList({ title, rows, emptyLabel, onSelect }: { title: string; rows: CampaignRow[]; emptyLabel: string; onSelect?: (value: string) => void }) {
  return (
    <section className="border-line bg-surface rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
      <div className="border-line border-b px-4 py-3">
        <h2 className="text-foreground text-sm font-semibold">{title}</h2>
      </div>
      {rows.length === 0 ? (
        <p className="text-subtle px-4 py-6 text-center text-sm">{emptyLabel}</p>
      ) : (
        <ul className="p-1.5">
          {rows.map((r, i) => {
            const content = (
              <>
                <span className="text-foreground truncate text-[13.5px] font-semibold">{r.value}</span>
                <span className="text-foreground text-sm font-bold whitespace-nowrap tabular-nums">
                  {r.sessions.toLocaleString()}
                  <span className="text-subtle ml-1 text-xs font-normal">({r.visitors.toLocaleString()})</span>
                </span>
              </>
            );
            return (
              <li key={i}>
                {onSelect ? (
                  <button
                    type="button"
                    onClick={() => onSelect(r.value)}
                    className="hover:bg-elevated flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
                  >
                    {content}
                  </button>
                ) : (
                  <div className="hover:bg-elevated flex items-center justify-between gap-3 rounded-lg px-2.5 py-2">{content}</div>
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
  period,
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
  interactions,
  goals,
}: {
  site: { id: string; name: string; domain: string; search_enabled: boolean };
  period: Period;
  filters: Filters;
  summary: Summary;
  previousSummary: Summary | null;
  liveVisitors: number;
  timeseries: SeriesPoint[];
  topPaths: (Rank & { avg_engaged: number | null; avg_scroll: number | null })[];
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
  interactions: Interactions;
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

  function go(next: { range?: string; from?: string; to?: string; compare?: Comparison; interval?: Interval; filters?: Filters }) {
    const range = next.range ?? period.range;
    const rangeChanged = next.range !== undefined && (next.range !== period.range || range === 'custom');
    const nextFilters = next.filters ?? filters;
    const compare = next.compare ?? period.compare;
    const params: Record<string, string | Filters> = { range };

    if (range === 'custom') {
      params.from = next.from ?? period.from;
      params.to = next.to ?? period.to;
    }
    const interval = next.interval ?? (rangeChanged ? null : period.interval);
    if (compare !== 'previous') params.compare = compare;
    if (interval) params.interval = interval;
    if (Object.keys(nextFilters).length > 0) params.filters = nextFilters;

    router.get(route('app.project.analytics.show', { project: project!.id, site: site.id }), params, {
      preserveState: true,
      preserveScroll: true,
    });
  }

  const addFilter = (key: FilterKey, value: string) => go({ filters: { ...filters, [key]: value } });
  const removeFilter = (key: string) => go({ filters: Object.fromEntries(Object.entries(filters).filter(([k]) => k !== key)) });

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

  function rows<T extends Rank>(items: T[], labelKey: string, filterKey: FilterKey | null, extra: (row: T) => Partial<RankRow> = () => ({})): RankRow[] {
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

  function valueRows(items: ValueRow[], extra: (row: ValueRow) => Partial<RankRow> = () => ({})): RankRow[] {
    return items.map((r, i) => ({ key: `${r.value}-${i}`, label: r.value, value: Number(r.count), ...extra(r) }));
  }

  function engagementSub(seconds: number | null, scroll: number | null): string | undefined {
    if (seconds === null) return undefined;
    return scroll === null
      ? t('analytics.dashboard.breakdown.engagement_time', { time: formatDuration(seconds) })
      : t('analytics.dashboard.breakdown.engagement', { time: formatDuration(seconds), scroll });
  }

  const noData = t('analytics.dashboard.no_data');
  const delta = (key: keyof Summary) => (previousSummary ? percentChange(summary[key], previousSummary[key]) : undefined);
  const deltaLabel = t(period.compare === 'year' ? 'analytics.dashboard.compare.vs_year' : 'common.dashboard.vs_previous');

  return (
    <AppLayout title={t('analytics.dashboard.title', { name: site.name })}>
      <div className="px-8 py-8">
        <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
          <div>
            <BackLink href={route('app.project.sites.index', { project: project!.id })}>{t('analytics.dashboard.back')}</BackLink>
            <div className="mt-1 flex flex-wrap items-center gap-3">
              <h1 className="text-foreground text-2xl font-bold tracking-tight">{site.name}</h1>
              <LiveVisitors count={liveVisitors} />
            </div>
            <p className="text-muted mt-1 text-sm">{site.domain}</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <DateRangePicker period={period} onSelect={(range, custom) => go({ range, ...custom })} />
            <select
              aria-label={t('analytics.dashboard.compare.label')}
              value={period.compare}
              onChange={(e) => go({ compare: e.target.value as Comparison })}
              className="rounded-lg border border-line bg-surface py-1.5 pl-3 pr-8 text-sm font-semibold text-foreground shadow-[var(--shadow-sm)] hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
            >
              {COMPARISONS.map((option) => (
                <option key={option} value={option}>
                  {t(`analytics.dashboard.compare.${option}`)}
                </option>
              ))}
            </select>
          </div>
        </div>

        <FilterBar items={activeFilters} onRemove={removeFilter} onClear={() => go({ filters: {} })} />

        <div className={`transition-opacity duration-200 ${loading ? 'opacity-60' : ''}`}>
          <div className="mb-6 grid grid-cols-2 gap-3.5 md:grid-cols-5">
            <KpiTile
              compact={false}
              label={t('analytics.dashboard.kpi.page_views')}
              value={summary.page_views}
              deltaPct={delta('page_views')}
              deltaLabel={deltaLabel}
              icon={BarChart3}
            />
            <KpiTile
              compact={false}
              label={t('analytics.dashboard.kpi.unique_visitors')}
              value={summary.visitors}
              deltaPct={delta('visitors')}
              deltaLabel={deltaLabel}
              icon={Users}
            />
            <KpiTile
              compact={false}
              label={t('analytics.dashboard.kpi.bounce_rate')}
              value={`${summary.bounce_rate} %`}
              deltaPct={delta('bounce_rate')}
              deltaLabel={deltaLabel}
              lowerIsBetter
              icon={MousePointerClick}
            />
            <KpiTile
              compact={false}
              label={t('analytics.dashboard.kpi.avg_duration')}
              value={formatDuration(summary.avg_duration)}
              deltaPct={delta('avg_duration')}
              deltaLabel={deltaLabel}
              icon={Clock}
            />
            <KpiTile
              compact={false}
              label={t('analytics.dashboard.kpi.from_campaigns')}
              value={`${summary.campaign_share} %`}
              deltaPct={delta('campaign_share')}
              deltaLabel={deltaLabel}
              icon={Megaphone}
            />
          </div>

          <VisitorsChart
            title={t('analytics.dashboard.chart_title')}
            data={timeseries}
            interval={period.interval}
            intervals={period.intervals}
            onIntervalChange={(interval) => go({ interval })}
          />

          <div className="mb-6">
            <WorldMap data={clicksByCountry} title={t('analytics.dashboard.map_title')} />
          </div>

          <div className="mb-6 grid grid-cols-1 gap-3.5 md:grid-cols-2">
            <BreakdownCard
              title={t('analytics.dashboard.breakdown.pages')}
              emptyLabel={noData}
              tabs={[
                {
                  key: 'top',
                  label: t('analytics.dashboard.breakdown.top_pages'),
                  rows: rows(topPaths, 'path', 'path', (r) => ({ sub: engagementSub(r.avg_engaged, r.avg_scroll) })),
                },
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

          <div className="mb-6 grid grid-cols-1 gap-3.5 md:grid-cols-2">
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
                  emptyLabel: site.search_enabled ? noData : t('analytics.dashboard.interactions.search_disabled'),
                },
                {
                  key: 'not_found',
                  label: t('analytics.dashboard.interactions.not_found'),
                  rows: valueRows(interactions.notFound, () => ({ prefix: <FileX className="text-subtle mx-auto h-4 w-4" /> })),
                  emptyLabel: t('analytics.dashboard.interactions.not_found_hint'),
                },
              ]}
            />
          </div>

          {period.days >= 7 && <ActivityHeatmap buckets={hourlyActivity} />}

          <h2 className="text-muted mt-8 mb-1 text-sm font-semibold tracking-wide uppercase">{t('analytics.dashboard.campaigns.title')}</h2>
          <p className="text-subtle mb-4 text-xs">{t('analytics.dashboard.campaigns.hint')}</p>
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
            <CampaignList title={t('analytics.dashboard.campaigns.source_medium')} rows={utmSourceMediums} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
            <CampaignList title={t('analytics.dashboard.campaigns.terms')} rows={utmTerms} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
            <CampaignList title={t('analytics.dashboard.campaigns.content')} rows={utmContents} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
          </div>

          <h2 className="text-muted mt-8 mb-3 text-sm font-semibold tracking-wide uppercase">{t('analytics.dashboard.events.title')}</h2>
          <section className="border-line bg-surface mb-6 rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
            {topEvents.length === 0 ? (
              <p className="text-subtle px-4 py-6 text-center text-sm">{t('analytics.dashboard.events.empty')}</p>
            ) : (
              <ul className="p-1.5">
                {topEvents.map((e, i) => (
                  <li key={i} className="hover:bg-elevated flex items-center justify-between gap-3 rounded-lg px-2.5 py-2">
                    <Link
                      href={route('app.project.analytics.events.show', { project: project!.id, site: site.id, name: e.name })}
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

          <div className="mt-8 mb-3 flex items-center justify-between">
            <h2 className="text-muted text-sm font-semibold tracking-wide uppercase">{t('analytics.dashboard.goals.title')}</h2>
            <Link href={route('app.project.analytics.goals.index', { project: project!.id, site: site.id })} className="text-accent-soft-foreground text-sm hover:underline">
              {t('analytics.dashboard.goals.manage')}
            </Link>
          </div>
          {goals.length === 0 ? (
            <p className="text-muted text-sm">
              {t('analytics.dashboard.goals.empty')}{' '}
              <Link href={route('app.project.analytics.goals.create', { project: project!.id, site: site.id })} className="text-accent-soft-foreground hover:underline">
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
        </div>
      </div>
    </AppLayout>
  );
}
