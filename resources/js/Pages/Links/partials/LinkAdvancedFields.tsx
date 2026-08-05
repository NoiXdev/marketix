import { Checkbox, Field, FormSection, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PixelOption } from '@/types';
import { Zap } from 'lucide-react';
import {
  AbTesting,
  AbVariant,
  DeviceRule,
  DeviceTargeting,
  GeoRule,
  GeoTargeting,
  LanguageRule,
  LanguageTargeting,
} from './TargetingSection';

export interface LinkAdvancedData {
  status: string;
  password: string;
  expired_at: string;
  targeting_geo: GeoRule[];
  targeting_device: DeviceRule[];
  targeting_language: LanguageRule[];
  targeting_ab: AbVariant[];
  pixel_ids: string[];
}

interface LinkAdvancedFieldsProps {
  data: LinkAdvancedData;
  setField: <K extends keyof LinkAdvancedData>(key: K, value: LinkAdvancedData[K]) => void;
  errors: Record<string, string | undefined>;
  pixels: PixelOption[];
  defaultUrl: string;
  hasPassword?: boolean;
}

const PROVIDER_LABELS: Record<string, string> = {
  google_tag_manager: 'Google Tag Manager',
  google_analytics:   'Google Analytics',
  facebook:           'Facebook',
  google_ads:         'Google Ads',
  linkedin:           'LinkedIn',
  twitter:            'Twitter',
  adroll:             'AdRoll',
  quora:              'Quora',
  pinterest:          'Pinterest',
  bing:               'Bing',
  snapchat:           'Snapchat',
  reddit:             'Reddit',
  tiktok:             'TikTok',
};

export default function LinkAdvancedFields({
  data, setField, errors, pixels, defaultUrl, hasPassword,
}: LinkAdvancedFieldsProps) {
  const { t } = useTranslation();

  function togglePixel(id: string) {
    const ids = data.pixel_ids.includes(id)
      ? data.pixel_ids.filter((x) => x !== id)
      : [...data.pixel_ids, id];
    setField('pixel_ids', ids);
  }

  return (
    <div className="space-y-5">
      {/* ── Status / Password / Expiry ── */}
      <FormSection>
        <div className="flex gap-4">
          <div className="flex-1">
            <Field label={t('links.form.status')} htmlFor="status">
              <Select
                id="status"
                value={data.status}
                onChange={(e) => setField('status', e.target.value)}
              >
                <option value="1">{t('links.status.active')}</option>
                <option value="0">{t('links.status.inactive')}</option>
              </Select>
            </Field>
          </div>

          <div className="flex-1">
            <Field label={t('links.form.password')} htmlFor="password" error={errors.password}>
              <Input
                id="password"
                type="text"
                value={data.password}
                onChange={(e) => setField('password', e.target.value)}
                placeholder={hasPassword ? t('links.form.password_placeholder_existing') : t('links.form.password_placeholder')}
              />
            </Field>
          </div>

          <div className="flex-1">
            <Field label={t('links.form.expires_at')} htmlFor="expired_at" error={errors.expired_at}>
              <Input
                id="expired_at"
                type="datetime-local"
                value={data.expired_at}
                onChange={(e) => setField('expired_at', e.target.value)}
              />
            </Field>
          </div>
        </div>
      </FormSection>

      {/* ── Geo Targeting ── */}
      <GeoTargeting
        rules={data.targeting_geo}
        onChange={(rules) => setField('targeting_geo', rules)}
      />

      {/* ── Device Targeting ── */}
      <DeviceTargeting
        rules={data.targeting_device}
        onChange={(rules) => setField('targeting_device', rules)}
      />

      {/* ── Language Targeting ── */}
      <LanguageTargeting
        rules={data.targeting_language}
        onChange={(rules) => setField('targeting_language', rules)}
      />

      {/* ── A/B Testing ── */}
      <AbTesting
        defaultUrl={defaultUrl}
        variants={data.targeting_ab}
        onChange={(variants) => setField('targeting_ab', variants)}
      />

      {/* ── Pixels ── */}
      {pixels.length > 0 && (
        <FormSection
          title={
            <span className="flex items-center gap-2">
              <Zap className="h-4 w-4 text-muted" />
              {t('links.pixels.section')}
            </span>
          }
          description={t('links.pixels.description')}
        >
          <div className="space-y-2">
            {pixels.map((pixel) => {
              const checked = data.pixel_ids.includes(pixel.id);
              return (
                <label
                  key={pixel.id}
                  className={`flex cursor-pointer items-center gap-3 rounded-[var(--radius-sm)] border px-3 py-2.5 transition-colors ${
                    checked
                      ? 'border-accent bg-accent-soft'
                      : 'border-line hover:border-line-strong'
                  }`}
                >
                  <Checkbox checked={checked} onChange={() => togglePixel(pixel.id)} />
                  <span className="flex-1 text-sm font-medium text-foreground">
                    {pixel.name}
                  </span>
                  <span className="text-xs text-subtle">
                    {PROVIDER_LABELS[pixel.provider] ?? pixel.provider}
                  </span>
                </label>
              );
            })}
          </div>
        </FormSection>
      )}
    </div>
  );
}
