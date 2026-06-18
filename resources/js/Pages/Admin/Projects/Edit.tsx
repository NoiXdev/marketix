import AdminLayout from '@/Layouts/AdminLayout';
import { ProjectMember } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, Trash2 } from 'lucide-react';

interface EditProject {
  id: string;
  name: string;
  locked: boolean;
}

interface AssignableUser {
  id: string;
  name: string;
  email: string;
}

export default function AdminProjectsEdit({ project, members, assignableUsers }: { project: EditProject; members: ProjectMember[]; assignableUsers: AssignableUser[] }) {
  const inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';
  const details = useForm({ name: project.name, locked: project.locked });
  const assign = useForm({ user_id: '', role: 'member' });

  function saveDetails(e: React.FormEvent) {
    e.preventDefault();
    details.put(route('app.admin.projects.update', { project: project.id }));
  }

  function assignUser(e: React.FormEvent) {
    e.preventDefault();
    assign.post(route('app.admin.projects.members.store', { project: project.id }), { onSuccess: () => assign.reset() });
  }

  function changeRole(member: ProjectMember, role: string) {
    router.patch(route('app.admin.projects.members.update', { project: project.id, user: member.id }), { role });
  }

  function removeMember(member: ProjectMember) {
    if (!confirm(`Remove ${member.name} from this project?`)) return;
    router.delete(route('app.admin.projects.members.destroy', { project: project.id, user: member.id }));
  }

  return (
    <AdminLayout title="Edit project">
      <div className="px-8 py-8">
        <div className="mb-6 flex items-center justify-between">
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Edit project</h1>
          <Link
            href={route('app.project.dashboard', { project: project.id })}
            className="inline-flex items-center gap-2 rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            <ExternalLink className="h-4 w-4" />
            Open project
          </Link>
        </div>

        <form onSubmit={saveDetails} className="mb-10 max-w-md space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
            <input value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} className={inputClass} />
            {details.errors.name && <p className="mt-1 text-xs text-red-600">{details.errors.name}</p>}
          </div>
          <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" checked={details.data.locked} onChange={(e) => details.setData('locked', e.target.checked)} />
            Locked
          </label>
          <div className="flex gap-2">
            <button disabled={details.processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Save</button>
            <Link href={route('app.admin.projects.index')} className="rounded-md px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">Back</Link>
          </div>
        </form>

        <h2 className="mb-3 text-lg font-semibold text-slate-900 dark:text-white">Members</h2>
        <div className="mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
          <table className="w-full text-sm">
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {members.map((member) => (
                <tr key={member.id}>
                  <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{member.name}</td>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{member.email}</td>
                  <td className="px-4 py-3">
                    <select value={member.role} onChange={(e) => changeRole(member, e.target.value)} className="rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                      <option value="admin">Admin</option>
                      <option value="member">Member</option>
                    </select>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button onClick={() => removeMember(member)} className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <form onSubmit={assignUser} className="flex max-w-2xl items-end gap-2">
          <div className="flex-1">
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Assign user</label>
            <select value={assign.data.user_id} onChange={(e) => assign.setData('user_id', e.target.value)} className={inputClass}>
              <option value="">Select a user…</option>
              {assignableUsers.map((u) => (
                <option key={u.id} value={u.id}>{u.name} ({u.email})</option>
              ))}
            </select>
            {assign.errors.user_id && <p className="mt-1 text-xs text-red-600">{assign.errors.user_id}</p>}
          </div>
          <select value={assign.data.role} onChange={(e) => assign.setData('role', e.target.value)} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="member">Member</option>
            <option value="admin">Admin</option>
          </select>
          <button disabled={assign.processing || !assign.data.user_id} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Assign</button>
        </form>
      </div>
    </AdminLayout>
  );
}
