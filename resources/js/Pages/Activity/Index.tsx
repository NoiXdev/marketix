import ActivityFeed from '@/Components/ActivityFeed';
import AppLayout from '@/Layouts/AppLayout';
import { ActivityEntry, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

export default function ActivityIndex({
  activities,
  logName,
  logNames,
}: {
  activities: Paginated<ActivityEntry>;
  logName: string | null;
  logNames: string[];
}) {
  const project = usePage<PageProps>().props.project;

  function onFilter(value: string) {
    router.get(route('app.project.activity.index', { project: project!.id }), value ? { log_name: value } : {}, {
      preserveState: true,
      replace: true,
    });
  }

  return (
    <AppLayout title="Activity">
      <div className="px-8 py-8">
        <div className="mb-6 flex items-center justify-between">
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Activity</h1>
          <select
            value={logName ?? ''}
            onChange={(e) => onFilter(e.target.value)}
            className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
          >
            <option value="">All activity</option>
            {logNames.map((n) => (
              <option key={n} value={n}>
                {n}
              </option>
            ))}
          </select>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white px-5 dark:border-slate-800 dark:bg-slate-900">
          <ActivityFeed activities={activities.data} />
        </div>

        <div className="mt-4 flex flex-wrap gap-1">
          {activities.links.map((link, i) => (
            <Link
              key={i}
              href={link.url ?? '#'}
              className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
              dangerouslySetInnerHTML={{ __html: link.label }}
            />
          ))}
        </div>
      </div>
    </AppLayout>
  );
}
