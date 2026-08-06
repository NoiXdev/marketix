import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Flash, IconButton, LinkButton, PageHeader, RowActions, TableCard } from '@/Components/ui';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { PageProps, Site } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { LineChart, Pencil, Plus, Trash2 } from 'lucide-react';

export default function SitesIndex({ sites }: { sites: Site[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  async function destroy(site: Site) {
    if (!(await confirmDelete({ title: site.name }))) return;
    router.delete(route('app.project.sites.destroy', { project: project!.id, site: site.id }));
  }

  const createBtn = (
    <LinkButton href={route('app.project.sites.create', { project: project!.id })}>
      <Plus className="h-4 w-4" />
      {t('analytics.sites.create')}
    </LinkButton>
  );

  return (
    <AppLayout title={t('analytics.sites.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('analytics.sites.title')} action={createBtn} />
        <Flash />

        {sites.length === 0 ? (
          <EmptyState
            icon={LineChart}
            title={t('analytics.sites.empty')}
            hint={t('analytics.sites.empty_hint')}
            action={
              <LinkButton size="sm" href={route('app.project.sites.create', { project: project!.id })}>
                <Plus className="h-3.5 w-3.5" />
                {t('analytics.sites.create')}
              </LinkButton>
            }
          />
        ) : (
          <TableCard
            columns={[
              { label: t('analytics.sites.columns.name') },
              { label: t('analytics.sites.columns.domain') },
              { label: t('analytics.sites.columns.mode') },
              { label: '' },
            ]}
          >
            <tbody className="divide-y divide-line">
              {sites.map((site) => (
                <tr
                  key={site.id}
                  onClick={rowLink(route('app.project.analytics.show', { project: project!.id, site: site.id }))}
                  className={`group ${ROW_LINK_CLASS}`}
                >
                  <td className="px-4 py-3">
                    <Link
                      href={route('app.project.analytics.show', { project: project!.id, site: site.id })}
                      className="font-medium text-foreground hover:text-accent-soft-foreground"
                    >
                      {site.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-muted">{site.domain}</td>
                  <td className="px-4 py-3 text-muted">{site.tracking_mode}</td>
                  <RowActions>
                    <IconButton
                      icon={Pencil}
                      label={t('common.actions.edit')}
                      href={route('app.project.sites.edit', { project: project!.id, site: site.id })}
                    />
                    <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => destroy(site)} />
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
