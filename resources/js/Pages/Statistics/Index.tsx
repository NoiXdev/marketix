import ReportDownloadButton from '@/Components/ReportDownloadButton';
import WorldMap, { CountryDatum } from '@/Components/WorldMap';
import AppLayout from '@/Layouts/AppLayout';
import KpiTile from '@/Pages/Dashboard/KpiTile';
import RankedList from '@/Pages/Dashboard/RankedList';
import { useTranslation } from '@/lib/i18n';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, MousePointerClick } from 'lucide-react';

interface DayClicks { date: string; clicks: number }
interface TopLink { id: string; slug: string; domain_name: string; clicks: number }
interface BreakdownRow { [key: string]: string | number; count: number }

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
    <div className="flex h-32 items-end gap-px">
      {data.map((d) => (
        <div key={d.date} className="group relative flex flex-1 flex-col items-center">
          <div
            className="w-full rounded-t bg-accent transition-all"
            style={{ height: `${Math.max((d.clicks / max) * 100, d.clicks > 0 ? 4 : 1)}%` }}
          />
          <div className="pointer-events-none absolute bottom-full z-10 mb-1 hidden whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs text-canvas shadow-[var(--shadow)] group-hover:block">
            <p className="font-semibold">{d.clicks.toLocaleString()} {clicksLabel}</p>
            <p className="text-subtle">{d.date}</p>
          </div>
        </div>
      ))}
    </div>
  );
}

function Breakdown({ title, rows, labelKey, emptyLabel }: { title: string; rows: BreakdownRow[]; labelKey: string; emptyLabel: string }) {
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

export default function StatisticsIndex({
  days, totalClicks, uniqueClicks, clicksByDay,
  topLinks, topCountries, topBrowsers, topOs, topReferrers,
  clicksByCountry,
}: Props) {
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
            <h1 className="text-2xl font-bold tracking-tight text-foreground">{t('statistics.title')}</h1>
            <p className="mt-1 text-sm text-muted">{t('statistics.subtitle')}</p>
          </div>
          <div className="flex items-center gap-3">
            <div className="inline-flex overflow-hidden rounded-lg border border-line">
              {RANGES.map((d) => (
                <button
                  key={d}
                  onClick={() => setDays(d)}
                  className={`border-r border-line px-3 py-1.5 text-sm font-semibold last:border-r-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
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
        <section className="mb-6 rounded-[var(--radius)] border border-line bg-surface p-6 shadow-[var(--shadow-sm)]">
          <h2 className="mb-4 text-sm font-semibold text-foreground">
            {t('statistics.clicks_over_time')} <span className="font-normal text-muted">{t('statistics.last_days', { days: String(days) })}</span>
          </h2>
          <ClicksBars data={clicksByDay} clicksLabel={t('statistics.clicks')} />
          <div className="mt-2 flex justify-between text-xs text-subtle">
            <span>{clicksByDay[0]?.date}</span>
            <span>{clicksByDay[clicksByDay.length - 1]?.date}</span>
          </div>
        </section>

        {/* Top links */}
        <section className="mb-6 rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
          <div className="border-b border-line px-4 py-3">
            <h2 className="text-sm font-semibold text-foreground">{t('statistics.top_links')}</h2>
          </div>
          {topLinks.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-subtle">{t('statistics.no_data')}</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-line">
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-muted">{t('statistics.columns.link')}</th>
                  <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-muted">{t('statistics.columns.clicks')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {topLinks.map((link) => (
                  <tr
                    key={link.id}
                    onClick={rowLink(route('app.project.links.show', { project: project!.id, url: link.id }))}
                    className={`group ${ROW_LINK_CLASS}`}
                  >
                    <td className="px-4 py-2.5 font-medium text-foreground">
                      <Link
                        href={route('app.project.links.show', { project: project!.id, url: link.id })}
                        className="hover:text-accent-soft-foreground"
                      >
                        <span className="text-subtle">{link.domain_name}/</span>{link.slug}
                      </Link>
                    </td>
                    <td className="px-4 py-2.5 text-right tabular-nums text-muted">{link.clicks.toLocaleString()}</td>
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
