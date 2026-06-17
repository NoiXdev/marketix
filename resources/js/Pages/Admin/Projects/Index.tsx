import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { ExternalLink, Lock, Pencil, Plus, Trash2 } from 'lucide-react';

interface AdminProjectRow {
  id: number;
  name: string;
  locked: boolean;
  users_count: number;
}

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

export default function AdminProjectsIndex({ projects, search }: { projects: Paginated<AdminProjectRow>; search: string }) {
  const { flash } = usePage<PageProps>().props;

  function destroy(project: AdminProjectRow) {
    if (!confirm(`Delete "${project.name}"?`)) return;
    router.delete(route('app.admin.projects.destroy', { project: project.id }));
  }

  function onSearch(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const value = new FormData(e.currentTarget).get('search') as string;
    router.get(route('app.admin.projects.index'), { search: value }, { preserveState: true, replace: true });
  }

  return (
    <AdminLayout title="Projects">
      <div className="px-8 py-8">
        <div className="mb-6 flex items-center justify-between">
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Projects</h1>
          <Link href={route('app.admin.projects.create')} className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
            <Plus className="h-4 w-4" />
            Add project
          </Link>
        </div>

        {flash?.success && <div className="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{flash.success}</div>}

        <form onSubmit={onSearch} className="mb-4">
          <input name="search" defaultValue={search} placeholder="Search projects…" className="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
        </form>

        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-800">
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Name</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Members</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {projects.data.map((project) => (
                <tr key={project.id} className="group">
                  <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                    <span className="flex items-center gap-2">
                      {project.name}
                      {project.locked && <Lock className="h-3.5 w-3.5 text-amber-500" />}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{project.users_count}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                      <Link href={route('app.project.dashboard', { project: project.id })} title="Open project" className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800">
                        <ExternalLink className="h-4 w-4" />
                      </Link>
                      <Link href={route('app.admin.projects.edit', { project: project.id })} title="Edit project" className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800">
                        <Pencil className="h-4 w-4" />
                      </Link>
                      <button onClick={() => destroy(project)} className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mt-4 flex flex-wrap gap-1">
          {projects.links.map((link, i) => (
            <Link key={i} href={link.url ?? '#'} className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
          ))}
        </div>
      </div>
    </AdminLayout>
  );
}
