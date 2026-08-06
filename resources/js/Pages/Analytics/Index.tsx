import AppLayout from '@/Layouts/AppLayout';
import RangeTabs from '@/Pages/Analytics/RangeTabs';
import { BackLink } from '@/Components/ui';
import KpiTile from '@/Pages/Dashboard/KpiTile';
import RankedList from '@/Pages/Dashboard/RankedList';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Clock, Megaphone, MousePointerClick, Users } from 'lucide-react';

type Series = { date: string; views: number; visitors: number };
type Rank = Record<string, string | number> & { count: number };
type CampaignRow = { value: string; sessions: number; visitors: number };
type CampaignShare = { total: number; from_campaigns: number; percent: number };
type EventRow = { name: string; count: number; visitors: number };
type GoalCard = {
  id: string; name: string; type: string; match_value: string;
  conversions: number; visitors: number; rate: number;
  byCampaign: { value: string; conversions: number }[];
};

function VisitorsBars({ data, max, viewsLabel }: { data: Series[]; max: number; viewsLabel: string }) {
  return (
    <div className="flex h-40 items-end gap-px">
      {data.map((d) => (
        <div key={d.date} className="group relative flex flex-1 flex-col items-center">
          <div
            className="w-full rounded-t bg-accent transition-all"
            style={{ height: `${Math.max((d.views / max) * 100, d.views > 0 ? 4 : 1)}%` }}
          />
          <div className="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs text-canvas shadow-[var(--shadow)] group-hover:block">
            <p className="font-semibold">
              {d.date}: {d.views.toLocaleString()} {viewsLabel}
            </p>
          </div>
        </div>
      ))}
    </div>
  );
}

function Breakdown({ title, rows, labelKey, emptyLabel }: { title: string; rows: Rank[]; labelKey: string; emptyLabel: string }) {
  return (
    <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
      <div className="border-b border-line px-4 py-3">
        <h2 className="text-sm font-semibold text-foreground">{title}</h2>
      </div>
      <RankedList
        emptyLabel={emptyLabel}
        rows={rows.map((r, i) => ({ key: `${String(r[labelKey] ?? '')}-${i}`, label: String(r[labelKey] || '—'), value: r.count }))}
      />
    </section>
  );
}

