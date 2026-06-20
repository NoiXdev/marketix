import LinkAdvancedFields, { LinkAdvancedData } from './LinkAdvancedFields';
import { AbVariant, DeviceRule, GeoRule, LanguageRule } from './TargetingSection';
import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
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

const inputCls =
  'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white';

export default function LinkForm({
  data, setData, errors, processing,
  submitLabel, cancelHref, domains, pixels, onSubmit, hasPassword,
}: LinkFormProps) {
  const { t } = useTranslation();

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
            {t('links.form.save_error_title')}
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
        <h2 className="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">{t('links.form.section_settings')}</h2>
        <div className="space-y-4">

          <div className="flex gap-3">
            <div className="w-48 shrink-0">
              <label htmlFor="domain_id" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                {t('links.form.domain')} <span className="text-red-500">*</span>
              </label>
              <select
                id="domain_id"
                value={data.domain_id}
                onChange={(e) => setData('domain_id', e.target.value)}
                className={inputCls}
              >
                <option value="">{t('links.form.domain_select')}</option>
                {domains.map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </select>
              {errors.domain_id && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.domain_id}</p>}
            </div>

            <div className="flex-1">
              <label htmlFor="slug" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                {t('links.form.slug')} <span className="text-red-500">*</span>
              </label>
              <input
                id="slug"
                type="text"
                value={data.slug}
                onChange={(e) => setData('slug', e.target.value)}
                placeholder={t('links.form.slug_placeholder')}
                className={inputCls}
              />
              {errors.slug && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.slug}</p>}
            </div>
          </div>

          <div>
            <label htmlFor="url" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
              {t('links.form.target')} <span className="text-red-500">*</span>
            </label>
            <input
              id="url"
              type="url"
              value={data.url}
              onChange={(e) => setData('url', e.target.value)}
              placeholder={t('links.form.target_placeholder')}
              className={inputCls}
            />
            {errors.url && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.url}</p>}
          </div>

        </div>
      </div>

      <LinkAdvancedFields
        data={data}
        setField={setData as <K extends keyof LinkAdvancedData>(key: K, value: LinkAdvancedData[K]) => void}
        errors={errors}
        pixels={pixels}
        defaultUrl={data.url}
        hasPassword={hasPassword}
      />

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
          {t('common.actions.cancel')}
        </Link>
      </div>
    </form>
  );
}
