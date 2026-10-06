import CodeSnippet from '@/Components/CodeSnippet';
import { Checkbox, Field, FormSection, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';

export type EnhancedMeasurement = {
  track_outbound_links: boolean;
  track_file_downloads: boolean;
  site_search_params: string;
};

export default function EnhancedMeasurementSection({
  values,
  onChange,
  error,
}: {
  values: EnhancedMeasurement;
  onChange: (patch: Partial<EnhancedMeasurement>) => void;
  error?: string;
}) {
  const { t } = useTranslation();

  return (
    <FormSection title={t('analytics.sites.measurement.title')} description={t('analytics.sites.measurement.description')}>
      <label className="flex items-center gap-2 text-sm text-foreground">
        <Checkbox checked={values.track_outbound_links} onChange={(e) => onChange({ track_outbound_links: e.target.checked })} />
        {t('analytics.sites.measurement.outbound_links')}
      </label>

      <label className="flex items-center gap-2 text-sm text-foreground">
        <Checkbox checked={values.track_file_downloads} onChange={(e) => onChange({ track_file_downloads: e.target.checked })} />
        {t('analytics.sites.measurement.file_downloads')}
      </label>

      <Field
        label={t('analytics.sites.measurement.search_params')}
        htmlFor="site_search_params"
        hint={t('analytics.sites.measurement.search_params_hint')}
        error={error}
      >
        <Input
          id="site_search_params"
          value={values.site_search_params}
          placeholder={t('analytics.sites.measurement.search_params_placeholder')}
          onChange={(e) => onChange({ site_search_params: e.target.value })}
        />
      </Field>

      <div>
        <p className="mb-2 text-xs text-muted">{t('analytics.sites.measurement.not_found_hint')}</p>
        <CodeSnippet code="marketix('404');" />
      </div>
    </FormSection>
  );
}