function CampaignList({ title, rows, emptyLabel }: { title: string; rows: CampaignRow[]; emptyLabel: string }) {
  return (
    <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
      <div className="border-b border-line px-4 py-3">
        <h2 className="text-sm font-semibold text-foreground">{title}</h2>
      </div>
      {rows.length === 0 ? (
        <p className="px-4 py-6 text-center text-sm text-subtle">{emptyLabel}</p>
      ) : (
        <ul className="p-1.5">
          {rows.map((r, i) => (
            <li key={i} className="flex items-center justify-between gap-3 rounded-lg px-2.5 py-2 hover:bg-elevated">
              <span className="truncate text-[13.5px] font-semibold text-foreground">{r.value}</span>
              <span className="whitespace-nowrap text-sm font-bold tabular-nums text-foreground">
                {r.sessions.toLocaleString()}
                <span className="ml-1 text-xs font-normal text-subtle">({r.visitors.toLocaleString()})</span>
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

export default function AnalyticsIndex({
  site,
  days,
  totalPageViews,
  uniqueVisitors,
  bounceRate,
  avgDurationSeconds,
  pageViewsByDay,
  topPaths,
  topReferrers,
  countries,
  browsers,
  operatingSystems,
  devices,
  campaignShare,
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
  totalPageViews: number;
  uniqueVisitors: number;
  bounceRate: number;
  avgDurationSeconds: number;
  pageViewsByDay: Series[];
  topPaths: Rank[];
  topReferrers: Rank[];
  countries: Rank[];
  browsers: Rank[];
  operatingSystems: Rank[];
  devices: Rank[];
  campaignShare: CampaignShare;
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
  const { t } = useTranslation();
  const maxViews = Math.max(1, ...pageViewsByDay.map((d) => d.views));

  function setDays(d: number) {
    router.get(route('app.project.analytics.show', { project: project!.id, site: site.id }), { days: d }, { preserveState: true });
  }

  return (
    <AppLayout title={t('analytics.dashboard.title', { name: site.name })}>
      <div className="px-8 py-8">
        {/* Header */}
        <div className="mb-6 flex items-center justify-between gap-4">
          <div>
            <BackLink href={route('app.project.sites.index', { project: project!.id })}>{t('analytics.dashboard.back')}</BackLink>
            <h1 className="mt-1 text-2xl font-bold tracking-tight text-foreground">{site.name}</h1>
            <p className="mt-1 text-sm text-muted">{site.domain}</p>
          </div>
          <RangeTabs days={days} onChange={setDays} />
        </div>

        {/* KPIs */}
        <div className="mb-6 grid grid-cols-2 gap-3.5 md:grid-cols-5">
          <KpiTile compact={false} label={t('analytics.dashboard.kpi.page_views')} value={totalPageViews} icon={BarChart3} />
          <KpiTile compact={false} label={t('analytics.dashboard.kpi.unique_visitors')} value={uniqueVisitors} icon={Users} />
          <KpiTile compact={false} label={t('analytics.dashboard.kpi.bounce_rate')} value={`${bounceRate} %`} icon={MousePointerClick} />
          <KpiTile
            compact={false}
            label={t('analytics.dashboard.kpi.avg_duration')}
            value={`${Math.floor(avgDurationSeconds / 60)}m ${avgDurationSeconds % 60}s`}
            icon={Clock}
          />
          <KpiTile compact={false} label={t('analytics.dashboard.kpi.from_campaigns')} value={`${campaignShare.percent} %`} icon={Megaphone} />
        </div>

        {/* Visitors over time */}
        <section className="mb-6 rounded-[var(--radius)] border border-line bg-surface p-6 shadow-[var(--shadow-sm)]">
          <h2 className="mb-4 text-sm font-semibold text-foreground">{t('analytics.dashboard.chart_title')}</h2>
          <VisitorsBars data={pageViewsByDay} max={maxViews} viewsLabel={t('analytics.dashboard.kpi.page_views')} />
        </section>

        {/* Breakdowns */}
        <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
          <Breakdown title={t('analytics.dashboard.breakdown.top_pages')} rows={topPaths} labelKey="path" emptyLabel={t('analytics.dashboard.no_data')} />
          <Breakdown
            title={t('analytics.dashboard.breakdown.top_referrers')}
            rows={topReferrers}
            labelKey="referer_domain"
            emptyLabel={t('analytics.dashboard.no_data')}
          />
          <Breakdown title={t('analytics.dashboard.breakdown.countries')} rows={countries} labelKey="country" emptyLabel={t('analytics.dashboard.no_data')} />
          <Breakdown title={t('analytics.dashboard.breakdown.browsers')} rows={browsers} labelKey="browser" emptyLabel={t('analytics.dashboard.no_data')} />
          <Breakdown title={t('analytics.dashboard.breakdown.os')} rows={operatingSystems} labelKey="os" emptyLabel={t('analytics.dashboard.no_data')} />
          <Breakdown title={t('analytics.dashboard.breakdown.devices')} rows={devices} labelKey="device" emptyLabel={t('analytics.dashboard.no_data')} />
        </div>

        {/* Campaigns */}
        <h2 className="mb-1 mt-8 text-sm font-semibold uppercase tracking-wide text-muted">{t('analytics.dashboard.campaigns.title')}</h2>
        <p className="mb-4 text-xs text-subtle">{t('analytics.dashboard.campaigns.hint')}</p>
        <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
          <CampaignList title={t('analytics.dashboard.campaigns.sources')} rows={utmSources} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
          <CampaignList title={t('analytics.dashboard.campaigns.mediums')} rows={utmMediums} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
          <CampaignList title={t('analytics.dashboard.campaigns.campaigns')} rows={utmCampaigns} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
          <CampaignList
            title={t('analytics.dashboard.campaigns.source_medium')}
            rows={utmSourceMediums}
            emptyLabel={t('analytics.dashboard.campaigns.no_data')}
          />
          <CampaignList title={t('analytics.dashboard.campaigns.terms')} rows={utmTerms} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
          <CampaignList title={t('analytics.dashboard.campaigns.content')} rows={utmContents} emptyLabel={t('analytics.dashboard.campaigns.no_data')} />
        </div>

        {/* Events */}
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

        {/* Goals */}
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
    </AppLayout>
  );
}
