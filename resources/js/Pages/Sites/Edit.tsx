import CodeSnippet from '@/Components/CodeSnippet';
import { Favicon } from '@/Components/icons/Favicon';
import { BackLink, Button, Flash, LinkButton } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmTyped } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import SiteForm, { Option, SiteFormData } from '@/Pages/Sites/partials/SiteForm';
import { PageProps, Site } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function SitesEdit({
  site,
  trackingModes,
  consentModes,
  snippet,
  lastSeenAt,
}: {
  site: Site;
  trackingModes: Option[];
  consentModes: Option[];
  snippet: string;
  lastSeenAt: string | null;
}) {
  const { project } = usePage<PageProps>().props;
  const { t, locale } = useTranslation();
  const [now] = useState(() => Date.now());
  const projectId = project!.id;

  const form = useForm<SiteFormData>({
    name: site.name,
    domain: site.domain,
    tracking_mode: site.tracking_mode,
    consent_mode: site.consent_mode,
    consent_signal: site.consent_signal ?? '',
    respect_dnt: site.respect_dnt ?? false,
    track_outbound_links: site.track_outbound_links ?? true,
    track_file_downloads: site.track_file_downloads ?? true,
    site_search_params: site.site_search_params ?? '',
    retention_days: site.retention_days ?? '',
  });

  form.transform((data) => ({ ...data, retention_days: data.retention_days === '' ? null : data.retention_days }));

  const indexUrl = route('app.project.sites.index', { project: projectId });
  const analyticsUrl = route('app.project.analytics.show', { project: projectId, site: site.id });

  function lastSeen(iso: string) {
    const relative = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
    const minutes = Math.max(0, Math.round((now - new Date(iso).getTime()) / 60000));
    if (minutes < 60) return relative.format(-minutes, 'minute');
    if (minutes < 1440) return relative.format(-Math.floor(minutes / 60), 'hour');
    return relative.format(-Math.floor(minutes / 1440), 'day');
  }

  async function destroy() {
    const confirmed = await confirmTyped({
      title: t('analytics.sites.delete.title'),
      text: t('analytics.sites.delete.confirm', { name: site.name }),
      match: site.name,
      confirmText: t('analytics.sites.delete.action'),
    });
    if (confirmed) router.delete(route('app.project.sites.destroy', { project: projectId, site: site.id }));
  }

  return (
    <AppLayout title={t('analytics.sites.edit')}>
      <div className="px-8 py-8">
        <BackLink href={indexUrl}>{t('analytics.sites.back')}</BackLink>
        <div className="mt-3 mb-6 flex max-w-6xl flex-wrap items-center justify-between gap-4">
          <div className="flex min-w-0 items-center gap-3">
            <span className="border-line bg-surface grid h-11 w-11 shrink-0 place-items-center rounded-lg border">
              <Favicon domain={site.domain} className="h-5 w-5 rounded-[3px]" />
            </span>
            <div className="min-w-0">
              <h1 className="text-foreground truncate text-2xl font-bold tracking-tight">{site.name}</h1>
              <p className="text-muted truncate text-sm">
                {t('analytics.sites.edit')} · {site.domain}
              </p>
            </div>
          </div>
          <LinkButton variant="secondary" href={analyticsUrl}>
            {t('analytics.sites.overview.open')}
            <ArrowRight className="h-4 w-4" />
          </LinkButton>
        </div>

        <Flash />

        <div className="grid max-w-6xl items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
          <div className="min-w-0 space-y-5">
            <section className="border-line bg-surface rounded-[var(--radius)] border p-5">
              <h2 className="text-foreground text-sm font-semibold">{t('analytics.sites.snippet_title')}</h2>
              <p className="text-muted mt-1 mb-3 text-xs">{t('analytics.sites.snippet_hint', { domain: site.domain })}</p>
              <CodeSnippet code={snippet} language="html" wrap className="w-full" />
            </section>

            <SiteForm
              form={form}
              trackingModes={trackingModes}
              consentModes={consentModes}
              submitLabel={t('common.actions.save')}
              cancelHref={indexUrl}
              onSubmit={() => form.put(route('app.project.sites.update', { project: projectId, site: site.id }))}
            />
          </div>

          <aside className="space-y-4 lg:sticky lg:top-6">
            <section className="border-line bg-surface rounded-[var(--radius)] border p-5 shadow-[var(--shadow-sm)]">
              <h2 className="text-foreground text-sm font-semibold">{t('analytics.sites.form.status.title')}</h2>
              <p className="mt-2 flex items-start gap-2 text-sm">
                <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${lastSeenAt ? 'bg-[color:var(--success-dot)]' : 'bg-[color:var(--warning-dot)]'}`} />
                <span className="text-foreground">
                  {lastSeenAt ? t('analytics.sites.form.status.receiving', { time: lastSeen(lastSeenAt) }) : t('analytics.sites.form.status.waiting')}
                </span>
              </p>
              {!lastSeenAt && <p className="text-muted mt-2 text-xs leading-relaxed">{t('analytics.sites.form.status.waiting_hint')}</p>}
            </section>

            <section className="bg-surface rounded-[var(--radius)] border border-[color:color-mix(in_srgb,var(--danger-foreground)_30%,transparent)] p-5">
              <h2 className="text-danger-foreground text-sm font-semibold">{t('analytics.sites.form.danger.title')}</h2>
              <p className="text-muted mt-1 text-xs leading-relaxed">{t('analytics.sites.form.danger.text')}</p>
              <Button variant="danger" size="sm" className="mt-3" onClick={destroy}>
                <Trash2 className="h-3.5 w-3.5" />
                {t('analytics.sites.delete.action')}
              </Button>
            </section>
          </aside>
        </div>
      </div>
    </AppLayout>
  );
}
