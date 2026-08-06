import { Button, Checkbox, EmptyState, Field, FormSection, IconButton, Input, PageHeader, Select, TableCard } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { ProjectMember } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { ExternalLink, Trash2, Users } from 'lucide-react';

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
  const { t } = useTranslation();
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

  async function removeMember(member: ProjectMember) {
    if (
      !(await confirmDelete({
        title: t('admin.projects.members.remove_confirm.title'),
        text: t('admin.projects.members.remove_confirm.text', { name: member.name }),
        confirmText: t('admin.projects.members.remove_confirm.confirm'),
      }))
    )
      return;
    router.delete(route('app.admin.projects.members.destroy', { project: project.id, user: member.id }));
  }

  const openBtn = (
    <Link
      href={route('app.project.dashboard', { project: project.id })}
      className="inline-flex items-center gap-2 rounded-[var(--radius-sm)] border border-line-strong bg-surface px-4 py-2 text-sm font-semibold text-foreground transition-colors hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
    >
      <ExternalLink className="h-4 w-4" />
      {t('admin.projects.actions.open')}
    </Link>
  );

  return (
    <AdminLayout title={t('admin.projects.edit.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('admin.projects.edit.title')} action={openBtn} />

        <form onSubmit={saveDetails} className="mb-10 max-w-md">
          <FormSection>
            <Field label={t('admin.projects.fields.name')} error={details.errors.name}>
              <Input value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} />
            </Field>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <Checkbox checked={details.data.locked} onChange={(e) => details.setData('locked', e.target.checked)} />
              {t('admin.projects.fields.locked')}
            </label>
            <div className="flex gap-2">
              <Button type="submit" loading={details.processing}>
                {t('common.actions.save')}
              </Button>
              <Link
                href={route('app.admin.projects.index')}
                className="inline-flex items-center rounded-[var(--radius-sm)] px-4 py-2 text-sm font-semibold text-muted transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
              >
                {t('common.actions.cancel')}
              </Link>
            </div>
          </FormSection>
        </form>

        <h2 className="mb-3 text-lg font-semibold text-foreground">{t('admin.projects.members.title')}</h2>

        {members.length === 0 ? (
          <div className="mb-4">
            <EmptyState icon={Users} title={t('admin.projects.members.empty')} />
          </div>
        ) : (
          <div className="mb-4">
            <TableCard
              columns={[
                { label: t('admin.users.columns.name') },
                { label: t('admin.users.columns.email') },
                { label: t('admin.common.role') },
                { label: '' },
              ]}
            >
              <tbody className="divide-y divide-line">
                {members.map((member) => (
                  <tr key={member.id}>
                    <td className="px-4 py-3 font-medium text-foreground">{member.name}</td>
                    <td className="px-4 py-3 text-muted">{member.email}</td>
                    <td className="px-4 py-3">
                      <div className="w-32">
                        <Select value={member.role} onChange={(e) => changeRole(member, e.target.value)}>
                          <option value="admin">{t('common.roles.admin')}</option>
                          <option value="member">{t('common.roles.member')}</option>
                        </Select>
                      </div>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => removeMember(member)} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </TableCard>
          </div>
        )}

        <form onSubmit={assignUser} className="flex max-w-2xl items-end gap-2">
          <div className="flex-1">
            <Field label={t('admin.projects.members.assign_label')} error={assign.errors.user_id}>
              <Select value={assign.data.user_id} onChange={(e) => assign.setData('user_id', e.target.value)}>
                <option value="">{t('admin.projects.members.select_user_placeholder')}</option>
                {assignableUsers.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name} ({u.email})
                  </option>
                ))}
              </Select>
            </Field>
          </div>
          <div className="w-32">
            <Select value={assign.data.role} onChange={(e) => assign.setData('role', e.target.value)}>
              <option value="member">{t('common.roles.member')}</option>
              <option value="admin">{t('common.roles.admin')}</option>
            </Select>
          </div>
          <Button type="submit" loading={assign.processing} disabled={assign.processing || !assign.data.user_id}>
            {t('admin.projects.members.assign_button')}
          </Button>
        </form>
      </div>
    </AdminLayout>
  );
}
