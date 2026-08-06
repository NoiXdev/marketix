import { Button, Checkbox, EmptyState, Field, Flash, FormSection, IconButton, Input, PageHeader, Select, TableCard } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { ProjectRole } from '@/types';
import { Link, router, useForm } from '@inertiajs/react';
import { Trash2, Users } from 'lucide-react';

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
  const { t } = useTranslation();
  const account = useForm({
    name: user.name,
    email: user.email,
    password: '',
    super_admin: user.super_admin,
    force_password_change: user.force_password_change,
  });
  const attach = useForm({ project_id: '', role: 'member' });

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
    if (
      !(await confirmDelete({
        title: t('admin.users.memberships.remove_confirm.title'),
        text: t('admin.users.memberships.remove_confirm.text', { name: user.name, project: membership.name }),
        confirmText: t('admin.users.memberships.remove_confirm.confirm'),
      }))
    )
      return;
    router.delete(route('app.admin.users.projects.destroy', { user: user.id, project: membership.id }));
  }

  return (
    <AdminLayout title={t('admin.users.edit.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('admin.users.edit.title')} />

        <Flash />

        {/* Account */}
        <form onSubmit={saveAccount} className="mb-8 max-w-2xl">
          <FormSection title={t('admin.users.sections.account')}>
            <Field label={t('admin.users.fields.name')} error={account.errors.name}>
              <Input value={account.data.name} onChange={(e) => account.setData('name', e.target.value)} />
            </Field>
            <Field label={t('admin.users.fields.email')} error={account.errors.email}>
              <Input type="email" value={account.data.email} onChange={(e) => account.setData('email', e.target.value)} />
            </Field>
            <Field label={t('admin.users.fields.new_password_hint')} error={account.errors.password}>
              <Input type="password" value={account.data.password} onChange={(e) => account.setData('password', e.target.value)} />
            </Field>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <Checkbox checked={account.data.super_admin} onChange={(e) => account.setData('super_admin', e.target.checked)} />
              {t('admin.users.fields.super_admin')}
            </label>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <Checkbox
                checked={account.data.force_password_change}
                onChange={(e) => account.setData('force_password_change', e.target.checked)}
              />
              {t('admin.users.fields.force_password_change')}
            </label>
            <div className="flex gap-2">
              <Button type="submit" loading={account.processing}>
                {t('common.actions.save')}
              </Button>
              <Link
                href={route('app.admin.users.index')}
                className="inline-flex items-center rounded-[var(--radius-sm)] px-4 py-2 text-sm font-semibold text-muted transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
              >
                {t('common.actions.cancel')}
              </Link>
            </div>
          </FormSection>
        </form>

        {/* Security actions */}
        <div className="mb-8 max-w-2xl">
          <FormSection title={t('admin.users.sections.security')}>
            <p className="text-sm text-muted">{t('admin.users.security.reset_description')}</p>
            <Button variant="secondary" onClick={sendPasswordReset}>
              {t('admin.users.security.send_reset')}
            </Button>
          </FormSection>
        </div>

        {/* Project memberships */}
        <div className="mb-8 max-w-2xl">
          <FormSection title={t('admin.users.memberships.title')}>
            {memberships.length === 0 ? (
              <EmptyState icon={Users} title={t('admin.users.memberships.empty')} />
            ) : (
              <TableCard columns={[{ label: t('admin.users.columns.name') }, { label: 'Role' }, { label: '' }]}>
                <tbody className="divide-y divide-line">
                  {memberships.map((m) => (
                    <tr key={m.id}>
                      <td className="px-4 py-3 font-medium text-foreground">{m.name}</td>
                      <td className="px-4 py-3">
                        <div className="w-32">
                          <Select value={m.role} onChange={(e) => changeRole(m, e.target.value)}>
                            <option value="admin">{t('common.roles.admin')}</option>
                            <option value="member">{t('common.roles.member')}</option>
                          </Select>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => removeMembership(m)} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </TableCard>
            )}

            <form onSubmit={attachProject} className="flex items-end gap-2">
              <div className="flex-1">
                <Field label={t('admin.users.memberships.add_label')} error={attach.errors.project_id}>
                  <Select value={attach.data.project_id} onChange={(e) => attach.setData('project_id', e.target.value)}>
                    <option value="">{t('admin.users.memberships.select_project_placeholder')}</option>
                    {availableProjects.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </Select>
                </Field>
              </div>
              <div className="w-32">
                <Select value={attach.data.role} onChange={(e) => attach.setData('role', e.target.value)}>
                  <option value="member">{t('common.roles.member')}</option>
                  <option value="admin">{t('common.roles.admin')}</option>
                </Select>
              </div>
              <Button type="submit" loading={attach.processing} disabled={attach.processing || !attach.data.project_id}>
                {t('common.actions.add')}
              </Button>
            </form>
          </FormSection>
        </div>
      </div>
    </AdminLayout>
  );
}
