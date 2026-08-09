import AppLayout from '@/Layouts/AppLayout';
import { BackLink, EmptyState, Flash, IconButton, LinkButton, PageHeader, RowActions, TableCard } from '@/Components/ui';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { Goal, PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Target, Trash2 } from 'lucide-react';

export default function GoalsIndex({ site, goals }: { site: { id: string; name: string }; goals: Goal[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  async function destroy(goal: Goal) {
    if (!(await confirmDelete({ title: goal.name }))) return;
    router.delete(route('app.project.analytics.goals.destroy', { project: project!.id, site: site.id, goal: goal.id }));
  }

  const createBtn = (
    <LinkButton href={route('app.project.analytics.goals.create', { project: project!.id, site: site.id })}>
      <Plus className="h-4 w-4" />
      {t('analytics.goals.create')}
    </LinkButton>
  );

  return (
    <AppLayout title={t('analytics.goals.title', { name: site.name })}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.analytics.show', { project: project!.id, site: site.id })}>
            {t('analytics.goals.back')}
          </BackLink>
        </div>
        <PageHeader title={t('analytics.goals.title', { name: site.name })} action={createBtn} />
        <Flash />

        {goals.length === 0 ? (
          <EmptyState icon={Target} title={t('analytics.goals.empty')} />
        ) : (
          <TableCard
            columns={[
              { label: t('analytics.goals.columns.name') },
              { label: t('analytics.goals.columns.type') },
              { label: t('analytics.goals.columns.match') },
              { label: '' },
            ]}
          >
            <tbody className="divide-y divide-line">
              {goals.map((goal) => (
                <tr key={goal.id} className="group">
                  <td className="px-4 py-3 font-medium text-foreground">{goal.name}</td>
                  <td className="px-4 py-3 text-muted">{goal.type}</td>
                  <td className="px-4 py-3 font-mono text-muted">{goal.match_value}</td>
                  <RowActions>
                    <IconButton
                      icon={Pencil}
                      label={t('common.actions.edit')}
                      href={route('app.project.analytics.goals.edit', { project: project!.id, site: site.id, goal: goal.id })}
                    />
                    <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => destroy(goal)} />
                  </RowActions>
                </tr>
              ))}
            </tbody>
          </TableCard>
        )}
      </div>
    </AppLayout>
  );
}
