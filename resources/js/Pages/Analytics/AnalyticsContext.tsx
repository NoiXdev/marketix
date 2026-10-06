import { formatDuration } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { Comparison, Interval, Period } from '@/Pages/Analytics/partials/period';
import { FilterKey, Filters, Rank, SiteInfo, TabKey, ValueRow } from '@/Pages/Analytics/types';
import { RankRow } from '@/Pages/Dashboard/RankedList';
import { router } from '@inertiajs/react';
import { createContext, ReactNode, useContext } from 'react';

export type Navigation = {
  tab?: TabKey;
  range?: string;
  from?: string;
  to?: string;
  compare?: Comparison;
  interval?: Interval;
  filters?: Filters;
};

type AnalyticsContextValue = {
  projectId: string;
  site: SiteInfo;
  tab: TabKey;
  period: Period;
  filters: Filters;
  go: (next: Navigation) => void;
  href: (next: Navigation) => string;
  addFilter: (key: FilterKey, value: string) => void;
};

const AnalyticsContext = createContext<AnalyticsContextValue | null>(null);

export function AnalyticsProvider({
  projectId,
  site,
  tab,
  period,
  filters,
  children,
}: {
  projectId: string;
  site: SiteInfo;
  tab: TabKey;
  period: Period;
  filters: Filters;
  children: ReactNode;
}) {
  function params(next: Navigation): Record<string, string | Filters> {
    const range = next.range ?? period.range;
    const rangeChanged = next.range !== undefined && (next.range !== period.range || range === 'custom');
    const nextFilters = next.filters ?? filters;
    const compare = next.compare ?? period.compare;
    const interval = next.interval ?? (rangeChanged ? null : period.interval);
    const nextTab = next.tab ?? tab;
    const result: Record<string, string | Filters> = { range };

    if (nextTab !== 'overview') result.tab = nextTab;
    if (range === 'custom') {
      result.from = next.from ?? period.from;
      result.to = next.to ?? period.to;
    }
    if (compare !== 'previous') result.compare = compare;
    if (interval) result.interval = interval;
    if (Object.keys(nextFilters).length > 0) result.filters = nextFilters;

    return result;
  }

  function href(next: Navigation): string {
    return route('app.project.analytics.show', { project: projectId, site: site.id, ...params(next) });
  }

  function go(next: Navigation) {
    router.get(route('app.project.analytics.show', { project: projectId, site: site.id }), params(next), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  const value: AnalyticsContextValue = {
    projectId,
    site,
    tab,
    period,
    filters,
    go,
    href,
    addFilter: (key, filterValue) => go({ filters: { ...filters, [key]: filterValue } }),
  };

  return <AnalyticsContext.Provider value={value}>{children}</AnalyticsContext.Provider>;
}

export function useAnalytics(): AnalyticsContextValue {
  const context = useContext(AnalyticsContext);
  if (!context) throw new Error('useAnalytics must be used inside AnalyticsProvider');
  return context;
}

export function useRowBuilders() {
  const { addFilter } = useAnalytics();
  const { t } = useTranslation();

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

  // Pages with a recorded title show it as the label and the path below it
  function titled(path: unknown, title: unknown): Partial<RankRow> {
    return title ? { label: String(title), sub: String(path) } : {};
  }

  function channelLabel(channel: string): string {
    return t(`analytics.dashboard.channels.${channel}`);
  }

  function engagementSub(seconds: number | null, scroll: number | null): string | undefined {
    if (seconds === null) return undefined;
    return scroll === null
      ? t('analytics.dashboard.breakdown.engagement_time', { time: formatDuration(seconds) })
      : t('analytics.dashboard.breakdown.engagement', { time: formatDuration(seconds), scroll });
  }

  return { rows, valueRows, titled, channelLabel, engagementSub };
}
