import { Button, ErrorSummary, Field, FormSection, Input, LinkButton } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import ChoiceCards from '@/Pages/Sites/partials/ChoiceCards';
import EnhancedMeasurementSection from '@/Pages/Sites/partials/EnhancedMeasurementSection';
import ToggleRow from '@/Pages/Sites/partials/ToggleRow';
import { InertiaFormProps } from '@inertiajs/react';
import { Globe } from 'lucide-react';
import { FormEvent } from 'react';

export type Option = { value: string; label: string };

export type SiteFormData = {
  name: string;
  domain: string;
  tracking_mode: string;
  consent_mode: string;
  consent_signal: string;
  respect_dnt: boolean;
  track_outbound_links: boolean;
  track_file_downloads: boolean;
  site_search_params: string;
  retention_days: string | number;
};

const THIRD_PARTY_SIGNAL = 'third_party_signal';

export default function SiteForm({
  form,
  trackingModes,
  consentModes,
  submitLabel,
  cancelHref,
  onSubmit,
}: {
  form: InertiaFormProps<SiteFormData>;
  trackingModes: Option[];
  consentModes: Option[];
  submitLabel: string;
  cancelHref: string;
  onSubmit: () => void;
}) {
  const { t } = useTranslation();
  const { data, setData, errors, processing, isDirty } = form;
  const errorMessages = Object.values(errors).filter(Boolean) as string[];
  const label = (group: string, value: string, part: string) => t(`analytics.sites.form.${group}.${value}.${part}`);

  function submit(event: FormEvent) {
    event.preventDefault();
    onSubmit();
  }

  return (
    <form onSubmit={submit} className="space-y-5">
      <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

      <FormSection title={t('analytics.sites.form.sections.website_title')} description={t('analytics.sites.form.sections.website_text')}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t('analytics.sites.form.name')} htmlFor="name" error={errors.name}>
            <Input id="name" value={data.name} placeholder={t('analytics.sites.form.name_placeholder')} onChange={(e) => setData('name', e.target.value)} />
          </Field>
          <Field label={t('analytics.sites.form.domain')} htmlFor="domain" error={errors.domain}>
            <div className="relative">
              <Globe className="text-subtle pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
              <Input
                id="domain"
                className="pl-9"
                value={data.domain}
                placeholder={t('analytics.sites.form.domain_placeholder')}
                onChange={(e) => setData('domain', e.target.value)}
              />
            </div>
          </Field>
        </div>
      </FormSection>

      <FormSection title={t('analytics.sites.form.sections.privacy_title')} description={t('analytics.sites.form.sections.privacy_text')}>
        <ChoiceCards
          legend={t('analytics.sites.form.tracking_mode')}
          name="tracking_mode"
          value={data.tracking_mode}
          onChange={(value) => setData('tracking_mode', value)}
          options={trackingModes.map((mode) => ({
            value: mode.value,
            title: label('modes', mode.value, 'title'),
            text: label('modes', mode.value, 'text'),
            badge: mode.value === 'cookieless' ? t('analytics.sites.form.recommended') : undefined,
          }))}
        />

        <ChoiceCards
          legend={t('analytics.sites.form.consent_mode')}
          name="consent_mode"
          value={data.consent_mode}
          onChange={(value) => setData('consent_mode', value)}
          options={consentModes.map((mode) => ({
            value: mode.value,
            title: label('consent', mode.value, 'title'),
            text: label('consent', mode.value, 'text'),
          }))}
        />

        {data.consent_mode === THIRD_PARTY_SIGNAL && (
          <Field label={t('analytics.sites.form.consent_signal')} htmlFor="consent_signal" hint={t('analytics.sites.form.consent_signal_hint')} error={errors.consent_signal}>
            <Input
              id="consent_signal"
              className="font-mono"
              value={data.consent_signal}
              placeholder={t('analytics.sites.form.consent_signal_placeholder')}
              onChange={(e) => setData('consent_signal', e.target.value)}
            />
          </Field>
        )}

        <div className="border-line border-t pt-3">
          <ToggleRow
            checked={data.respect_dnt}
            onChange={(checked) => setData('respect_dnt', checked)}
            title={t('analytics.sites.form.respect_dnt')}
            text={t('analytics.sites.form.respect_dnt_hint')}
          />
        </div>
      </FormSection>

      <EnhancedMeasurementSection values={data} onChange={(patch) => setData((current) => ({ ...current, ...patch }))} error={errors.site_search_params} />

      <FormSection title={t('analytics.sites.form.sections.retention_title')} description={t('analytics.sites.form.retention_days_hint')}>
        <Field label={t('analytics.sites.form.retention_days')} htmlFor="retention_days" error={errors.retention_days}>
          <div className="relative max-w-xs">
            <Input
              id="retention_days"
              type="number"
              min={1}
              className="pr-14"
              value={data.retention_days}
              placeholder={t('analytics.sites.form.retention_days_placeholder')}
              onChange={(e) => setData('retention_days', e.target.value)}
            />
            <span className="text-muted pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm">{t('analytics.sites.form.days')}</span>
          </div>
        </Field>
      </FormSection>

      <div className="border-line bg-surface/90 sticky bottom-4 z-10 flex items-center justify-end gap-3 rounded-[var(--radius)] border px-4 py-3 shadow-[var(--shadow)] backdrop-blur">
        {isDirty && <span className="text-muted mr-auto text-xs">{t('analytics.sites.form.unsaved')}</span>}
        <LinkButton variant="ghost" href={cancelHref}>
          {t('common.actions.cancel')}
        </LinkButton>
        <Button type="submit" loading={processing}>
          {submitLabel}
        </Button>
      </div>
    </form>
  );
}
