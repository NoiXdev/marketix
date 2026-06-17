import { DYNAMIC_TYPES, DEFAULT_STYLE, STATIC_TYPES, QrStyle, QrType, buildQrContent, qrTypeTrackable } from '@/data/qrTypes';
import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import QrContentForm from './QrContentForm';
import QrPreview from './QrPreview';
import QrStyleForm from './QrStyleForm';

export interface QrFormData {
  name: string;
  type: QrType;
  is_dynamic: boolean;
  domain_id: number | '';
  slug: string;
  content: Record<string, string>;
  style: QrStyle;
  url_id?: number; // attach mode: back this QR with an existing link instead of creating one
}

interface Domain { id: number; name: string }

interface Props {
  data: QrFormData;
  setData: <K extends keyof QrFormData>(key: K, val: QrFormData[K]) => void;
  errors: Record<string, string>;
  processing: boolean;
  submitLabel: string;
  cancelHref: string;
  onSubmit: FormEventHandler;
  domains: Domain[];
  dynamicUrl?: string; // saved short-link URL (edit page)
  attachLink?: { domainName: string; slug: string; target: string } | null; // attach mode
}

const inp = 'block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white';

export default function QrEditor({
  data, setData, errors, processing, submitLabel, cancelHref, onSubmit, domains, dynamicUrl, attachLink,
}: Props) {
  const [tab, setTab] = useState<'content' | 'style'>('content');

  const typeList = data.is_dynamic ? DYNAMIC_TYPES : STATIC_TYPES;

  function changeType(type: QrType) {
    const config = typeList.find(t => t.value === type);
    setData('type', type);
    if (config) setData('content', { ...config.defaultContent });
  }

  function toggleDynamic(dynamic: boolean) {
    setData('is_dynamic', dynamic);
    const list = dynamic ? DYNAMIC_TYPES : STATIC_TYPES;
    const first = list[0];
    setData('type', first.value);
    setData('content', { ...first.defaultContent });
  }

  // The QR encodes its short link for dynamic types. In attach mode the link
  // is fixed; otherwise prefer the live domain+slug, then the saved URL (edit).
  const selectedDomain = domains.find(d => d.id === data.domain_id);
  const liveDynamicUrl = attachLink
    ? `https://${attachLink.domainName}/${attachLink.slug}`
    : data.is_dynamic && selectedDomain && data.slug
      ? `https://${selectedDomain.name}/${data.slug}`
      : dynamicUrl;

  const qrContent = buildQrContent(data.type, data.is_dynamic, data.content, liveDynamicUrl);

  return (
    <form onSubmit={onSubmit}>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">

        {/* ── Left: configuration ── */}
        <div className="space-y-5">

          {/* Name */}
          <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
              QR Code name
            </label>
            <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
              placeholder="e.g. Website QR" className={inp} />
            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
          </div>

          {attachLink ? (
            /* Attach mode: the backing link is fixed and read-only. */
            <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              <h3 className="mb-1 text-sm font-medium text-slate-700 dark:text-slate-300">Tracking link</h3>
              <p className="mb-3 text-xs text-slate-500 dark:text-slate-400">
                This QR is attached to an existing short link, so every scan is tracked on that link's statistics.
              </p>
              <p className="font-mono text-sm text-indigo-600 dark:text-indigo-400">
                https://{attachLink.domainName}/{attachLink.slug}
              </p>
              <p className="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">
                Destination: {attachLink.target}
              </p>
              {errors.url_id && <p className="mt-2 text-xs text-red-600">{errors.url_id}</p>}
            </div>
          ) : (
            <>
              {/* Static / Dynamic toggle */}
              <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div className="mb-4 flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-sm dark:border-slate-700 dark:bg-slate-800">
                  {[false, true].map(dyn => (
                    <button key={String(dyn)} type="button" onClick={() => toggleDynamic(dyn)}
                      className={`flex-1 rounded-md py-1.5 transition-colors ${
                        data.is_dynamic === dyn
                          ? 'bg-white font-semibold text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                          : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'
                      }`}>
                      {dyn ? 'Dynamic QR' : 'Static QR'}
                    </button>
                  ))}
                </div>

                {/* Type grid */}
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                  {typeList.map(t => {
                    const trackable = qrTypeTrackable(t, data.is_dynamic);
                    return (
                      <button key={t.value} type="button" onClick={() => changeType(t.value)}
                        className={`relative flex flex-col items-center gap-1.5 rounded-lg border px-2 py-3 text-xs transition-colors ${
                          data.type === t.value
                            ? 'border-indigo-500 bg-indigo-50 font-semibold text-indigo-700 dark:border-indigo-400 dark:bg-indigo-900/20 dark:text-indigo-300'
                            : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400'
                        }`}>
                        <span className="text-xl leading-none">{t.icon}</span>
                        {t.label}
                        <span className={`mt-0.5 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium ${
                          trackable
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                            : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                        }`}>
                          {trackable ? 'Trackable' : 'Not tracked'}
                        </span>
                      </button>
                    );
                  })}
                </div>
              </div>

              {/* Backing short link (dynamic only) */}
              {data.is_dynamic && (
                <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                  <h3 className="mb-1 text-sm font-medium text-slate-700 dark:text-slate-300">Tracking link</h3>
                  <p className="mb-4 text-xs text-slate-500 dark:text-slate-400">
                    This QR encodes a short link, so every scan is tracked (location, device, referrer).
                  </p>
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Domain</label>
                      <select value={data.domain_id} onChange={e => setData('domain_id', e.target.value ? Number(e.target.value) : '')}
                        className={inp}>
                        <option value="">Select a domain…</option>
                        {domains.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                      </select>
                      {errors.domain_id && <p className="mt-1 text-xs text-red-600">{errors.domain_id}</p>}
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Slug</label>
                      <input type="text" value={data.slug} onChange={e => setData('slug', e.target.value)}
                        placeholder="promo" className={inp} />
                      {errors.slug && <p className="mt-1 text-xs text-red-600">{errors.slug}</p>}
                    </div>
                  </div>
                  {liveDynamicUrl && (
                    <p className="mt-3 font-mono text-xs text-indigo-600 dark:text-indigo-400">{liveDynamicUrl}</p>
                  )}
                </div>
              )}
            </>
          )}

          {/* Content / Style — attach mode shows style only (destination is the existing link) */}
          {attachLink ? (
            <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
              <div className="border-b border-slate-200 px-5 py-3 dark:border-slate-800">
                <h3 className="text-sm font-medium text-slate-700 dark:text-slate-300">Style</h3>
              </div>
              <div className="p-5">
                <QrStyleForm style={data.style} onChange={s => setData('style', s)} />
              </div>
            </div>
          ) : (
            <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
              <div className="flex border-b border-slate-200 dark:border-slate-800">
                {(['content', 'style'] as const).map(t => (
                  <button key={t} type="button" onClick={() => setTab(t)}
                    className={`flex-1 py-3 text-sm font-medium capitalize transition-colors ${
                      tab === t
                        ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400'
                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'
                    }`}>{t}</button>
                ))}
              </div>
              <div className="p-5">
                {tab === 'content' ? (
                  <QrContentForm type={data.type} content={data.content}
                    onChange={c => setData('content', c)} />
                ) : (
                  <QrStyleForm style={data.style} onChange={s => setData('style', s)} />
                )}
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex items-center gap-3">
            <button type="submit" disabled={processing}
              className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60">
              {processing && <Loader2 className="h-4 w-4 animate-spin" />}
              {submitLabel}
            </button>
            <Link href={cancelHref} className="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">
              Cancel
            </Link>
          </div>
        </div>

        {/* ── Right: sticky preview ── */}
        <div className="lg:sticky lg:top-8 lg:self-start">
          <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 className="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Preview</h2>
            <QrPreview data={qrContent} style={data.style} name={data.name || 'qr-code'} />
          </div>
        </div>
      </div>
    </form>
  );
}
