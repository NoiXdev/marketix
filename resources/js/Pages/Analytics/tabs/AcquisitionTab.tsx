import { Favicon } from '@/Components/icons/Favicon';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import BreakdownCard from '@/Pages/Analytics/partials/BreakdownCard';
import CampaignList from '@/Pages/Analytics/partials/CampaignList';
import { CampaignRow, Rank } from '@/Pages/Analytics/types';

export type AcquisitionData = {
  channels: (Rank & { channel: string; visitors: number })[];
  topReferrers: Rank[];
  utmSources: CampaignRow[];
  utmMediums: CampaignRow[];
  utmCampaigns: CampaignRow[];
  utmSourceMediums: CampaignRow[];
  utmTerms: CampaignRow[];
  utmContents: CampaignRow[];
};

export default function AcquisitionTab({ channels, topReferrers, utmSources, utmMediums, utmCampaigns, utmSourceMediums, utmTerms, utmContents }: AcquisitionData) {
  const { t } = useTranslation();
  const { addFilter } = useAnalytics();
  const { rows, channelLabel } = useRowBuilders();
  const noData = t('analytics.dashboard.no_data');
  const noCampaigns = t('analytics.dashboard.campaigns.no_data');

  return (
    <>
      <div className="mb-8 grid grid-cols-1 gap-3.5 md:grid-cols-2">
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.channels')}
          emptyLabel={noData}
          tabs={[{ key: 'channels', label: '', rows: rows(channels, 'channel', 'channel', (r) => ({ label: channelLabel(String(r.channel)) })) }]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.top_referrers')}
          emptyLabel={noData}
          tabs={[
            {
              key: 'referrers',
              label: '',
              rows: rows(topReferrers, 'referer_domain', 'referer_domain', (r) => ({ prefix: <Favicon domain={String(r.referer_domain ?? '')} /> })),
            },
          ]}
        />
      </div>

      <h2 className="text-muted mb-1 text-sm font-semibold tracking-wide uppercase">{t('analytics.dashboard.campaigns.title')}</h2>
      <p className="text-subtle mb-4 text-xs">{t('analytics.dashboard.campaigns.hint')}</p>
      <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
        <CampaignList title={t('analytics.dashboard.campaigns.sources')} rows={utmSources} emptyLabel={noCampaigns} onSelect={(v) => addFilter('utm_source', v)} />
        <CampaignList title={t('analytics.dashboard.campaigns.mediums')} rows={utmMediums} emptyLabel={noCampaigns} onSelect={(v) => addFilter('utm_medium', v)} />
        <CampaignList title={t('analytics.dashboard.campaigns.campaigns')} rows={utmCampaigns} emptyLabel={noCampaigns} onSelect={(v) => addFilter('utm_campaign', v)} />
        <CampaignList title={t('analytics.dashboard.campaigns.source_medium')} rows={utmSourceMediums} emptyLabel={noCampaigns} />
        <CampaignList title={t('analytics.dashboard.campaigns.terms')} rows={utmTerms} emptyLabel={noCampaigns} />
        <CampaignList title={t('analytics.dashboard.campaigns.content')} rows={utmContents} emptyLabel={noCampaigns} />
      </div>
    </>
  );
}
