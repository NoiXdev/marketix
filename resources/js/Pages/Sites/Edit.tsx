import CodeSnippet from '@/Components/CodeSnippet';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Button, Checkbox, ErrorSummary, Field, FormSection, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import EnhancedMeasurementSection from '@/Pages/Sites/partials/EnhancedMeasurementSection';
import { PageProps, Site } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { value: string; label: string };

export default function SitesEdit({
  site,
  trackingModes,
  consentModes,
  snippet,
}: {
  site: Site;
  trackingModes: Option[];
  consentModes: Option[];
  snippet: string;
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const { data, setData, put, processing, errors, transform } = useForm({
    name: site.name,
    domain: site.domain,
    tracking_mode: site.tracking_mode,
    consent_mode: site.consent_mode,
    consent_signal: site.consent_signal ?? '',
    respect_dnt: site.respect_dnt ?? false,
    track_outbound_links: site.track_outbound_links ?? true,
    track_file_downloads: site.track_file_downloads ?? true,
    site_search_params: site.site_search_params ?? '',
    retention_days: site.retention_days ?? ('' as string | number),
  });

  transform((data) => ({
    ...data,
    retention_days: data.retention_days === '' ? null : data.retention_days,
  }));

  function submit(e: FormEvent) {
    e.preventDefault();
    put(route('app.project.sites.update', { project: project!.id, site: site.id }));
  }

  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  return (
    <AppLayout title={t('analytics.sites.edit')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.sites.index', { project: project!.id })}>{t('analytics.sites.back')}</BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">{t('analytics.sites.edit')}</h1>
        </div>

        <div className="max-w-2xl">
          <div className="mb-6 rounded-[var(--radius)] border border-line bg-surface p-4">
            <p className="mb-2 text-sm font-medium text-foreground">{t('analytics.sites.snippet_title')}</p>
            <CodeSnippet code={snippet} language="html" wrap className="w-full" />
            <p className="mt-2 text-xs text-muted">{t('analytics.sites.snippet_hint', { domain: site.domain })}</p>
          </div>

          <form onSubmit={submit} className="space-y-5">
            <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

            <FormSection>
              <Field label={t('analytics.sites.form.name')} htmlFor="name" error={errors.name}>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
              </Field>

              <Field label={t('analytics.sites.form.domain')} htmlFor="domain" error={errors.domain}>
                <Input id="domain" value={data.domain} onChange={(e) => setData('domain', e.target.value)} />
              </Field>

              <Field label={t('analytics.sites.form.tracking_mode')} htmlFor="tracking_mode">
                <Select id="tracking_mode" value={data.tracking_mode} onChange={(e) => setData('tracking_mode', e.target.value)}>
                  {trackingModes.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </Select>
              </Field>

              <Field label={t('analytics.sites.form.consent_mode')} htmlFor="consent_mode">
                <Select id="consent_mode" value={data.consent_mode} onChange={(e) => setData('consent_mode', e.target.value)}>
                  {consentModes.map((o) => (
                    <option key={o.value} value={o.value}>
                      {o.label}
                    </option>
                  ))}
                </Select>
              </Field>

              <Field
                label={t('analytics.sites.form.consent_signal')}
                htmlFor="consent_signal"
                hint={t('analytics.sites.form.consent_signal_hint')}
              >
                <Input id="consent_signal" value={data.consent_signal} onChange={(e) => setData('consent_signal', e.target.value)} />
              </Field>

              <Field
                label={t('analytics.sites.form.retention_days')}
                htmlFor="retention_days"
                hint={t('analytics.sites.form.retention_days_hint')}
                error={errors.retention_days}
              >
                <Input
                  id="retention_days"
                  type="number"
                  min={1}
                  value={data.retention_days}
                  onChange={(e) => setData('retention_days', e.target.value)}
                />
              </Field>

              <label className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox checked={data.respect_dnt} onChange={(e) => setData('respect_dnt', e.target.checked)} />
                {t('analytics.sites.form.respect_dnt')}
              </label>
            </FormSection>

            <EnhancedMeasurementSection values={data} onChange={(patch) => setData({ ...data, ...patch })} error={errors.site_search_params} />

            <Button type="submit" loading={processing}>
              {t('common.actions.save')}
            </Button>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
