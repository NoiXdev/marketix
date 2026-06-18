import AppLayout from '@/Layouts/AppLayout';
import { confirmDelete } from '@/lib/confirm';
import { PageProps, ProjectInvitation, ProjectMember } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { Mail, Send, Trash2, UserPlus } from 'lucide-react';

export default function TeamIndex({ members, invitations }: { members: ProjectMember[]; invitations: ProjectInvitation[] }) {
  const { project, auth, flash } = usePage<PageProps>().props;
  const invite = useForm({ email: '', role: 'member' });

  function sendInvite(e: React.FormEvent) {
    e.preventDefault();
    invite.post(route('app.project.team.invitations.store', { project: project!.id }), { onSuccess: () => invite.reset() });
  }

  async function revoke(invitation: ProjectInvitation) {
    if (!(await confirmDelete({ title: 'Revoke invitation?', text: `Revoke the invitation for ${invitation.email}?`, confirmText: 'Revoke' }))) return;
    router.delete(route('app.project.team.invitations.destroy', { project: project!.id, invitation: invitation.id }));
  }

  function resend(invitation: ProjectInvitation) {
    router.post(route('app.project.team.invitations.resend', { project: project!.id, invitation: invitation.id }), {}, { preserveScroll: true });
  }

  function changeRole(member: ProjectMember, role: string) {
    router.patch(route('app.project.team.members.update', { project: project!.id, user: member.id }), { role });
  }

  async function removeMember(member: ProjectMember) {
    if (!(await confirmDelete({ title: 'Remove member?', text: `Remove ${member.name}?`, confirmText: 'Remove' }))) return;
    router.delete(route('app.project.team.members.destroy', { project: project!.id, user: member.id }));
  }

  const inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

  return (
    <AppLayout title="Team">
      <div className="px-8 py-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Team</h1>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage members and invitations for this project</p>
        </div>

        {flash?.success && <div className="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{flash.success}</div>}
        {flash?.error && <div className="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">{flash.error}</div>}

        <form onSubmit={sendInvite} className="mb-8 flex max-w-2xl items-end gap-2">
          <div className="flex-1">
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Invite by email</label>
            <input type="email" value={invite.data.email} onChange={(e) => invite.setData('email', e.target.value)} placeholder="person@example.com" className={inputClass} />
            {invite.errors.email && <p className="mt-1 text-xs text-red-600">{invite.errors.email}</p>}
          </div>
          <select value={invite.data.role} onChange={(e) => invite.setData('role', e.target.value)} className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <option value="member">Member</option>
            <option value="admin">Admin</option>
          </select>
          <button disabled={invite.processing} className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
            <UserPlus className="h-4 w-4" />
            Invite
          </button>
        </form>

        <h2 className="mb-3 text-lg font-semibold text-slate-900 dark:text-white">Members</h2>
        <div className="mb-8 overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
          <table className="w-full text-sm">
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
              {members.map((member) => (
                <tr key={member.id}>
                  <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{member.name}</td>
                  <td className="px-4 py-3 text-slate-500 dark:text-slate-400">{member.email}</td>
                  <td className="px-4 py-3">
                    <select
                      value={member.role}
                      onChange={(e) => changeRole(member, e.target.value)}
                      disabled={member.id === auth.user.id}
                      className="rounded-md border border-slate-300 px-2 py-1 text-sm disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    >
                      <option value="admin">Admin</option>
                      <option value="member">Member</option>
                    </select>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {member.id !== auth.user.id && (
                      <button onClick={() => removeMember(member)} className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                        <Trash2 className="h-4 w-4" />
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {invitations.length > 0 && (
          <>
            <h2 className="mb-3 text-lg font-semibold text-slate-900 dark:text-white">Pending invitations</h2>
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
              <table className="w-full text-sm">
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {invitations.map((inv) => (
                    <tr key={inv.id}>
                      <td className="px-4 py-3 text-slate-900 dark:text-white">
                        <span className="flex items-center gap-2"><Mail className="h-4 w-4 text-slate-400" />{inv.email}</span>
                      </td>
                      <td className="px-4 py-3 text-slate-500 dark:text-slate-400 capitalize">{inv.role}</td>
                      <td className="px-4 py-3">
                        {inv.expired ? (
                          <span className="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">Expired</span>
                        ) : (
                          <span className="text-xs text-slate-400">Expires {new Date(inv.expires_at).toLocaleDateString()}</span>
                        )}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <button
                          onClick={() => resend(inv)}
                          disabled={!inv.can_resend}
                          title={inv.can_resend ? 'Resend invitation' : 'Recently sent — try again shortly'}
                          className="mr-1 rounded p-1.5 text-slate-400 enabled:hover:bg-indigo-50 enabled:hover:text-indigo-600 disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-indigo-900/20"
                        >
                          <Send className="h-4 w-4" />
                        </button>
                        <button onClick={() => revoke(inv)} className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20">
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
      </div>
    </AppLayout>
  );
}
