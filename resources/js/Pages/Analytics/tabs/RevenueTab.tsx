import { CountryFlag } from '@/Components/icons/CountryFlag';
import { countryName } from '@/lib/displayNames';
import { formatMoney, percentChange } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import AnalyticsEmptyState from '@/Pages/Analytics/partials/AnalyticsEmptyState';
import BreakdownCard from '@/Pages/Analytics/partials/BreakdownCard';
import RevenueChart, { RevenuePoint } from '@/Pages/Analytics/partials/RevenueChart';
import KpiTile from '@/Pages/Dashboard/KpiTile';
import { RankRow } from '@/Pages/Dashboard/RankedList';
import { Banknote, Percent, ReceiptText, ShoppingBag, UserRound } from 'lucide-react';

type RevenueSummary = {
  revenue: number;
  orders: number;
  average_order_value: number;
  purchase_rate: number;
  revenue_per_visitor: number;
};

type RevenueRow = { value: string; revenue: number; orders: number };

export type RevenueData = {
  revenue: {
    currency: string;
    currencies: { currency: string; revenue: number; orders: number }[];
    summary: RevenueSummary;
    previousSummary: RevenueSummary | null;
    timeseries: RevenuePoint[];
    channels: RevenueRow[];
    campaigns: RevenueRow[];
    landingPages: RevenueRow[];
    countries: RevenueRow[];
  };
};

const SNIPPET = "marketix('event', 'purchase', { value: 49.90, currency: 'CHF' });";

export default function RevenueTab({ revenue }: RevenueData) {
  const { t, locale } = useTranslation();
  const { period, go } = useAnalytics();
  const { channelLabel } = useRowBuilders();
  const { currency, summary, previousSummary } = revenue;

  const money = (value: number) => formatMoney(value, currency, locale);
  const delta = (key: keyof RevenueSummary) => (previousSummary ? percentChange(summary[key], previousSummary[key]) : undefined);
  const deltaLabel = t(period.compare === 'year' ? 'analytics.dashboard.compare.vs_year' : 'common.dashboard.vs_previous');
  const noData = t('analytics.dashboard.no_data');
  const otherCurrencies = revenue.currencies.slice(1);

  function revenueRows(items: RevenueRow[], extra: (row: RevenueRow) => Partial<RankRow> = () => ({})): RankRow[] {
    return items.map((row, i) => ({
      key: `${row.value}-${i}`,
      label: row.value,
      value: row.revenue,
      display: money(row.revenue),
      note: t('analytics.dashboard.revenue.orders_count', { count: row.orders.toLocaleString(locale) }),
      ...extra(row),
    }));
  }

  if (revenue.currencies.length === 0 && !previousSummary?.orders) {
    return (
      <AnalyticsEmptyState
        framed
        icon={ShoppingBag}
        title={t('analytics.dashboard.revenue.empty_title')}
        text={t('analytics.dashboard.revenue.empty_text')}
        code={SNIPPET}
      />
    );
  }

  return (
    <>
      <div className="mb-6 grid grid-cols-2 gap-3.5 md:grid-cols-5">
        <KpiTile
          compact={false}
          label={t('analytics.dashboard.revenue.kpi.revenue')}
          value={money(summary.revenue)}
          deltaPct={delta('revenue')}
          deltaLabel={deltaLabel}
          icon={Banknote}
        />
        <KpiTile compact={false} label={t('analytics.dashboard.revenue.kpi.orders')} value={summary.orders} deltaPct={delta('orders')} deltaLabel={deltaLabel} icon={ReceiptText} />
        <KpiTile
          compact={false}
          label={t('analytics.dashboard.revenue.kpi.average_order_value')}
          value={money(summary.average_order_value)}
          deltaPct={delta('average_order_value')}
          deltaLabel={deltaLabel}
          icon={ShoppingBag}
        />
        <KpiTile
          compact={false}
          label={t('analytics.dashboard.revenue.kpi.purchase_rate')}
          value={`${summary.purchase_rate.toLocaleString(locale)} %`}
          deltaPct={delta('purchase_rate')}
          deltaLabel={deltaLabel}
          icon={Percent}
        />
        <KpiTile
          compact={false}
          label={t('analytics.dashboard.revenue.kpi.revenue_per_visitor')}
          value={money(summary.revenue_per_visitor)}
          deltaPct={delta('revenue_per_visitor')}
          deltaLabel={deltaLabel}
          icon={UserRound}
        />
      </div>

      {otherCurrencies.length > 0 && (
        <p className="text-muted mb-4 text-xs">
          {t('analytics.dashboard.revenue.other_currencies', {
            currency: currency || '—',
            others: otherCurrencies.map((c) => formatMoney(c.revenue, c.currency, locale)).join(', '),
          })}
        </p>
      )}

      <RevenueChart data={revenue.timeseries} currency={currency} interval={period.interval} intervals={period.intervals} onIntervalChange={(interval) => go({ interval })} />

      <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
        <BreakdownCard
          title={t('analytics.dashboard.revenue.by_channel')}
          emptyLabel={noData}
          tabs={[{ key: 'channels', label: '', rows: revenueRows(revenue.channels, (row) => ({ label: channelLabel(row.value) })) }]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.revenue.by_campaign')}
          emptyLabel={t('analytics.dashboard.campaigns.no_data')}
          tabs={[{ key: 'campaigns', label: '', rows: revenueRows(revenue.campaigns) }]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.revenue.by_landing_page')}
          emptyLabel={noData}
          tabs={[{ key: 'landing', label: '', rows: revenueRows(revenue.landingPages) }]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.revenue.by_country')}
          emptyLabel={noData}
          tabs={[
            {
              key: 'countries',
              label: '',
              rows: revenueRows(revenue.countries, (row) => ({ label: countryName(row.value, locale, row.value), prefix: <CountryFlag code={row.value} /> })),
            },
          ]}
        />
      </div>

      <p className="text-subtle mt-4 text-xs">{t('analytics.dashboard.revenue.hint')}</p>
    </>
  );
}
