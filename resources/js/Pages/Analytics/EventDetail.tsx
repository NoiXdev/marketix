import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';

type PropValue = { value: string; count: number };
type NumericSummary = { count: number; sum: number; avg: number };
type PropKey = { key: string; values: PropValue[]; numeric: NumericSummary | null };

export default function EventDetail({
  site,
  event,
  days,
  total,
  keys,
}: {
  site: { id: string; name: string; domain: string };
  event: string;
  days: number;
  total: number;
  keys: PropKey[];
}) {
  const { project } = usePage<PageProps>().props;

  function setDays(d: number) {
    router.get(
      route('app.project.analytics.events.show', { project: project!.id, site: site.id, name: event }),
      { days: d },
      { preserveState: true },
    );
  }

  return (
    <AppLayout title={`Event — ${event}`}>
      <div className="px-8 py-8">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link
              href={route('app.project.analytics.show', { project: project!.id, site: site.id })}
              className="text-sm text-indigo-600 hover:underline"
            >
              ← Analytics
            </Link>
            <h1 className="font-mono text-xl font-semibold text-gray-900 dark:text-gray-100">{event}</h1>
            <p className="text-sm text-gray-500">
              {total} events · {site.name}
            </p>
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

        {keys.length === 0 ? (
          <p className="text-sm text-gray-500">No properties recorded for this event.</p>
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {keys.map((k) => (
              <div key={k.key} className="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <h3 className="mb-2 font-mono text-sm font-semibold text-gray-700 dark:text-gray-300">{k.key}</h3>
                {k.numeric && (
                  <p className="mb-3 border-b border-gray-100 pb-2 text-xs text-gray-500 dark:border-gray-800">
                    Sum <span className="font-medium text-gray-700 dark:text-gray-300">{k.numeric.sum}</span> · Avg{' '}
                    <span className="font-medium text-gray-700 dark:text-gray-300">{k.numeric.avg}</span> · {k.numeric.count} numeric
                  </p>
                )}
                <ul className="space-y-1">
                  {k.values.map((v, i) => (
                    <li key={i} className="flex justify-between text-sm text-gray-600 dark:text-gray-300">
                      <span className="truncate">{v.value}</span>
                      <span className="font-medium">{v.count}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
