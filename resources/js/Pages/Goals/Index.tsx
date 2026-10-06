import { BackLink, EmptyState, Flash, IconButton, LinkButton, PageHeader, RowActions, TableCard } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmDelete } from '@/lib/confirm';
import { formatMoney } from '@/lib/format';
import { goalMatchLabel } from '@/lib/goals';
import { useTranslation } from '@/lib/i18n';
import { Goal, PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Target, Trash2 } from 'lucide-react';

export default function GoalsIndex({ site, goals }: { site: { id: string; name: string }; goals: Goal[] }) {
  const { project } = usePage<PageProps>().props;
  const { t, locale } = useTranslation();

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
      <div className="px-4 py-6 sm:px-8 sm:py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.analytics.show', { project: project!.id, site: site.id })}>{t('analytics.goals.back')}</BackLink>
        </div>
        <PageHeader title={t('analytics.goals.title', { name: site.name })} action={createBtn} />
        <Flash />

        {goals.length === 0 ? (
          <EmptyState icon={Target} title={t('analytics.goals.empty')} />
        ) : (
          <TableCard
            columns={[
              { label: t('analytics.goals.columns.name') },
              { label: t('analytics.goals.columns.type'), className: 'hidden sm:table-cell' },
              { label: t('analytics.goals.columns.match'), className: 'hidden sm:table-cell' },
              { label: t('analytics.goals.columns.value'), align: 'right', className: 'hidden md:table-cell' },
              { label: '' },
            ]}
          >
            <tbody className="divide-line divide-y">
              {goals.map((goal) => (
                <tr key={goal.id} className="group">
                  <td className="text-foreground px-4 py-3 font-medium">
                    {goal.name}
                    {/* Phones show type and match below the name instead of in their own columns */}
                    <span className="text-muted mt-0.5 block font-mono text-xs font-normal break-all sm:hidden">
                      {goal.type} · {goalMatchLabel(goal)}
                    </span>
                  </td>
                  <td className="text-muted hidden px-4 py-3 sm:table-cell">{goal.type}</td>
                  <td className="text-muted hidden px-4 py-3 font-mono break-all sm:table-cell">{goalMatchLabel(goal)}</td>
                  <td className="text-muted hidden px-4 py-3 text-right whitespace-nowrap tabular-nums md:table-cell">
                    {goal.value === null ? '—' : formatMoney(goal.value, goal.currency ?? '', locale)}
                  </td>
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
