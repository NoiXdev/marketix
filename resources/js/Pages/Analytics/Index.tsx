import { CountryFlag } from '@/Components/icons/CountryFlag';
import { BackLink } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { countryName, languageName } from '@/lib/displayNames';
import { useTranslation } from '@/lib/i18n';
import { AnalyticsProvider, useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import AnalyticsTabs from '@/Pages/Analytics/partials/AnalyticsTabs';
import DateRangePicker from '@/Pages/Analytics/partials/DateRangePicker';
import FilterBar, { ActiveFilter } from '@/Pages/Analytics/partials/FilterBar';
import LiveVisitors from '@/Pages/Analytics/partials/LiveVisitors';
import { COMPARISONS, Comparison, Period } from '@/Pages/Analytics/partials/period';
import TrackingSetup from '@/Pages/Analytics/partials/TrackingSetup';
import AcquisitionTab, { AcquisitionData } from '@/Pages/Analytics/tabs/AcquisitionTab';
import AudienceTab, { AudienceData } from '@/Pages/Analytics/tabs/AudienceTab';
import BehaviorTab, { BehaviorData } from '@/Pages/Analytics/tabs/BehaviorTab';
import ConversionsTab, { ConversionsData } from '@/Pages/Analytics/tabs/ConversionsTab';
import OverviewTab, { OverviewData } from '@/Pages/Analytics/tabs/OverviewTab';
import RealtimeTab, { RealtimeData } from '@/Pages/Analytics/tabs/RealtimeTab';
import RevenueTab, { RevenueData } from '@/Pages/Analytics/tabs/RevenueTab';
import { FilterKey, Filters, SiteInfo, TabKey } from '@/Pages/Analytics/types';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';

type TabData = OverviewData & RealtimeData & AcquisitionData & BehaviorData & AudienceData & ConversionsData & RevenueData;

type AnalyticsPageProps = {
  site: SiteInfo;
  tab: TabKey;
  period: Period;
  filters: Filters;
  liveVisitors: number;
} & Partial<TabData>;

function ActiveTab({ tab, data }: { tab: TabKey; data: Partial<TabData> }) {
  switch (tab) {
    case 'realtime':
      return <RealtimeTab {...(data as RealtimeData)} />;
    case 'acquisition':
      return <AcquisitionTab {...(data as AcquisitionData)} />;
    case 'behavior':
      return <BehaviorTab {...(data as BehaviorData)} />;
    case 'audience':
      return <AudienceTab {...(data as AudienceData)} />;
    case 'conversions':
      return <ConversionsTab {...(data as ConversionsData)} />;
    case 'revenue':
      return <RevenueTab {...(data as RevenueData)} />;
    default:
      return <OverviewTab {...(data as OverviewData)} />;
  }
}

function Header({ liveVisitors }: { liveVisitors: number }) {
  const { t } = useTranslation();
  const { projectId, site, tab, period, go } = useAnalytics();

  return (
    <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
      <div>
        <BackLink href={route('app.project.sites.index', { project: projectId })}>{t('analytics.dashboard.back')}</BackLink>
        <div className="mt-1 flex flex-wrap items-center gap-3">
          <h1 className="text-foreground text-2xl font-bold tracking-tight">{site.name}</h1>
          {site.has_data && <LiveVisitors count={liveVisitors} />}
        </div>
        <p className="text-muted mt-1 text-sm">{site.domain}</p>
      </div>
      {!site.has_data ? null : tab === 'realtime' ? (
        <span className="border-line bg-surface text-muted inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold shadow-[var(--shadow-sm)]">
          <Clock className="text-subtle h-4 w-4" />
          {t('analytics.dashboard.realtime.window')}
        </span>
      ) : (
        <div className="flex flex-wrap items-center gap-2">
          <DateRangePicker period={period} onSelect={(range, custom) => go({ range, ...custom })} />
          <select
            aria-label={t('analytics.dashboard.compare.label')}
            value={period.compare}
            onChange={(e) => go({ compare: e.target.value as Comparison })}
            className="border-line bg-surface text-foreground hover:bg-elevated rounded-lg border py-1.5 pr-8 pl-3 text-sm font-semibold shadow-[var(--shadow-sm)] focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
          >
            {COMPARISONS.map((option) => (
              <option key={option} value={option}>
                {t(`analytics.dashboard.compare.${option}`)}
              </option>
            ))}
          </select>
        </div>
      )}
    </div>
  );
}

function ActiveFilters() {
  const { t, locale } = useTranslation();
  const { filters, go } = useAnalytics();

  function filterValue(key: FilterKey, value: string): ReactNode {
    switch (key) {
      case 'channel':
        return t(`analytics.dashboard.channels.${value}`);
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

  const items: ActiveFilter[] = (Object.entries(filters) as [FilterKey, string][]).map(([key, value]) => ({
    key,
    label: t(`analytics.dashboard.filters.keys.${key}`),
    value: filterValue(key, value),
  }));

  return (
    <FilterBar items={items} onRemove={(key) => go({ filters: Object.fromEntries(Object.entries(filters).filter(([k]) => k !== key)) })} onClear={() => go({ filters: {} })} />
  );
}

export default function AnalyticsIndex({ site, tab, period, filters, liveVisitors, ...data }: AnalyticsPageProps) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
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

  return (
    <AppLayout title={t('analytics.dashboard.title', { name: site.name })}>
      <AnalyticsProvider projectId={project!.id} site={site} tab={tab} period={period} filters={filters}>
        <div className="px-8 py-8">
          <Header liveVisitors={liveVisitors} />
          {site.has_data ? (
            <>
              <AnalyticsTabs />
              {tab !== 'realtime' && <ActiveFilters />}
              <div className={`transition-opacity duration-200 ${loading ? 'opacity-60' : ''}`}>
                <ActiveTab tab={tab} data={data} />
              </div>
            </>
          ) : (
            <>
              <TrackingSetup />
              <div
                inert
                aria-hidden
                className="pointer-events-none max-h-[560px] overflow-hidden [mask-image:linear-gradient(to_bottom,black_35%,transparent)] opacity-50 grayscale select-none"
              >
                <ActiveTab tab={tab} data={data} />
              </div>
            </>
          )}
        </div>
      </AnalyticsProvider>
    </AppLayout>
  );
}
