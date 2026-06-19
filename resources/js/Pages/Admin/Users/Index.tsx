import AdminLayout from '@/Layouts/AdminLayout';
import { confirmDelete } from '@/lib/confirm';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Shield, Trash2 } from 'lucide-react';

interface AdminUserRow {
  id: string;
  name: string;
  email: string;
  super_admin: boolean;
  projects_count: number;
}

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

export default function AdminUsersIndex({ users, search }: { users: Paginated<AdminUserRow>; search: string }) {
  const { flash } = usePage<PageProps>().props;

  async function destroy(user: AdminUserRow) {
    if (!(await confirmDelete({ title: 'Delete user?', text: `Delete "${user.name}"? This cannot be undone.` }))) return;
    router.delete(route('app.admin.users.destroy', { user: user.id }));
  }

  function onSearch(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const value = new FormData(e.currentTarget).get('search') as string;
    router.get(route('app.admin.users.index'), { search: value }, { preserveState: true, replace: true });
  }

  return (
    <AdminLayout title="Users">
      <div className="px-8 py-8">
        <div className="mb-6 flex items-center justify-between">
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Users</h1>
          <Link
            href={route('app.admin.users.create')}
            className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
          >
            <Plus className="h-4 w-4" />
            Add user
          </Link>
        </div>

        {flash?.success && (
          <div className="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{flash.success}</div>
        )}
        {flash?.error && (
          <div className="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">{flash.error}</div>
        )}

        <form onSubmit={onSearch} className="mb-4">
          <input
            name="search"
            defaultValue={search}
            placeholder="Search name or email…"
            className="w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
          />
        </form>

        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-800">
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Name</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Email</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Projects</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {users.data.map((user) => (
                <tr
                  key={user.id}
                  onClick={rowLink(route('app.admin.users.edit', { user: user.id }))}
                  className={`group ${ROW_LINK_CLASS}`}
                >
                  <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                    <Link
                      href={route('app.admin.users.edit', { user: user.id })}
                      className="flex items-center gap-2 hover:text-indigo-600 dark:hover:text-indigo-400"
                    >
                      {user.name}
                      {user.super_admin && <Shield className="h-3.5 w-3.5 text-indigo-500" />}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{user.email}</td>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{user.projects_count}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                      <Link href={route('app.admin.users.edit', { user: user.id })} className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800">
                        <Pencil className="h-4 w-4" />
                      </Link>
                      <button onClick={() => destroy(user)} className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
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
          {users.links.map((link, i) => (
            <Link
              key={i}
              href={link.url ?? '#'}
              className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
              dangerouslySetInnerHTML={{ __html: link.label }}
            />
          ))}
        </div>
      </div>
    </AdminLayout>
  );
}
