import CodeSnippet from '@/Components/CodeSnippet';
import { Field, FormSection, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import ToggleRow from '@/Pages/Sites/partials/ToggleRow';

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
      <div className="space-y-1">
        <ToggleRow
          checked={values.track_outbound_links}
          onChange={(checked) => onChange({ track_outbound_links: checked })}
          title={t('analytics.sites.measurement.outbound_links')}
          text={t('analytics.sites.measurement.outbound_links_hint')}
        />
        <ToggleRow
          checked={values.track_file_downloads}
          onChange={(checked) => onChange({ track_file_downloads: checked })}
          title={t('analytics.sites.measurement.file_downloads')}
          text={t('analytics.sites.measurement.file_downloads_hint')}
        />
      </div>

      <Field label={t('analytics.sites.measurement.search_params')} htmlFor="site_search_params" hint={t('analytics.sites.measurement.search_params_hint')} error={error}>
        <Input
          id="site_search_params"
          value={values.site_search_params}
          placeholder={t('analytics.sites.measurement.search_params_placeholder')}
          onChange={(e) => onChange({ site_search_params: e.target.value })}
        />
      </Field>

      <div className="border-line border-t pt-4">
        <p className="text-foreground text-sm font-semibold">{t('analytics.sites.measurement.not_found_title')}</p>
        <p className="text-muted mt-0.5 mb-2 text-xs">{t('analytics.sites.measurement.not_found_hint')}</p>
        <CodeSnippet code="marketix('404');" />
      </div>
    </FormSection>
  );
}
