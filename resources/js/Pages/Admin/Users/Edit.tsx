import AdminLayout from '@/Layouts/AdminLayout';
import { Link, useForm } from '@inertiajs/react';

interface EditUser {
  id: number;
  name: string;
  email: string;
  super_admin: boolean;
}

export default function AdminUsersEdit({ user }: { user: EditUser }) {
  const { data, setData, put, processing, errors } = useForm({
    name: user.name,
    email: user.email,
    password: '',
    super_admin: user.super_admin,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.admin.users.update', { user: user.id }));
  }

  const inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

  return (
    <AdminLayout title="Edit user">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Edit user</h1>
        <form onSubmit={submit} className="max-w-md space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
            <input value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputClass} />
            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
            <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className={inputClass} />
            {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">New password (leave blank to keep)</label>
            <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className={inputClass} />
            {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
          </div>
          <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" checked={data.super_admin} onChange={(e) => setData('super_admin', e.target.checked)} />
            Super admin
          </label>
          <div className="flex gap-2">
            <button disabled={processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Save</button>
            <Link href={route('app.admin.users.index')} className="rounded-md px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">Cancel</Link>
          </div>
        </form>
      </div>
    </AdminLayout>
  );
}
