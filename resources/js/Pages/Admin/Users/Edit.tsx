import AdminLayout from '@/Layouts/AdminLayout';
import { confirmDelete } from '@/lib/confirm';
import { ProjectRole } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

interface EditUser {
  id: string;
  name: string;
  email: string;
  super_admin: boolean;
  force_password_change: boolean;
}

interface Membership {
  id: string;
  name: string;
  role: ProjectRole;
}

interface AvailableProject {
  id: string;
  name: string;
}

export default function AdminUsersEdit({
  user,
  memberships,
  availableProjects,
}: {
  user: EditUser;
  memberships: Membership[];
  availableProjects: AvailableProject[];
}) {
  const account = useForm({
    name: user.name,
    email: user.email,
    password: '',
    super_admin: user.super_admin,
    force_password_change: user.force_password_change,
  });
  const attach = useForm({ project_id: '', role: 'member' });

  const inputClass =
    'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

  function saveAccount(e: React.FormEvent) {
    e.preventDefault();
    account.put(route('app.admin.users.update', { user: user.id }));
  }

  function sendPasswordReset() {
    router.post(route('app.admin.users.send-password-reset', { user: user.id }));
  }

  function attachProject(e: React.FormEvent) {
    e.preventDefault();
    attach.post(route('app.admin.users.projects.store', { user: user.id }), {
      onSuccess: () => attach.reset(),
    });
  }

  function changeRole(membership: Membership, role: string) {
    router.patch(route('app.admin.users.projects.update', { user: user.id, project: membership.id }), { role });
  }

  async function removeMembership(membership: Membership) {
    if (!(await confirmDelete({ title: 'Remove from project?', text: `Remove ${user.name} from ${membership.name}?`, confirmText: 'Remove' }))) return;
    router.delete(route('app.admin.users.projects.destroy', { user: user.id, project: membership.id }));
  }

  const cardClass = 'mb-8 max-w-2xl rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900';

  return (
    <AdminLayout title="Edit user">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Edit user</h1>

        {/* Account */}
        <form onSubmit={saveAccount} className={cardClass}>
          <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Account</h2>
          <div className="space-y-4">
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
              <input value={account.data.name} onChange={(e) => account.setData('name', e.target.value)} className={inputClass} />
              {account.errors.name && <p className="mt-1 text-xs text-red-600">{account.errors.name}</p>}
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
              <input type="email" value={account.data.email} onChange={(e) => account.setData('email', e.target.value)} className={inputClass} />
              {account.errors.email && <p className="mt-1 text-xs text-red-600">{account.errors.email}</p>}
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">New password (leave blank to keep)</label>
              <input type="password" value={account.data.password} onChange={(e) => account.setData('password', e.target.value)} className={inputClass} />
              {account.errors.password && <p className="mt-1 text-xs text-red-600">{account.errors.password}</p>}
            </div>
            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input type="checkbox" checked={account.data.super_admin} onChange={(e) => account.setData('super_admin', e.target.checked)} />
              Super admin
            </label>
            <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
              <input type="checkbox" checked={account.data.force_password_change} onChange={(e) => account.setData('force_password_change', e.target.checked)} />
              Force password change on next login
            </label>
            <div className="flex gap-2">
              <button disabled={account.processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Save</button>
              <Link href={route('app.admin.users.index')} className="rounded-md px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">Cancel</Link>
            </div>
          </div>
        </form>

        {/* Security actions */}
        <div className={cardClass}>
          <h2 className="mb-1 text-lg font-semibold text-slate-900 dark:text-white">Security actions</h2>
          <p className="mb-4 text-sm text-slate-500 dark:text-slate-400">Email this user a link to reset their password.</p>
          <button onClick={sendPasswordReset} className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Send password reset
          </button>
        </div>

        {/* Project memberships */}
        <div className={cardClass}>
          <h2 className="mb-4 text-lg font-semibold text-slate-900 dark:text-white">Project memberships</h2>

          {memberships.length === 0 ? (
            <p className="mb-4 text-sm text-slate-500 dark:text-slate-400">Not a member of any project yet.</p>
          ) : (
            <div className="mb-4 overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800">
              <table className="w-full text-sm">
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {memberships.map((m) => (
                    <tr key={m.id}>
                      <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{m.name}</td>
                      <td className="px-4 py-3">
                        <select value={m.role} onChange={(e) => changeRole(m, e.target.value)} className="rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                          <option value="admin">Admin</option>
                          <option value="member">Member</option>
                        </select>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <button onClick={() => removeMembership(m)} className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          <form onSubmit={attachProject} className="flex items-end gap-2">
            <div className="flex-1">
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Add to project</label>
              <select value={attach.data.project_id} onChange={(e) => attach.setData('project_id', e.target.value)} className={inputClass}>
                <option value="">Select a project…</option>
                {availableProjects.map((p) => (
                  <option key={p.id} value={p.id}>{p.name}</option>
                ))}
              </select>
              {attach.errors.project_id && <p className="mt-1 text-xs text-red-600">{attach.errors.project_id}</p>}
            </div>
            <select value={attach.data.role} onChange={(e) => attach.setData('role', e.target.value)} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
              <option value="member">Member</option>
              <option value="admin">Admin</option>
            </select>
            <button disabled={attach.processing || !attach.data.project_id} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Add</button>
          </form>
        </div>
      </div>
    </AdminLayout>
  );
}
