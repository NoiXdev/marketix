import { Favicon } from '@/Components/icons/Favicon';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import BreakdownCard, { breakdownTab } from '@/Pages/Analytics/partials/BreakdownCard';
import CardColumns from '@/Pages/Analytics/partials/CardColumns';
import ChannelTable from '@/Pages/Analytics/partials/ChannelTable';
import { CampaignRow, ChannelRow, FilterKey, Rank } from '@/Pages/Analytics/types';
import { RankRow } from '@/Pages/Dashboard/RankedList';

export type AcquisitionData = {
  channels: ChannelRow[];
  topReferrers: Rank[];
  utmCampaigns: CampaignRow[];
  utmSourceMediums: (CampaignRow & { source: string; medium: string | null })[];
  utmTerms: CampaignRow[];
  utmContents: CampaignRow[];
};

export default function AcquisitionTab({ channels, topReferrers, utmCampaigns, utmSourceMediums, utmTerms, utmContents }: AcquisitionData) {
  const { t, locale } = useTranslation();
  const { addFilter, addFilters } = useAnalytics();
  const { rows } = useRowBuilders();
  const noData = t('analytics.dashboard.no_data');
  const noCampaigns = t('analytics.dashboard.campaigns.no_data');
  const sessions = t('analytics.dashboard.units.sessions');

  function campaignRows<T extends CampaignRow>(items: T[], select?: (row: T) => void): RankRow[] {
    return items.map((r, i) => ({
      key: `${r.value}-${i}`,
      label: r.value,
      value: Number(r.sessions),
      note: t('analytics.dashboard.units.visitors_count', { count: Number(r.visitors).toLocaleString(locale) }),
      onClick: select ? () => select(r) : undefined,
      title: select ? t('analytics.dashboard.filters.apply') : undefined,
    }));
  }

  const filterBy = (key: FilterKey) => (row: CampaignRow) => addFilter(key, row.value);

  return (
    <>
      <CardColumns
        className="mb-8"
        left={<ChannelTable title={t('analytics.dashboard.breakdown.channels')} channels={channels} emptyLabel={noData} />}
        right={
          <BreakdownCard
            title={t('analytics.dashboard.breakdown.top_referrers')}
            emptyLabel={noData}
            tabs={[
              breakdownTab({
                key: 'referrers',
                label: '',
                unit: sessions,
                dimension: 'referrers',
                items: topReferrers,
                toRows: (items) => rows(items, 'referer_domain', 'referer_domain', (r) => ({ prefix: <Favicon domain={String(r.referer_domain ?? '')} /> })),
              }),
            ]}
          />
        }
      />

      <h2 className="text-muted mb-1 text-sm font-semibold tracking-wide uppercase">{t('analytics.dashboard.campaigns.title')}</h2>
      <p className="text-subtle mb-4 text-xs">{t('analytics.dashboard.campaigns.hint')}</p>
      <CardColumns
        left={
          <>
            <BreakdownCard
              title={t('analytics.dashboard.campaigns.campaigns')}
              emptyLabel={noCampaigns}
              tabs={[
                breakdownTab({
                  key: 'campaigns',
                  label: '',
                  unit: sessions,
                  dimension: 'campaigns',
                  items: utmCampaigns,
                  toRows: (items) => campaignRows(items, filterBy('utm_campaign')),
                }),
              ]}
            />
            <BreakdownCard
              title={t('analytics.dashboard.campaigns.terms')}
              emptyLabel={noCampaigns}
              tabs={[breakdownTab({ key: 'terms', label: '', unit: sessions, dimension: 'terms', items: utmTerms, toRows: (items) => campaignRows(items) })]}
            />
          </>
        }
        right={
          <>
            <BreakdownCard
              title={t('analytics.dashboard.campaigns.source_medium')}
              emptyLabel={noCampaigns}
              tabs={[
                breakdownTab({
                  key: 'source_medium',
                  label: '',
                  unit: sessions,
                  dimension: 'source_medium',
                  items: utmSourceMediums,
                  toRows: (items) => campaignRows(items, (r) => addFilters(r.medium ? { utm_source: r.source, utm_medium: r.medium } : { utm_source: r.source })),
                }),
              ]}
            />
            <BreakdownCard
              title={t('analytics.dashboard.campaigns.content')}
              emptyLabel={noCampaigns}
              tabs={[breakdownTab({ key: 'contents', label: '', unit: sessions, dimension: 'contents', items: utmContents, toRows: (items) => campaignRows(items) })]}
            />
          </>
        }
      />
    </>
  );
}
