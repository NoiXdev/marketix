import { Domain, PixelOption } from '@/types';
import { Link } from '@inertiajs/react';
import { Loader2, Zap } from 'lucide-react';
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

export default function LinkForm({
  data, setData, errors, processing,
  submitLabel, cancelHref, domains, pixels, onSubmit, hasPassword,
}: LinkFormProps) {
  function togglePixel(id: string) {
    const ids = data.pixel_ids.includes(id)
      ? data.pixel_ids.filter((x) => x !== id)
      : [...data.pixel_ids, id];
    setData('pixel_ids', ids);
  }
  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  return (
    <form onSubmit={onSubmit} className="space-y-5">

      {/* ── Validation summary ── */}
      {/* Surfaces every error, including nested targeting keys (e.g.
          targeting_geo.0.country) that have no inline field to display them —
          otherwise a failed save looks like nothing happened. */}
      {errorMessages.length > 0 && (
        <div className="rounded-xl border border-red-300 bg-red-50 p-4 dark:border-red-800/60 dark:bg-red-900/20">
          <p className="text-sm font-semibold text-red-700 dark:text-red-300">
            Couldn’t save — please fix the following:
          </p>
          <ul className="mt-2 list-disc space-y-1 pl-5 text-xs text-red-600 dark:text-red-400">
            {errorMessages.map((msg, i) => (
              <li key={i}>{msg}</li>
            ))}
          </ul>
        </div>
      )}

      {/* ── Domain + Slug ── */}
      <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <h2 className="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Link settings</h2>
        <div className="space-y-4">

          <div className="flex gap-3">
            <div className="w-48 shrink-0">
              <label htmlFor="domain_id" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Domain <span className="text-red-500">*</span>
              </label>
              <select
                id="domain_id"
                value={data.domain_id}
                onChange={(e) => setData('domain_id', e.target.value)}
                className={inputCls}
              >
                <option value="">Select domain</option>
                {domains.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
              {errors.domain_id && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.domain_id}</p>}
            </div>

            <div className="flex-1">
              <label htmlFor="slug" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Slug <span className="text-red-500">*</span>
              </label>
              <input
                id="slug"
                type="text"
                value={data.slug}
                onChange={(e) => setData('slug', e.target.value)}
                placeholder="my-link"
                className={inputCls}
              />
              {errors.slug && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.slug}</p>}
            </div>
          </div>

          <div>
            <label htmlFor="url" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
              Destination URL <span className="text-red-500">*</span>
            </label>
            <input
              id="url"
              type="url"
              value={data.url}
              onChange={(e) => setData('url', e.target.value)}
              placeholder="https://example.com/my-long-url"
              className={inputCls}
            />
            {errors.url && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.url}</p>}
          </div>

          <div className="flex gap-4">
            <div className="flex-1">
              <label htmlFor="status" className="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
              <select
                id="status"
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
                className={inputCls}
              >
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>

            <div className="flex-1">
              <label htmlFor="password" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Password
              </label>
              <input
                id="password"
                type="text"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
                placeholder={hasPassword ? 'Leave blank to keep current password' : 'optional'}
                className={inputCls}
              />
              {errors.password && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.password}</p>}
            </div>

            <div className="flex-1">
              <label htmlFor="expired_at" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Expires at
              </label>
              <input
                id="expired_at"
                type="datetime-local"
                value={data.expired_at}
                onChange={(e) => setData('expired_at', e.target.value)}
                className={inputCls}
              />
              {errors.expired_at && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.expired_at}</p>}
            </div>
          </div>
        </div>
      </div>

      {/* ── Geo Targeting ── */}
      <GeoTargeting
        rules={data.targeting_geo}
        onChange={(rules) => setData('targeting_geo', rules)}
      />

      {/* ── Device Targeting ── */}
      <DeviceTargeting
        rules={data.targeting_device}
        onChange={(rules) => setData('targeting_device', rules)}
      />

      {/* ── Language Targeting ── */}
      <LanguageTargeting
        rules={data.targeting_language}
        onChange={(rules) => setData('targeting_language', rules)}
      />

      {/* ── A/B Testing ── */}
      <AbTesting
        defaultUrl={data.url}
        variants={data.targeting_ab}
        onChange={(variants) => setData('targeting_ab', variants)}
      />

      {/* ── Pixels ── */}
      {pixels.length > 0 && (
        <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
          <div className="mb-4 flex items-center gap-2">
            <Zap className="h-4 w-4 text-slate-500 dark:text-slate-400" />
            <h2 className="text-sm font-semibold text-slate-700 dark:text-slate-300">Tracking Pixels</h2>
          </div>
          <p className="mb-3 text-xs text-slate-500 dark:text-slate-400">
            Select pixels to fire before redirecting. The redirect will be delayed by 2 seconds to allow pixels to load.
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

      {/* ── Actions ── */}
      <div className="flex items-center gap-3">
        <button
          type="submit"
          disabled={processing}
          className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
        >
          {processing && <Loader2 className="h-4 w-4 animate-spin" />}
          {submitLabel}
        </button>
        <Link
          href={cancelHref}
          className="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
        >
          Cancel
        </Link>
      </div>
    </form>
  );
}
