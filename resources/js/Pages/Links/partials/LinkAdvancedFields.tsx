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

const inputCls =
  'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white';

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
      <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div className="flex gap-4">
          <div className="flex-1">
            <label htmlFor="status" className="block text-sm font-medium text-slate-700 dark:text-slate-300">{t('links.form.status')}</label>
            <select
              id="status"
              value={data.status}
              onChange={(e) => setField('status', e.target.value)}
              className={inputCls}
            >
              <option value="1">{t('links.status.active')}</option>
              <option value="0">{t('links.status.inactive')}</option>
            </select>
          </div>

          <div className="flex-1">
            <label htmlFor="password" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {t('links.form.password')}
            </label>
            <input
              id="password"
              type="text"
              value={data.password}
              onChange={(e) => setField('password', e.target.value)}
              placeholder={hasPassword ? t('links.form.password_placeholder_existing') : t('links.form.password_placeholder')}
              className={inputCls}
            />
            {errors.password && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.password}</p>}
          </div>

          <div className="flex-1">
            <label htmlFor="expired_at" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {t('links.form.expires_at')}
            </label>
            <input
              id="expired_at"
              type="datetime-local"
              value={data.expired_at}
              onChange={(e) => setField('expired_at', e.target.value)}
              className={inputCls}
            />
            {errors.expired_at && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.expired_at}</p>}
          </div>
        </div>
      </div>

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
        <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
          <div className="mb-4 flex items-center gap-2">
            <Zap className="h-4 w-4 text-slate-500 dark:text-slate-400" />
            <h2 className="text-sm font-semibold text-slate-700 dark:text-slate-300">{t('links.pixels.section')}</h2>
          </div>
          <p className="mb-3 text-xs text-slate-500 dark:text-slate-400">
            {t('links.pixels.description')}
          </p>
          <div className="space-y-2">
            {pixels.map((pixel) => {
              const checked = data.pixel_ids.includes(pixel.id);
              return (
                <label
                  key={pixel.id}
                  className={`flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 transition-colors ${
                    checked
                      ? 'border-indigo-400 bg-indigo-50 dark:border-indigo-600 dark:bg-indigo-900/20'
                      : 'border-slate-200 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={() => togglePixel(pixel.id)}
                    className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                  />
                  <span className="flex-1 text-sm font-medium text-slate-800 dark:text-slate-200">
                    {pixel.name}
                  </span>
                  <span className="text-xs text-slate-400 dark:text-slate-500">
                    {PROVIDER_LABELS[pixel.provider] ?? pixel.provider}
                  </span>
                </label>
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
