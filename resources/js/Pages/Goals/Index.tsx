import AppLayout from '@/Layouts/AppLayout';
import { Goal, PageProps } from '@/types';
import { confirmDelete } from '@/lib/confirm';
import { Link, router, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

export default function GoalsIndex({ site, goals }: { site: { id: string; name: string }; goals: Goal[] }) {
  const { project, flash } = usePage<PageProps>().props;

  async function destroy(goal: Goal) {
    if (!(await confirmDelete({ title: goal.name }))) return;
    router.delete(route('app.project.analytics.goals.destroy', { project: project!.id, site: site.id, goal: goal.id }));
  }

  return (
    <AppLayout title={`Goals — ${site.name}`}>
      <div className="px-8 py-8">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <Link href={route('app.project.analytics.show', { project: project!.id, site: site.id })} className="text-sm text-indigo-600 hover:underline">
              ← Analytics
            </Link>
            <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">Goals — {site.name}</h1>
          </div>
          <Link
            href={route('app.project.analytics.goals.create', { project: project!.id, site: site.id })}
            className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
          >
            <Plus className="h-4 w-4" /> Add goal
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
                <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                <th className="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Match</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
              {goals.map((goal) => (
                <tr key={goal.id} className="bg-white dark:bg-gray-900">
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{goal.name}</td>
                  <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{goal.type}</td>
                  <td className="px-4 py-3 font-mono text-sm text-gray-600 dark:text-gray-300">{goal.match_value}</td>
                  <td className="px-4 py-3 text-right">
                    <Link
                      href={route('app.project.analytics.goals.edit', { project: project!.id, site: site.id, goal: goal.id })}
                      className="mr-3 text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-200"
                    >
                      Edit
                    </Link>
                    <button onClick={() => destroy(goal)} className="text-sm text-red-600 hover:text-red-500">
                      <Trash2 className="inline h-4 w-4" />
                    </button>
                  </td>
                </tr>
              ))}
              {goals.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-8 text-center text-sm text-gray-500">
                    No goals yet. Add one to track conversions.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </AppLayout>
  );
}
