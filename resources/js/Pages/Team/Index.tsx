import { Badge, Button, Field, Flash, FormSection, IconButton, Input, PageHeader, Select, TableCard } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { PageProps, ProjectInvitation, ProjectMember } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { Mail, Send, Trash2, UserPlus } from 'lucide-react';

export default function TeamIndex({ members, invitations }: { members: ProjectMember[]; invitations: ProjectInvitation[] }) {
  const { t } = useTranslation();
  const { project, auth } = usePage<PageProps>().props;
  const invite = useForm({ email: '', role: 'member' });

  function sendInvite(e: React.FormEvent) {
    e.preventDefault();
    invite.post(route('app.project.team.invitations.store', { project: project!.id }), { onSuccess: () => invite.reset() });
  }

  async function revoke(invitation: ProjectInvitation) {
    if (
      !(await confirmDelete({
        title: t('team.confirm.revoke_title'),
        text: t('team.confirm.revoke_text', { email: invitation.email }),
        confirmText: t('team.confirm.revoke_button'),
      }))
    )
      return;
    router.delete(route('app.project.team.invitations.destroy', { project: project!.id, invitation: invitation.id }));
  }

  function resend(invitation: ProjectInvitation) {
    router.post(route('app.project.team.invitations.resend', { project: project!.id, invitation: invitation.id }), {}, { preserveScroll: true });
  }

  function changeRole(member: ProjectMember, role: string) {
    router.patch(route('app.project.team.members.update', { project: project!.id, user: member.id }), { role });
  }

  async function removeMember(member: ProjectMember) {
    if (
      !(await confirmDelete({
        title: t('team.confirm.remove_title'),
        text: t('team.confirm.remove_text', { name: member.name }),
        confirmText: t('team.confirm.remove_button'),
      }))
    )
      return;
    router.delete(route('app.project.team.members.destroy', { project: project!.id, user: member.id }));
  }

  return (
    <AppLayout title={t('team.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('team.title')} subtitle={t('team.subtitle')} />

        <Flash />

        <form onSubmit={sendInvite} className="mb-8 max-w-2xl">
          <FormSection>
            <div className="flex items-end gap-2">
              <div className="flex-1">
                <Field label={t('team.invite.label')} error={invite.errors.email}>
                  <Input
                    type="email"
                    value={invite.data.email}
                    onChange={(e) => invite.setData('email', e.target.value)}
                    placeholder={t('team.invite.email_placeholder')}
                  />
                </Field>
              </div>
              <div className="w-32">
                <Select value={invite.data.role} onChange={(e) => invite.setData('role', e.target.value)}>
                  <option value="member">{t('common.roles.member')}</option>
                  <option value="admin">{t('common.roles.admin')}</option>
                </Select>
              </div>
              <Button type="submit" loading={invite.processing}>
                <UserPlus className="h-4 w-4" />
                {t('team.invite.submit')}
              </Button>
            </div>
          </FormSection>
        </form>

        <h2 className="mb-3 text-lg font-semibold text-foreground">{t('team.members.heading')}</h2>
        <div className="mb-8">
          <TableCard
            columns={[
              { label: t('team.members.columns.name') },
              { label: t('team.members.columns.email') },
              { label: t('team.members.columns.role') },
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
                      <Select value={member.role} onChange={(e) => changeRole(member, e.target.value)} disabled={member.id === auth.user.id}>
                        <option value="admin">{t('common.roles.admin')}</option>
                        <option value="member">{t('common.roles.member')}</option>
                      </Select>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {member.id !== auth.user.id && <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => removeMember(member)} />}
                  </td>
                </tr>
              ))}
            </tbody>
          </TableCard>
        </div>

        {invitations.length > 0 && (
          <>
            <h2 className="mb-3 text-lg font-semibold text-foreground">{t('team.invitations.heading')}</h2>
            <TableCard columns={[{ label: t('team.members.columns.email') }, { label: t('team.members.columns.role') }, { label: '' }, { label: '' }]}>
              <tbody className="divide-y divide-line">
                {invitations.map((inv) => (
                  <tr key={inv.id}>
                    <td className="px-4 py-3 text-foreground">
                      <span className="flex items-center gap-2">
                        <Mail className="h-4 w-4 text-subtle" />
                        {inv.email}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-muted">{t(`common.roles.${inv.role}`)}</td>
                    <td className="px-4 py-3">
                      {inv.expired ? (
                        <Badge variant="warning">{t('team.invitations.expired_badge')}</Badge>
                      ) : (
                        <span className="text-xs text-subtle">{t('team.invitations.expires_at', { date: new Date(inv.expires_at).toLocaleDateString() })}</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-1">
                        <IconButton
                          icon={Send}
                          label={inv.can_resend ? t('team.invitations.resend_title') : t('team.invitations.resend_disabled_title')}
                          disabled={!inv.can_resend}
                          onClick={() => resend(inv)}
                        />
                        <IconButton icon={Trash2} label={t('team.confirm.revoke_button')} variant="danger" onClick={() => revoke(inv)} />
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </TableCard>
          </>
        )}
      </div>
    </AppLayout>
  );
}
