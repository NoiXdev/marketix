import AppLayout from '@/Layouts/AppLayout';
import { formatCompactNumber } from '@/lib/format';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { BarChart3, Globe, LinkIcon, MousePointerClick } from 'lucide-react';

interface DayClicks {
  date: string;
  clicks: number;
  unique: number;
}

interface DashboardProps {
  urlsCount: number;
  domainsCount: number;
  totalClicks: number;
  totalUniqueClicks: number;
  days: number;
  clicksByDay: DayClicks[];
}

const RANGES = [7, 30, 90, 180, 365];

function ClicksChart({ data }: { data: DayClicks[] }) {
  const max = Math.max(...data.map((d) => Math.max(d.clicks, d.unique)), 1);

  return (
    <div className="flex h-40 items-end gap-px">
      {data.map((d) => (
        <div key={d.date} className="group relative flex flex-1 items-end justify-center gap-px">
          <div
            className="w-full rounded-t bg-indigo-500 transition-all group-hover:bg-indigo-600 dark:bg-indigo-600 dark:group-hover:bg-indigo-500"
            style={{ height: `${Math.max((d.clicks / max) * 100, d.clicks > 0 ? 4 : 1)}%` }}
          />
          <div
            className="w-full rounded-t bg-violet-500 transition-all group-hover:bg-violet-600 dark:bg-violet-600 dark:group-hover:bg-violet-500"
            style={{ height: `${Math.max((d.unique / max) * 100, d.unique > 0 ? 4 : 1)}%` }}
          />
          {/* Tooltip */}
          <div className="pointer-events-none absolute bottom-full left-1/2 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-xs text-white group-hover:block dark:bg-slate-700">
            <p className="text-slate-300">{d.date}</p>
            <p className="font-semibold text-indigo-300">{d.clicks.toLocaleString()} total</p>
            <p className="font-semibold text-violet-300">{d.unique.toLocaleString()} unique</p>
          </div>
        </div>
      ))}
    </div>
  );
}

export default function Dashboard({ urlsCount, domainsCount, totalClicks, totalUniqueClicks, days, clicksByDay }: DashboardProps) {
  const currentProject = usePage<PageProps>().props.project;

  function setDays(d: number) {
    router.get(route('app.project.dashboard', { project: currentProject!.id }), { days: d }, { preserveState: true });
  }

  const stats = [
    {
      label: 'Links',
      value: urlsCount,
      icon: LinkIcon,
      color: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20 dark:text-indigo-400',
    },
    {
      label: 'Domains',
      value: domainsCount,
      icon: Globe,
      color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 dark:text-emerald-400',
    },
    {
      label: 'Total clicks',
      value: totalClicks,
      icon: BarChart3,
      color: 'text-amber-600 bg-amber-50 dark:bg-amber-900/20 dark:text-amber-400',
      compact: true,
    },
    {
      label: 'Unique clicks',
      value: totalUniqueClicks,
      icon: MousePointerClick,
      color: 'text-violet-600 bg-violet-50 dark:bg-violet-900/20 dark:text-violet-400',
      compact: true,
    },
  ];

  return (
    <AppLayout title="Dashboard">
      <div className="px-8 py-8">
        <div className="mb-8">
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">{currentProject?.name}</h1>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Overview of your project</p>
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {stats.map(({ label, value, icon: Icon, color, compact }) => (
            <div key={label} className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{label}</p>
                <span className={`rounded-lg p-2 ${color}`}>
                  <Icon className="h-4 w-4" />
                </span>
              </div>
              <p
                className="mt-3 text-3xl font-bold text-slate-900 dark:text-white"
                title={compact ? value.toLocaleString() : undefined}
              >
                {compact ? formatCompactNumber(value) : value.toLocaleString()}
              </p>
            </div>
          ))}
        </div>

        {/* Clicks over time */}
        <div className="mt-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
          <div className="mb-4 flex items-center justify-between gap-4">
            <div className="flex items-center gap-4">
              <h2 className="text-sm font-semibold text-slate-700 dark:text-slate-300">
                Clicks over time <span className="font-normal text-slate-400">— last {days} days</span>
              </h2>
              <div className="flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                <span className="flex items-center gap-1.5">
                  <span className="h-2.5 w-2.5 rounded-sm bg-indigo-500 dark:bg-indigo-600" /> Total
                </span>
                <span className="flex items-center gap-1.5">
                  <span className="h-2.5 w-2.5 rounded-sm bg-violet-500 dark:bg-violet-600" /> Unique
                </span>
              </div>
            </div>

            {/* Range selector */}
            <div className="flex rounded-lg border border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
              {RANGES.map((d) => (
                <button
                  key={d}
                  onClick={() => setDays(d)}
                  className={`px-3 py-1.5 transition-colors first:rounded-l-lg last:rounded-r-lg ${
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

          <ClicksChart data={clicksByDay} />
          <div className="mt-2 flex justify-between text-xs text-slate-400">
            <span>{clicksByDay[0]?.date}</span>
            <span>{clicksByDay[clicksByDay.length - 1]?.date}</span>
          </div>
        </div>
      </div>
    </AppLayout>
  );
}
