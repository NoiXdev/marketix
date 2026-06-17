import AdminLayout from '@/Layouts/AdminLayout';
import { Link, useForm } from '@inertiajs/react';

export default function AdminProjectsCreate() {
  const { data, setData, post, processing, errors } = useForm({ name: '', locked: false as boolean });
  const inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('app.admin.projects.store'));
  }

  return (
    <AdminLayout title="Add project">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Add project</h1>
        <form onSubmit={submit} className="max-w-md space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
            <input value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputClass} />
            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
          </div>
          <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" checked={data.locked} onChange={(e) => setData('locked', e.target.checked)} />
            Locked
          </label>
          <div className="flex gap-2">
            <button disabled={processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Create</button>
            <Link href={route('app.admin.projects.index')} className="rounded-md px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">Cancel</Link>
          </div>
        </form>
      </div>
    </AdminLayout>
  );
}
