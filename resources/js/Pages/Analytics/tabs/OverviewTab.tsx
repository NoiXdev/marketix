import { CountryFlag } from '@/Components/icons/CountryFlag';
import { PlatformIcon } from '@/Components/icons/PlatformIcon';
import { countryName } from '@/lib/displayNames';
import { formatDuration, percentChange } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import BreakdownCard from '@/Pages/Analytics/partials/BreakdownCard';
import { formatRange } from '@/Pages/Analytics/partials/period';
import VisitorsChart, { SeriesPoint } from '@/Pages/Analytics/partials/VisitorsChart';
import { Rank, Summary } from '@/Pages/Analytics/types';
import KpiTile from '@/Pages/Dashboard/KpiTile';
import { Activity, BarChart3, Clock, Megaphone, MousePointerClick, Users } from 'lucide-react';

export type OverviewData = {
  summary: Summary;
  previousSummary: Summary | null;
  timeseries: SeriesPoint[];
  previousTimeseries: SeriesPoint[] | null;
  topPaths: (Rank & { avg_engaged: number | null; avg_scroll: number | null })[];
  channels: (Rank & { channel: string; visitors: number })[];
  countries: Rank[];
  devices: Rank[];
};

export default function OverviewTab({ summary, previousSummary, timeseries, previousTimeseries, topPaths, channels, countries, devices }: OverviewData) {
  const { t, locale } = useTranslation();
  const { period, go, href, addFilter } = useAnalytics();
  const { rows, channelLabel, engagementSub } = useRowBuilders();

  const noData = t('analytics.dashboard.no_data');
  const percent = (value: number) => `${value.toLocaleString(locale)} %`;
  const delta = (key: keyof Summary) => (previousSummary ? percentChange(summary[key], previousSummary[key]) : undefined);
  const deltaLabel = t(period.compare === 'year' ? 'analytics.dashboard.compare.vs_year' : 'common.dashboard.vs_previous');
  const more = (tab: 'acquisition' | 'behavior' | 'audience') => ({ href: href({ tab }), label: t(`analytics.dashboard.tabs.${tab}.more`) });

  return (
    <>
      <div className="mb-6 grid grid-cols-1 gap-3.5 min-[340px]:grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
        <KpiTile
          compact={false}
          label={t('analytics.dashboard.kpi.page_views')}
          value={summary.page_views}
          deltaPct={delta('page_views')}
          deltaLabel={deltaLabel}
          icon={BarChart3}
        />
        <KpiTile compact={false} label={t('analytics.dashboard.kpi.unique_visitors')} value={summary.visitors} deltaPct={delta('visitors')} deltaLabel={deltaLabel} icon={Users} />
        <KpiTile
          compact={false}
          label={t('analytics.dashboard.kpi.engagement_rate')}
          value={percent(summary.engagement_rate)}
          deltaPct={delta('engagement_rate')}
          deltaLabel={deltaLabel}
          subtitle={t('analytics.dashboard.kpi.engagement_hint')}
          icon={Activity}
        />
        <KpiTile
          compact={false}
          label={t('analytics.dashboard.kpi.bounce_rate')}
          value={percent(summary.bounce_rate)}
          deltaPct={delta('bounce_rate')}
          deltaLabel={deltaLabel}
          subtitle={t('analytics.dashboard.kpi.bounce_hint')}
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
          value={percent(summary.campaign_share)}
          deltaPct={delta('campaign_share')}
          deltaLabel={deltaLabel}
          icon={Megaphone}
        />
      </div>

      <VisitorsChart
        title={t('analytics.dashboard.chart_title')}
        data={timeseries}
        previous={previousTimeseries}
        comparisonRange={period.compare_from && period.compare_to ? formatRange(period.compare_from, period.compare_to, locale) : null}
        interval={period.interval}
        intervals={period.intervals}
        onIntervalChange={(interval) => go({ interval })}
      />

      <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.top_pages')}
          emptyLabel={noData}
          more={more('behavior')}
          tabs={[{ key: 'top', label: '', rows: rows(topPaths, 'path', 'path', (r) => ({ note: engagementSub(r.avg_engaged, r.avg_scroll) })) }]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.channels')}
          emptyLabel={noData}
          more={more('acquisition')}
          tabs={[{ key: 'channels', label: '', rows: rows(channels, 'channel', 'channel', (r) => ({ label: channelLabel(String(r.channel)) })) }]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.countries')}
          emptyLabel={noData}
          more={more('audience')}
          tabs={[
            {
              key: 'countries',
              label: '',
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
          ]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.devices')}
          emptyLabel={noData}
          more={more('audience')}
          tabs={[
            {
              key: 'devices',
              label: '',
              rows: rows(devices, 'device', 'device', (r) => ({ prefix: <PlatformIcon kind="device" name={String(r.device ?? '')} /> })),
            },
          ]}
        />
      </div>
    </>
  );
}
