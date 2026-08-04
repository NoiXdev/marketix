import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';

type Series = { date: string; views: number; visitors: number };
type Rank = Record<string, string | number> & { count: number };
type CampaignRow = { value: string; sessions: number; visitors: number };
type CampaignShare = { total: number; from_campaigns: number; percent: number };

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
}) {
  const { project } = usePage<PageProps>().props;
  const maxViews = Math.max(1, ...pageViewsByDay.map((d) => d.views));

  function setDays(d: number) {
    router.get(route('app.project.analytics.show', { project: project!.id, site: site.id }), { days: d }, { preserveState: true });
  }

  const tile = (label: string, value: string | number) => (
    <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
      <p className="text-xs uppercase text-gray-500">{label}</p>
      <p className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{value}</p>
    </div>
  );

  const list = (title: string, rows: Rank[], key: string) => (
    <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
      <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{title}</h3>
      <ul className="space-y-1">
        {rows.map((r, i) => (
          <li key={i} className="flex justify-between text-sm text-gray-600 dark:text-gray-300">
            <span className="truncate">{String(r[key] ?? '—')}</span>
            <span className="font-medium">{r.count}</span>
          </li>
        ))}
        {rows.length === 0 && <li className="text-sm text-gray-400">No data</li>}
      </ul>
    </div>
  );

  const campaignList = (title: string, rows: CampaignRow[]) => (
    <div className="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
      <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">{title}</h3>
      <ul className="space-y-1">
        {rows.map((r, i) => (
          <li key={i} className="flex justify-between gap-2 text-sm text-gray-600 dark:text-gray-300">
            <span className="truncate">{r.value}</span>
            <span className="whitespace-nowrap font-medium">
              {r.sessions}
              <span className="ml-1 text-xs text-gray-400">({r.visitors})</span>
            </span>
          </li>
        ))}
        {rows.length === 0 && <li className="text-sm text-gray-400">No campaign data</li>}
      </ul>
    </div>
  );

  return (
    <AppLayout title={`Analytics — ${site.name}`}>
      <div className="px-8 py-8">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link href={route('app.project.sites.index', { project: project!.id })} className="text-sm text-indigo-600 hover:underline">
              ← Sites
            </Link>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">{site.name}</h1>
            <p className="text-sm text-gray-500">{site.domain}</p>
          </div>
          <div className="flex gap-1">
            {[1, 7, 30, 90].map((d) => (
              <button
                key={d}
                onClick={() => setDays(d)}
                className={`rounded-lg px-3 py-1.5 text-sm ${days === d ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'}`}
              >
                {d === 1 ? 'Today' : `${d}d`}
              </button>
            ))}
          </div>
        </div>

        <div className="mb-6 grid grid-cols-2 gap-4 md:grid-cols-5">
          {tile('Page views', totalPageViews)}
          {tile('Unique visitors', uniqueVisitors)}
          {tile('Bounce rate', `${bounceRate}%`)}
          {tile('Avg. duration', `${Math.floor(avgDurationSeconds / 60)}m ${avgDurationSeconds % 60}s`)}
          {tile('From campaigns', `${campaignShare.percent}%`)}
        </div>

        <div className="mb-6 rounded-xl border border-gray-200 p-4 dark:border-gray-700">
          <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Visitors over time</h3>
          <div className="flex h-40 items-end gap-1">
            {pageViewsByDay.map((d) => (
              <div key={d.date} className="flex flex-1 flex-col items-center justify-end" title={`${d.date}: ${d.views} views`}>
                <div className="w-full rounded-t bg-indigo-500" style={{ height: `${(d.views / maxViews) * 100}%` }} />
              </div>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {list('Top pages', topPaths, 'path')}
          {list('Top referrers', topReferrers, 'referer_domain')}
          {list('Countries', countries, 'country')}
          {list('Browsers', browsers, 'browser')}
          {list('Operating systems', operatingSystems, 'os')}
          {list('Devices', devices, 'device')}
        </div>

        <h2 className="mb-3 mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">Campaigns</h2>
        <p className="mb-4 text-xs text-gray-500">Session counts, unique visitors in parentheses. First-touch attribution.</p>
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {campaignList('Top sources', utmSources)}
          {campaignList('Top mediums', utmMediums)}
          {campaignList('Top campaigns', utmCampaigns)}
          {campaignList('Source / medium', utmSourceMediums)}
          {campaignList('Terms', utmTerms)}
          {campaignList('Content', utmContents)}
        </div>
      </div>
    </AppLayout>
  );
}
