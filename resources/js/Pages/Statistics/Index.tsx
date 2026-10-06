import ReportDownloadButton from '@/Components/ReportDownloadButton';
import WorldMap, { CountryDatum } from '@/Components/WorldMap';
import AppLayout from '@/Layouts/AppLayout';
import ChartDateAxis from '@/Pages/Dashboard/ChartDateAxis';
import KpiTile from '@/Pages/Dashboard/KpiTile';
import RankedList from '@/Pages/Dashboard/RankedList';
import { useTranslation } from '@/lib/i18n';
import { ROW_LINK_CLASS, rowLink } from '@/lib/rowLink';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, MousePointerClick } from 'lucide-react';

interface DayClicks {
  date: string;
  clicks: number;
}
interface TopLink {
  id: string;
  slug: string;
  domain_name: string;
  clicks: number;
}
interface BreakdownRow {
  [key: string]: string | number;
  count: number;
}

interface Props {
  days: number;
  totalClicks: number;
  uniqueClicks: number;
  clicksByDay: DayClicks[];
  topLinks: TopLink[];
  topCountries: (BreakdownRow & { country: string })[];
  clicksByCountry: CountryDatum[];
  topBrowsers: (BreakdownRow & { browser: string })[];
  topOs: (BreakdownRow & { os: string })[];
  topReferrers: (BreakdownRow & { domain: string })[];
}

const RANGES = [7, 30, 90];

function ClicksBars({ data, clicksLabel }: { data: DayClicks[]; clicksLabel: string }) {
  const max = Math.max(...data.map((d) => d.clicks), 1);
  return (
    <div>
      <div className="flex h-32 items-end gap-px">
        {data.map((d) => (
          <div key={d.date} className="group relative flex h-full flex-1 flex-col items-center justify-end">
            <div
              className="bg-accent w-full rounded-t transition-all"
              style={{ height: `${Math.max((d.clicks / max) * 100, d.clicks > 0 ? 4 : 1)}%` }}
              title={`${d.date}: ${d.clicks} ${clicksLabel}`}
            />
            <div className="bg-foreground text-canvas pointer-events-none absolute bottom-full z-10 mb-1 hidden rounded-md px-2 py-1 text-xs whitespace-nowrap shadow-[var(--shadow)] group-hover:block">
              <p className="font-semibold">
                {d.clicks.toLocaleString()} {clicksLabel}
              </p>
              <p className="text-subtle">{d.date}</p>
            </div>
          </div>
        ))}
      </div>
      <ChartDateAxis dates={data.map((d) => d.date)} gapClass="gap-px" />
    </div>
  );
}

function Breakdown({ title, rows, labelKey, emptyLabel }: { title: string; rows: BreakdownRow[]; labelKey: string; emptyLabel: string }) {
  return (
    <section className="border-line bg-surface rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
      <div className="border-line border-b px-4 py-3">
        <h2 className="text-foreground text-sm font-semibold">{title}</h2>
      </div>
      <RankedList emptyLabel={emptyLabel} rows={rows.map((r, i) => ({ key: `${String(r[labelKey] ?? '')}-${i}`, label: String(r[labelKey] || '—'), value: r.count }))} />
    </section>
  );
}

export default function StatisticsIndex({ days, totalClicks, uniqueClicks, clicksByDay, topLinks, topCountries, topBrowsers, topOs, topReferrers, clicksByCountry }: Props) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  function setDays(d: number) {
    router.get(route('app.project.statistics', { project: project!.id }), { days: d }, { preserveState: true });
  }

  return (
    <AppLayout title={t('statistics.title')}>
      <div className="px-8 py-8">
        {/* Header */}
        <div className="mb-6 flex items-center justify-between gap-4">
          <div>
            <h1 className="text-foreground text-2xl font-bold tracking-tight">{t('statistics.title')}</h1>
            <p className="text-muted mt-1 text-sm">{t('statistics.subtitle')}</p>
          </div>
          <div className="flex items-center gap-3">
            <div className="border-line inline-flex overflow-hidden rounded-lg border">
              {RANGES.map((d) => (
                <button
                  key={d}
                  onClick={() => setDays(d)}
                  className={`border-line border-r px-3 py-1.5 text-sm font-semibold last:border-r-0 focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${
                    days === d ? 'bg-accent-soft text-accent-soft-foreground' : 'bg-surface text-muted hover:bg-elevated'
                  }`}
                >
                  {d}
                  {t('common.dashboard.range_days')}
                </button>
              ))}
            </div>
            <ReportDownloadButton projectId={project!.id} />
          </div>
        </div>

        {/* Summary cards */}
        <div className="mb-6 grid grid-cols-2 gap-3.5">
          <KpiTile compact={false} label={t('statistics.total_clicks')} value={totalClicks} icon={BarChart3} />
          <KpiTile compact={false} label={t('statistics.unique_clicks')} value={uniqueClicks} icon={MousePointerClick} />
        </div>

        {/* Clicks over time */}
        <section className="border-line bg-surface mb-6 rounded-[var(--radius)] border p-6 shadow-[var(--shadow-sm)]">
          <h2 className="text-foreground mb-4 text-sm font-semibold">
            {t('statistics.clicks_over_time')} <span className="text-muted font-normal">{t('statistics.last_days', { days: String(days) })}</span>
          </h2>
          <ClicksBars data={clicksByDay} clicksLabel={t('statistics.clicks')} />
        </section>

        {/* Top links */}
        <section className="border-line bg-surface mb-6 rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
          <div className="border-line border-b px-4 py-3">
            <h2 className="text-foreground text-sm font-semibold">{t('statistics.top_links')}</h2>
          </div>
          {topLinks.length === 0 ? (
            <p className="text-subtle px-4 py-6 text-center text-sm">{t('statistics.no_data')}</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-line border-b">
                  <th className="text-muted px-4 py-2.5 text-left text-xs font-semibold tracking-wider uppercase">{t('statistics.columns.link')}</th>
                  <th className="text-muted px-4 py-2.5 text-right text-xs font-semibold tracking-wider uppercase">{t('statistics.columns.clicks')}</th>
                </tr>
              </thead>
              <tbody className="divide-line divide-y">
                {topLinks.map((link) => (
                  <tr key={link.id} onClick={rowLink(route('app.project.links.show', { project: project!.id, url: link.id }))} className={`group ${ROW_LINK_CLASS}`}>
                    <td className="text-foreground px-4 py-2.5 font-medium">
                      <Link href={route('app.project.links.show', { project: project!.id, url: link.id })} className="hover:text-accent-soft-foreground">
                        <span className="text-subtle">{link.domain_name}/</span>
                        {link.slug}
                      </Link>
                    </td>
                    <td className="text-muted px-4 py-2.5 text-right tabular-nums">{link.clicks.toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        {/* Clicks by country map */}
        <div className="mb-6">
          <WorldMap data={clicksByCountry} />
        </div>

        {/* Breakdown grids */}
        <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2 xl:grid-cols-4">
          <Breakdown title={t('statistics.breakdown.countries')} rows={topCountries} labelKey="country" emptyLabel={t('statistics.no_data')} />
          <Breakdown title={t('statistics.breakdown.browsers')} rows={topBrowsers} labelKey="browser" emptyLabel={t('statistics.no_data')} />
          <Breakdown title={t('statistics.breakdown.os')} rows={topOs} labelKey="os" emptyLabel={t('statistics.no_data')} />
          <Breakdown title={t('statistics.breakdown.referrers')} rows={topReferrers} labelKey="domain" emptyLabel={t('statistics.no_data')} />
        </div>
      </div>
    </AppLayout>
  );
}
