import ActivityFeed from '@/Components/ActivityFeed';
import AdminLayout from '@/Layouts/AdminLayout';
import { ActivityEntry } from '@/types';
import { Link, router } from '@inertiajs/react';

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

interface Filters {
  log_name: string | null;
  project_id: string | null;
  causer: string | null;
  from: string | null;
  to: string | null;
}

export default function AdminActivityIndex({
  activities,
  filters,
  logNames,
  projects,
}: {
  activities: Paginated<ActivityEntry>;
  filters: Filters;
  logNames: string[];
  projects: { id: string; name: string }[];
}) {
  function apply(patch: Partial<Filters>) {
    const next = { ...filters, ...patch };
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v));
    router.get(route('app.admin.activity.index'), params, { preserveState: true, replace: true });
  }

  return (
    <AdminLayout title="Activity log">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Activity log</h1>

        <div className="mb-4 flex flex-wrap gap-2">
          <select value={filters.log_name ?? ''} onChange={(e) => apply({ log_name: e.target.value || null })} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All types</option>
            {logNames.map((n) => <option key={n} value={n}>{n}</option>)}
          </select>
          <select value={filters.project_id ?? ''} onChange={(e) => apply({ project_id: e.target.value || null })} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="">All projects</option>
            {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
          <input defaultValue={filters.causer ?? ''} onBlur={(e) => apply({ causer: e.target.value || null })} placeholder="Causer name/email" className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
          <input type="date" defaultValue={filters.from ?? ''} onChange={(e) => apply({ from: e.target.value || null })} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
          <input type="date" defaultValue={filters.to ?? ''} onChange={(e) => apply({ to: e.target.value || null })} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
        </div>

        <div className="rounded-xl border border-slate-200 bg-white px-5 dark:border-slate-800 dark:bg-slate-900">
          <ActivityFeed activities={activities.data} showProject />
        </div>

        <div className="mt-4 flex flex-wrap gap-1">
          {activities.links.map((link, i) => (
            <Link key={i} href={link.url ?? '#'} className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
          ))}
        </div>
      </div>
    </AdminLayout>
  );
}
