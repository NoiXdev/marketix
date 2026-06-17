import AdminLayout from '@/Layouts/AdminLayout';
import { Link, useForm } from '@inertiajs/react';

export default function AdminUsersCreate() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    password: '',
    super_admin: false as boolean,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('app.admin.users.store'));
  }

  return (
    <AdminLayout title="Add user">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Add user</h1>
        <form onSubmit={submit} className="max-w-md space-y-4">
          <Field label="Name" error={errors.name}>
            <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
          </Field>
          <Field label="Email" error={errors.email}>
            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
          </Field>
          <Field label="Password" error={errors.password}>
            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
          </Field>
          <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" checked={data.super_admin} onChange={(e) => setData('super_admin', e.target.checked)} />
            Super admin
          </label>
          <div className="flex gap-2">
            <button disabled={processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
              Create
            </button>
            <Link href={route('app.admin.users.index')} className="rounded-md px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
              Cancel
            </Link>
          </div>
        </form>
      </div>
    </AdminLayout>
  );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
  return (
    <div>
      <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">{label}</label>
      {children}
      {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
    </div>
  );
}
