import { EmptyState, Flash, LinkButton, PageHeader } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmTyped } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import SiteCard, { SiteStats } from '@/Pages/Sites/partials/SiteCard';
import { PageProps, Site } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { LineChart, Plus } from 'lucide-react';

type OverviewSite = Site & { tracking_mode_label: string };

export default function SitesIndex({ sites, stats }: { sites: OverviewSite[]; stats?: Record<string, SiteStats | null> }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const projectId = project!.id;

  async function destroy(site: Site) {
    const confirmed = await confirmTyped({
      title: t('analytics.sites.delete.title'),
      text: t('analytics.sites.delete.confirm', { name: site.name }),
      match: site.name,
      confirmText: t('analytics.sites.delete.action'),
    });
    if (!confirmed) return;
    router.delete(route('app.project.sites.destroy', { project: projectId, site: site.id }));
  }

  const createUrl = route('app.project.sites.create', { project: projectId });
  const createBtn = (
    <LinkButton href={createUrl}>
      <Plus className="h-4 w-4" />
      {t('analytics.sites.create')}
    </LinkButton>
  );

  return (
    <AppLayout title={t('analytics.sites.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('analytics.sites.title')} subtitle={t('analytics.sites.overview.subtitle')} action={createBtn} />
        <Flash />

        {sites.length === 0 ? (
          <EmptyState
            icon={LineChart}
            title={t('analytics.sites.empty')}
            hint={t('analytics.sites.empty_hint')}
            action={
              <LinkButton size="sm" href={createUrl}>
                <Plus className="h-3.5 w-3.5" />
                {t('analytics.sites.create')}
              </LinkButton>
            }
          />
        ) : (
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
            {sites.map((site) => (
              <SiteCard
                key={site.id}
                site={site}
                stats={stats === undefined ? undefined : (stats[site.id] ?? null)}
                analyticsUrl={route('app.project.analytics.show', { project: projectId, site: site.id })}
                editUrl={route('app.project.sites.edit', { project: projectId, site: site.id })}
                onDelete={() => destroy(site)}
              />
            ))}
            <Link
              href={createUrl}
              className="group border-line-strong text-muted hover:border-accent hover:bg-accent-soft/40 hover:text-accent-soft-foreground flex min-h-48 flex-col items-center justify-center gap-2 rounded-[var(--radius)] border border-dashed p-6 text-center transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
            >
              <span className="bg-elevated group-hover:bg-accent-soft grid h-10 w-10 place-items-center rounded-full transition-colors">
                <Plus className="h-5 w-5" />
              </span>
              <span className="text-sm font-semibold">{t('analytics.sites.create')}</span>
              <span className="text-subtle text-xs">{t('analytics.sites.overview.add_hint')}</span>
            </Link>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
