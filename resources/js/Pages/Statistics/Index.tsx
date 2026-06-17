import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { BarChart3, Globe, Monitor, MousePointerClick } from 'lucide-react';

interface DayClicks { date: string; clicks: number }
interface TopLink { id: number; slug: string; domain_name: string; clicks: number }
interface BreakdownRow { [key: string]: string | number; count: number }

interface Props {
  days: number;
  totalClicks: number;
  uniqueClicks: number;
  clicksByDay: DayClicks[];
  topLinks: TopLink[];
  topCountries: (BreakdownRow & { country: string })[];
  topBrowsers: (BreakdownRow & { browser: string })[];
  topOs: (BreakdownRow & { os: string })[];
  topReferrers: (BreakdownRow & { domain: string })[];
}

function BarChart({ data }: { data: DayClicks[] }) {
  const max = Math.max(...data.map((d) => d.clicks), 1);

  return (
    <div className="flex h-32 items-end gap-px">
      {data.map((d) => (
        <div key={d.date} className="group relative flex flex-1 flex-col items-center">
          <div
            className="w-full rounded-t bg-indigo-500 transition-all group-hover:bg-indigo-600 dark:bg-indigo-600 dark:group-hover:bg-indigo-500"
            style={{ height: `${Math.max((d.clicks / max) * 100, d.clicks > 0 ? 4 : 1)}%` }}
          />
          {/* Tooltip */}
          <div className="pointer-events-none absolute bottom-full mb-1 hidden rounded bg-slate-900 px-2 py-1 text-xs text-white group-hover:block dark:bg-slate-700">
            <p className="font-semibold">{d.clicks} clicks</p>
            <p className="text-slate-400">{d.date}</p>
          </div>
        </div>
      ))}
    </div>
  );
}

function BreakdownTable({ title, rows, labelKey }: { title: string; rows: BreakdownRow[]; labelKey: string }) {
  const total = rows.reduce((s, r) => s + (r.count as number), 0) || 1;

  return (
    <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
        <h2 className="text-sm font-semibold text-slate-700 dark:text-slate-300">{title}</h2>
      </div>
      {rows.length === 0 ? (
        <p className="px-4 py-6 text-center text-sm text-slate-400">No data yet</p>
      ) : (
        <ul className="divide-y divide-slate-100 dark:divide-slate-800">
          {rows.map((row, i) => {
            const label = String(row[labelKey] || '—');
            const pct = Math.round(((row.count as number) / total) * 100);
            return (
              <li key={i} className="px-4 py-2.5">
                <div className="mb-1 flex items-center justify-between text-sm">
                  <span className="truncate font-medium text-slate-700 dark:text-slate-300">{label}</span>
                  <span className="ml-2 shrink-0 tabular-nums text-slate-500 dark:text-slate-400">
                    {(row.count as number).toLocaleString()} <span className="text-xs text-slate-400">({pct}%)</span>
                  </span>
                </div>
                <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                  <div
                    className="h-full rounded-full bg-indigo-500 dark:bg-indigo-600"
                    style={{ width: `${pct}%` }}
                  />
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}

export default function StatisticsIndex({
  days, totalClicks, uniqueClicks, clicksByDay,
  topLinks, topCountries, topBrowsers, topOs, topReferrers,
}: Props) {
  const { project } = usePage<PageProps>().props;

  function setDays(d: number) {
    router.get(route('app.project.statistics', { project: project!.id }), { days: d }, { preserveState: true });
  }

  const summaryCards = [
    { label: 'Total clicks',  value: totalClicks.toLocaleString(),  icon: BarChart3,         color: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20 dark:text-indigo-400' },
    { label: 'Unique clicks', value: uniqueClicks.toLocaleString(), icon: MousePointerClick,  color: 'text-violet-600 bg-violet-50 dark:bg-violet-900/20 dark:text-violet-400' },
  ];

  return (
    <AppLayout title="Statistics">
      <div className="px-8 py-8">

        {/* Header */}
        <div className="mb-6 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Statistics</h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Click analytics for this project</p>
          </div>

          {/* Range selector */}
          <div className="flex rounded-lg border border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
            {[7, 30, 90].map((d) => (
              <button
                key={d}
                onClick={() => setDays(d)}
                className={`px-3 py-1.5 first:rounded-l-lg last:rounded-r-lg transition-colors ${
                  days === d
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800'
                }`}
              >
                {d}d
              </button>
            ))}
          </div>
        </div>

        {/* Summary cards */}
        <div className="mb-6 grid grid-cols-2 gap-4">
          {summaryCards.map(({ label, value, icon: Icon, color }) => (
            <div key={label} className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{label}</p>
                <span className={`rounded-lg p-2 ${color}`}><Icon className="h-4 w-4" /></span>
              </div>
              <p className="mt-3 text-3xl font-bold text-slate-900 dark:text-white">{value}</p>
            </div>
          ))}
        </div>

        {/* Clicks over time */}
        <div className="mb-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
          <h2 className="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
            Clicks over time <span className="font-normal text-slate-400">— last {days} days</span>
          </h2>
          <BarChart data={clicksByDay} />
          <div className="mt-2 flex justify-between text-xs text-slate-400">
            <span>{clicksByDay[0]?.date}</span>
            <span>{clicksByDay[clicksByDay.length - 1]?.date}</span>
          </div>
        </div>

        {/* Top links */}
        <div className="mb-6 rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
          <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <h2 className="text-sm font-semibold text-slate-700 dark:text-slate-300">Top links</h2>
          </div>
          {topLinks.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-slate-400">No data yet</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-100 dark:border-slate-800">
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Link</th>
                  <th className="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Clicks</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {topLinks.map((link) => (
                  <tr key={link.id}>
                    <td className="px-4 py-2.5 font-medium text-slate-700 dark:text-slate-300">
                      <span className="text-slate-400">{link.domain_name}/</span>{link.slug}
                    </td>
                    <td className="px-4 py-2.5 text-right tabular-nums text-slate-600 dark:text-slate-400">
                      {link.clicks.toLocaleString()}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* Breakdown grids */}
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          <BreakdownTable title="Countries" rows={topCountries} labelKey="country" />
          <BreakdownTable title="Browsers"  rows={topBrowsers}  labelKey="browser" />
          <BreakdownTable title="OS"        rows={topOs}        labelKey="os" />
          <BreakdownTable title="Referrers" rows={topReferrers} labelKey="domain" />
        </div>

      </div>
    </AppLayout>
  );
}
