import AppLayout from '@/Layouts/AppLayout';
import { Goal, PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { value: string; label: string };

export default function GoalsEdit({ site, goal, goalTypes }: { site: { id: string }; goal: Goal; goalTypes: Option[] }) {
  const { project } = usePage<PageProps>().props;
  const { data, setData, put, processing, errors } = useForm({ name: goal.name, type: goal.type, match_value: goal.match_value });

  function submit(e: FormEvent) {
    e.preventDefault();
    put(route('app.project.analytics.goals.update', { project: project!.id, site: site.id, goal: goal.id }));
  }

  const inputClass =
    'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100';

  return (
    <AppLayout title="Edit goal">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-xl font-semibold text-gray-900 dark:text-gray-100">Edit goal</h1>
        <form onSubmit={submit} className="max-w-lg space-y-4">
          <div>
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
            <input className={inputClass} value={data.name} onChange={(e) => setData('name', e.target.value)} />
            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
          </div>
          <div>
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
            <select className={inputClass} value={data.type} onChange={(e) => setData('type', e.target.value)}>
              {goalTypes.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Match value</label>
            <input className={inputClass} value={data.match_value} onChange={(e) => setData('match_value', e.target.value)} />
            <p className="mt-1 text-xs text-gray-500">
              {data.type === 'event'
                ? "Event name, as in marketix('event', 'name')."
                : 'Path like /danke, or /blog/* to match everything under /blog.'}
            </p>
            {errors.match_value && <p className="mt-1 text-sm text-red-600">{errors.match_value}</p>}
          </div>
          <button disabled={processing} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
            Save changes
          </button>
        </form>
      </div>
    </AppLayout>
  );
}
