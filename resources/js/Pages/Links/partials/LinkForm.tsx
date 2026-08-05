import LinkAdvancedFields, { LinkAdvancedData } from './LinkAdvancedFields';
import { AbVariant, DeviceRule, GeoRule, LanguageRule } from './TargetingSection';
import { Button, ErrorSummary, Field, FormSection, Input, Select } from '@/Components/ui';
import { Link } from '@inertiajs/react';
import { useTranslation } from '@/lib/i18n';
import { Domain, PixelOption } from '@/types';

export interface LinkFormData {
  domain_id: string;
  slug: string;
  url: string;
  type: string;
  status: string;
  password: string;
  expired_at: string;
  targeting_geo: GeoRule[];
  targeting_device: DeviceRule[];
  targeting_language: LanguageRule[];
  targeting_ab: AbVariant[];
  pixel_ids: string[];
}

interface Errors {
  domain_id?: string;
  slug?: string;
  url?: string;
  status?: string;
  password?: string;
  expired_at?: string;
  [key: string]: string | undefined;
}

interface LinkFormProps {
  data: LinkFormData;
  setData: <K extends keyof LinkFormData>(key: K, value: LinkFormData[K]) => void;
  errors: Errors;
  processing: boolean;
  submitLabel: string;
  cancelHref: string;
  domains: Pick<Domain, 'id' | 'name'>[];
  pixels: PixelOption[];
  hasPassword?: boolean;
  onSubmit: React.FormEventHandler;
}

export default function LinkForm({
  data, setData, errors, processing,
  submitLabel, cancelHref, domains, pixels, onSubmit, hasPassword,
}: LinkFormProps) {
  const { t } = useTranslation();

  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  return (
    <form onSubmit={onSubmit} className="space-y-5">
      {/* Validation summary — surfaces nested targeting errors that have no inline field. */}
      <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

      <FormSection title={t('links.form.section_settings')}>
        <div className="flex gap-3">
          <div className="w-48 shrink-0">
            <Field label={<>{t('links.form.domain')} <span className="text-danger-foreground">*</span></>} htmlFor="domain_id" error={errors.domain_id}>
              <Select id="domain_id" value={data.domain_id} onChange={(e) => setData('domain_id', e.target.value)}>
                <option value="">{t('links.form.domain_select')}</option>
                {domains.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </Select>
            </Field>
          </div>
          <div className="flex-1">
            <Field label={<>{t('links.form.slug')} <span className="text-danger-foreground">*</span></>} htmlFor="slug" error={errors.slug}>
              <Input id="slug" type="text" value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder={t('links.form.slug_placeholder')} />
            </Field>
          </div>
        </div>

        <Field label={<>{t('links.form.target')} <span className="text-danger-foreground">*</span></>} htmlFor="url" error={errors.url}>
          <Input id="url" type="url" value={data.url} onChange={(e) => setData('url', e.target.value)} placeholder={t('links.form.target_placeholder')} />
        </Field>
      </FormSection>

      <LinkAdvancedFields
        data={data}
        setField={setData as <K extends keyof LinkAdvancedData>(key: K, value: LinkAdvancedData[K]) => void}
        errors={errors}
        pixels={pixels}
        defaultUrl={data.url}
        hasPassword={hasPassword}
      />

      <div className="flex items-center gap-3">
        <Button type="submit" loading={processing}>{submitLabel}</Button>
        <Link href={cancelHref} className="text-sm text-muted transition-colors hover:text-foreground">
          {t('common.actions.cancel')}
        </Link>
      </div>
    </form>
  );
}
