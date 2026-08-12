import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Flash, IconButton, LinkButton, PageHeader, RowActions, StatusPill, TableCard } from '@/Components/ui';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { CrawlListItem, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Plus, Search, Trash2 } from 'lucide-react';

const statusVariant: Record<CrawlListItem['status'], 'neutral' | 'success' | 'warning' | 'danger'> = {
  queued: 'neutral',
  running: 'warning',
  completed: 'success',
  failed: 'danger',
};

export default function CrawlsIndex({ crawls }: { crawls: CrawlListItem[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  async function destroy(c: CrawlListItem) {
    if (!(await confirmDelete({ title: c.start_url }))) return;
    router.delete(route('app.project.crawls.destroy', { project: project!.id, crawl: c.id }));
  }

  const createBtn = (
    <LinkButton href={route('app.project.crawls.create', { project: project!.id })}>
      <Plus className="h-4 w-4" />
      {t('crawler.create')}
    </LinkButton>
  );

  return (
    <AppLayout title={t('crawler.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('crawler.title')} action={createBtn} />
        <Flash />

        {crawls.length === 0 ? (
          <EmptyState
            icon={Search}
            title={t('crawler.empty')}
            hint={t('crawler.empty_hint')}
            action={
              <LinkButton size="sm" href={route('app.project.crawls.create', { project: project!.id })}>
                <Plus className="h-3.5 w-3.5" />
                {t('crawler.create')}
              </LinkButton>
            }
          />
        ) : (
          <TableCard
            columns={[
              { label: t('crawler.col_url') },
              { label: t('crawler.col_status') },
              { label: t('crawler.col_pages') },
              { label: '' },
            ]}
          >
            <tbody className="divide-y divide-line">
              {crawls.map((c) => (
                <tr
                  key={c.id}
                  onClick={rowLink(route('app.project.crawls.show', { project: project!.id, crawl: c.id }))}
                  className={`group ${ROW_LINK_CLASS}`}
                >
                  <td className="px-4 py-3">
                    <Link
                      href={route('app.project.crawls.show', { project: project!.id, crawl: c.id })}
                      className="font-medium text-foreground hover:text-accent-soft-foreground"
                    >
                      {c.start_url}
                    </Link>
                  </td>
                  <td className="px-4 py-3">
                    <StatusPill status={statusVariant[c.status]}>{t(`crawler.status_${c.status}`)}</StatusPill>
                  </td>
                  <td className="px-4 py-3 text-muted">{c.pages_crawled}</td>
                  <RowActions>
                    <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => destroy(c)} />
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
