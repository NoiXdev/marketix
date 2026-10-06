import { BackLink } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';
import SiteForm, { Option, SiteFormData } from '@/Pages/Sites/partials/SiteForm';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

const STEPS = ['create', 'install', 'watch'] as const;

export default function SitesCreate({ trackingModes, consentModes }: { trackingModes: Option[]; consentModes: Option[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const form = useForm<SiteFormData>({
    name: '',
    domain: '',
    tracking_mode: 'cookieless',
    consent_mode: 'immediate',
    consent_signal: '',
    respect_dnt: false,
    track_outbound_links: true,
    track_file_downloads: true,
    site_search_params: '',
    retention_days: '',
  });

  form.transform((data) => ({ ...data, retention_days: data.retention_days === '' ? null : data.retention_days }));

  const indexUrl = route('app.project.sites.index', { project: project!.id });

  return (
    <AppLayout title={t('analytics.sites.create')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={indexUrl}>{t('analytics.sites.back')}</BackLink>
          <h1 className="text-foreground mt-3 text-2xl font-bold tracking-tight">{t('analytics.sites.create')}</h1>
          <p className="text-muted mt-1 text-sm">{t('analytics.sites.form.create_subtitle')}</p>
        </div>

        <div className="grid max-w-6xl items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
          <div className="min-w-0">
            <SiteForm
              form={form}
              trackingModes={trackingModes}
              consentModes={consentModes}
              submitLabel={t('analytics.sites.create')}
              cancelHref={indexUrl}
              onSubmit={() => form.post(route('app.project.sites.store', { project: project!.id }))}
            />
          </div>

          <aside className="space-y-4 lg:sticky lg:top-6">
            <section className="border-line bg-surface rounded-[var(--radius)] border p-5 shadow-[var(--shadow-sm)]">
              <h2 className="text-foreground text-sm font-semibold">{t('analytics.sites.form.steps.title')}</h2>
              <ol className="mt-4 space-y-4">
                {STEPS.map((step, i) => (
                  <li key={step} className="flex gap-3">
                    <span
                      className={`grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-bold ${i === 0 ? 'bg-accent text-accent-foreground' : 'bg-elevated text-muted'}`}
                    >
                      {i + 1}
                    </span>
                    <div className="min-w-0">
                      <p className="text-foreground text-sm font-semibold">{t(`analytics.sites.form.steps.${step}_title`)}</p>
                      <p className="text-muted mt-0.5 text-xs leading-relaxed">{t(`analytics.sites.form.steps.${step}_text`)}</p>
                    </div>
                  </li>
                ))}
              </ol>
            </section>

            <section className="border-line bg-accent-soft/40 flex gap-3 rounded-[var(--radius)] border p-4">
              <ShieldCheck className="text-accent-soft-foreground mt-0.5 h-5 w-5 shrink-0" />
              <p className="text-muted text-xs leading-relaxed">{t('analytics.sites.form.privacy_note')}</p>
            </section>
          </aside>
        </div>
      </div>
    </AppLayout>
  );
}
