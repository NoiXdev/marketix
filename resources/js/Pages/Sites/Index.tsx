import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Site } from '@/types';
import { confirmDelete } from '@/lib/confirm';
import { Link, router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

export default function SitesIndex({ sites }: { sites: Site[] }) {
  const { project, flash } = usePage<PageProps>().props;

  async function destroy(site: Site) {
    if (!(await confirmDelete({ title: site.name }))) return;
    router.delete(route('app.project.sites.destroy', { project: project!.id, site: site.id }));
  }

  return (
    <AppLayout title="Analytics">
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Analytics — Sites</h1>
        <Link
          href={route('app.project.sites.create', { project: project!.id })}
          className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
        >
          <Plus className="h-4 w-4" /> Add site
        </Link>
      </div>

      {flash?.success && (
        <div className="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-300">
          {flash.success}
        </div>
      )}

      <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead className="bg-gray-50 dark:bg-gray-800/50">
            <tr>
              <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Name</th>
              <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Domain</th>
              <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Mode</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
            {sites.map((site) => (
              <tr key={site.id} className="bg-white dark:bg-gray-900">
                <td className="px-4 py-3">
                  <Link
                    href={route('app.project.analytics.show', { project: project!.id, site: site.id })}
                    className="font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                  >
                    {site.name}
                  </Link>
                </td>
                <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{site.domain}</td>
                <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{site.tracking_mode}</td>
                <td className="px-4 py-3 text-right">
                  <Link
                    href={route('app.project.sites.edit', { project: project!.id, site: site.id })}
                    className="mr-3 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-200"
                  >
                    Edit
                  </Link>
                  <button onClick={() => destroy(site)} className="text-sm text-red-600 hover:text-red-500">
                    <Trash2 className="inline h-4 w-4" />
                  </button>
                </td>
              </tr>
            ))}
            {sites.length === 0 && (
              <tr>
                <td colSpan={4} className="px-4 py-8 text-center text-sm text-gray-500">
                  No sites yet. Add your first site to start tracking.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </AppLayout>
  );
}
