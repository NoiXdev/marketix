import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Flash, IconButton, LinkButton, PageHeader, RowActions, StatusPill, TableCard } from '@/Components/ui';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { FileBarChart, Pencil, Plus, Send, Trash2 } from 'lucide-react';

interface ReportRow {
  id: string;
  name: string;
  type: string;
  frequency: string;
  next_run_at: string | null;
  active: boolean;
}

export default function ReportsIndex({ reports }: { reports: ReportRow[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  async function destroy(report: ReportRow) {
    const confirmed = await confirmDelete({
      title: t('reports.index.delete_confirm.title'),
      text: t('reports.index.delete_confirm.text', { name: report.name }),
      confirmText: t('reports.index.delete_confirm.button'),
    });
    if (!confirmed) return;
    router.delete(route('app.project.reports.destroy', { project: project!.id, report: report.id }));
  }

  function toggle(report: ReportRow) {
    router.post(
      route('app.project.reports.toggle', { project: project!.id, report: report.id }),
      {},
      { preserveScroll: true },
    );
  }

  function sendNow(report: ReportRow) {
    router.post(
      route('app.project.reports.send-now', { project: project!.id, report: report.id }),
      {},
      { preserveScroll: true },
    );
  }

  const createBtn = (
    <LinkButton href={route('app.project.reports.create', { project: project!.id })}>
      <Plus className="h-4 w-4" />
      {t('reports.index.create')}
    </LinkButton>
  );

  return (
    <AppLayout title={t('reports.index.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('reports.index.title')} subtitle={t('reports.index.subtitle')} action={createBtn} />
        <Flash />

        {reports.length === 0 ? (
          <EmptyState
            icon={FileBarChart}
            title={t('reports.index.empty')}
            action={
              <LinkButton size="sm" href={route('app.project.reports.create', { project: project!.id })}>
                <Plus className="h-3.5 w-3.5" />
                {t('reports.index.create')}
              </LinkButton>
            }
          />
        ) : (
          <TableCard
            columns={[
              { label: t('reports.index.columns.name') },
              { label: t('reports.index.columns.type') },
              { label: t('reports.index.columns.frequency') },
              { label: t('reports.index.columns.next_run') },
              { label: t('reports.index.columns.active') },
              { label: '' },
            ]}
          >
            <tbody className="divide-y divide-line">
              {reports.map((report) => (
                <tr key={report.id} className="group">
                  <td className="px-4 py-3 font-medium text-foreground">{report.name}</td>
                  <td className="px-4 py-3 text-muted">{t(`reports.types.${report.type}`)}</td>
                  <td className="px-4 py-3 text-muted">{t(`reports.frequencies.${report.frequency}`)}</td>
                  <td className="px-4 py-3 text-muted">
                    {report.next_run_at ? new Date(report.next_run_at).toLocaleString() : '—'}
                  </td>
                  <td className="px-4 py-3">
                    <button
                      type="button"
                      onClick={() => toggle(report)}
                      className="rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
                    >
                      <StatusPill status={report.active ? 'success' : 'neutral'}>{t('reports.form.active')}</StatusPill>
                    </button>
                  </td>
                  <RowActions>
                    <IconButton icon={Send} label={t('reports.index.actions.send_now')} onClick={() => sendNow(report)} />
                    <IconButton
                      icon={Pencil}
                      label={t('reports.index.actions.edit')}
                      href={route('app.project.reports.edit', { project: project!.id, report: report.id })}
                    />
                    <IconButton
                      icon={Trash2}
                      label={t('reports.index.actions.delete')}
                      variant="danger"
                      onClick={() => destroy(report)}
                    />
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
