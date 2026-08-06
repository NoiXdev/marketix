import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Button, Checkbox, ErrorSummary, Field, FormSection, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { value: string; label: string };

export default function SitesCreate({ trackingModes, consentModes }: { trackingModes: Option[]; consentModes: Option[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const { data, setData, post, processing, errors, transform } = useForm({
    name: '',
    domain: '',
    tracking_mode: 'cookieless',
    consent_mode: 'immediate',
    consent_signal: '',
    respect_dnt: false,
    retention_days: '' as string | number,
  });

  transform((data) => ({
    ...data,
    retention_days: data.retention_days === '' ? null : data.retention_days,
  }));

  function submit(e: FormEvent) {
    e.preventDefault();
    post(route('app.project.sites.store', { project: project!.id }));
  }

  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  return (
    <AppLayout title={t('analytics.sites.create')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.sites.index', { project: project!.id })}>{t('analytics.sites.back')}</BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">{t('analytics.sites.create')}</h1>
        </div>

        <div className="max-w-2xl">
          <form onSubmit={submit} className="space-y-5">
            <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

            <FormSection>
              <Field label={t('analytics.sites.form.name')} htmlFor="name" error={errors.name}>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
              </Field>

              <Field label={t('analytics.sites.form.domain')} htmlFor="domain" error={errors.domain}>
                <Input
                  id="domain"
                  value={data.domain}
                  onChange={(e) => setData('domain', e.target.value)}
                  placeholder={t('analytics.sites.form.domain_placeholder')}
                />
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
                <Input
                  id="consent_signal"
                  value={data.consent_signal}
                  onChange={(e) => setData('consent_signal', e.target.value)}
                  placeholder={t('analytics.sites.form.consent_signal_placeholder')}
                />
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

            <Button type="submit" loading={processing}>
              {t('analytics.sites.create')}
            </Button>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
