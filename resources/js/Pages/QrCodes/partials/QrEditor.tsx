import { Badge, Button, Field, FormSection, Input, Select } from '@/Components/ui';
import { DYNAMIC_TYPES, QrStyle, QrType, STATIC_TYPES, buildQrContent, qrTypeTrackable } from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import LinkAdvancedFields, { LinkAdvancedData } from '@/Pages/Links/partials/LinkAdvancedFields';
import { AbVariant, DeviceRule, GeoRule, LanguageRule } from '@/Pages/Links/partials/TargetingSection';
import { UtmParams } from '@/Pages/Links/partials/UtmSection';
import { PixelOption } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import QrContentForm from './QrContentForm';
import QrPreview from './QrPreview';
import QrStyleForm from './QrStyleForm';
import QrTemplatePanel from './QrTemplatePanel';

export interface QrFormData {
  name: string;
  type: QrType;
  is_dynamic: boolean;
  domain_id: string | '';
  slug: string;
  content: Record<string, string>;
  style: QrStyle;
  url_id?: string;
  status: string;
  password: string;
  expired_at: string;
  targeting_geo: GeoRule[];
  targeting_device: DeviceRule[];
  targeting_language: LanguageRule[];
  targeting_ab: AbVariant[];
  utm: UtmParams;
  pixel_ids: string[];
}

interface Domain {
  id: string;
  name: string;
}

interface Props {
  data: QrFormData;
  setData: <K extends keyof QrFormData>(key: K, val: QrFormData[K]) => void;
  errors: Record<string, string>;
  processing: boolean;
  submitLabel: string;
  cancelHref: string;
  onSubmit: FormEventHandler;
  domains: Domain[];
  dynamicUrl?: string;
  attachLink?: { domainName: string; slug: string; target: string } | null;
  pixels: PixelOption[];
  linkHasPassword?: boolean;
  // Presets seed a *new* QR code's style; they are hidden when editing an
  // existing one so an applied preset can never silently restyle it.
  showTemplates?: boolean;
}

export default function QrEditor({
  data,
  setData,
  errors,
  processing,
  submitLabel,
  cancelHref,
  onSubmit,
  domains,
  dynamicUrl,
  attachLink,
  pixels,
  linkHasPassword,
  showTemplates = false,
}: Props) {
  const { t } = useTranslation();
  const [tab, setTab] = useState<'content' | 'style'>('content');
  const [advancedOpen, setAdvancedOpen] = useState(false);

  const typeList = data.is_dynamic ? DYNAMIC_TYPES : STATIC_TYPES;

  function changeType(type: QrType) {
    const config = typeList.find((t) => t.value === type);
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

  const selectedDomain = domains.find((d) => d.id === data.domain_id);
  const liveDynamicUrl = attachLink
    ? `https://${attachLink.domainName}/${attachLink.slug}`
    : data.is_dynamic && selectedDomain && data.slug
      ? `https://${selectedDomain.name}/${data.slug}`
      : dynamicUrl;

  const qrContent = buildQrContent(data.type, data.is_dynamic, data.content, liveDynamicUrl);

  const hasBackingLink = !!attachLink || data.is_dynamic;
  const advancedDefaultUrl = attachLink ? attachLink.target : data.content.url || '';

  const segBtn = (active: boolean) =>
    `flex-1 rounded-md py-1.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
      active ? 'bg-surface text-foreground shadow-[var(--shadow-sm)]' : 'text-muted hover:text-foreground'
    }`;

  return (
    <form onSubmit={onSubmit}>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_340px]">
        {/* ── Left: configuration ── */}
        <div className="space-y-4">
          {/* Name */}
          <FormSection>
            <Field label={t('qr.editor.name')} htmlFor="qr-name" error={errors.name}>
              <Input id="qr-name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder={t('qr.editor.name_placeholder')} />
            </Field>
          </FormSection>

          {attachLink ? (
            <FormSection title={t('qr.editor.tracking_link')} description={t('qr.editor.tracking_link_attached_desc')}>
              <p className="text-accent-soft-foreground font-mono text-sm">
                https://{attachLink.domainName}/{attachLink.slug}
              </p>
              <p className="text-muted truncate text-xs">
                {t('qr.editor.destination')} {attachLink.target}
              </p>
              {errors.url_id && <p className="text-danger-foreground text-xs">{errors.url_id}</p>}
            </FormSection>
          ) : (
            <>
              <FormSection>
                <div className="border-line bg-elevated flex gap-1 rounded-[var(--radius-sm)] border p-1">
                  {[false, true].map((dyn) => (
                    <button key={String(dyn)} type="button" onClick={() => toggleDynamic(dyn)} className={segBtn(data.is_dynamic === dyn)}>
                      {dyn ? t('qr.editor.dynamic') : t('qr.editor.static')}
                    </button>
                  ))}
                </div>
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                  {typeList.map((ty) => {
                    const trackable = qrTypeTrackable(ty, data.is_dynamic);
                    const active = data.type === ty.value;
                    return (
                      <button
                        key={ty.value}
                        type="button"
                        onClick={() => changeType(ty.value)}
                        className={`flex flex-col items-center gap-1.5 rounded-[var(--radius-sm)] border px-2 py-3 text-xs transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${
                          active ? 'border-accent bg-accent-soft text-accent-soft-foreground font-semibold' : 'border-line text-muted hover:border-line-strong'
                        }`}
                      >
                        <span className="text-xl leading-none">{ty.icon}</span>
                        {ty.label}
                        <Badge variant={trackable ? 'success' : 'neutral'}>{trackable ? t('qr.editor.trackable') : t('qr.editor.not_tracked')}</Badge>
                      </button>
                    );
                  })}
                </div>
              </FormSection>

              {data.is_dynamic && (
                <FormSection title={t('qr.editor.tracking_link')} description={t('qr.editor.tracking_link_desc')}>
                  <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Field label={t('qr.editor.domain')} htmlFor="qr-domain" error={errors.domain_id}>
                      <Select id="qr-domain" value={data.domain_id} onChange={(e) => setData('domain_id', e.target.value)}>
                        <option value="">{t('qr.editor.domain_select')}</option>
                        {domains.map((d) => (
                          <option key={d.id} value={d.id}>
                            {d.name}
                          </option>
                        ))}
                      </Select>
                    </Field>
                    <Field label={t('qr.editor.slug')} htmlFor="qr-slug" error={errors.slug}>
                      <Input id="qr-slug" type="text" value={data.slug} onChange={(e) => setData('slug', e.target.value)} placeholder={t('qr.editor.slug_placeholder')} />
                    </Field>
                  </div>
                  {liveDynamicUrl && <p className="text-accent-soft-foreground font-mono text-xs">{liveDynamicUrl}</p>}
                </FormSection>
              )}
            </>
          )}

          {hasBackingLink && (
            <div className="border-line bg-surface rounded-[var(--radius)] border">
              <button type="button" onClick={() => setAdvancedOpen((o) => !o)} className="flex w-full items-center justify-between px-5 py-4 text-left">
                <span className="text-foreground text-sm font-semibold">{t('qr.editor.advanced')}</span>
                <ChevronDown className={`text-subtle h-4 w-4 transition-transform ${advancedOpen ? 'rotate-180' : ''}`} />
              </button>
              {advancedOpen && (
                <div className="border-line border-t p-5">
                  {attachLink && (
                    <p className="bg-warning-soft text-warning-foreground mb-4 rounded-[var(--radius-sm)] px-3 py-2 text-xs">{t('qr.editor.advanced_shared_warning')}</p>
                  )}
                  <LinkAdvancedFields
                    data={data}
                    setField={setData as <K extends keyof LinkAdvancedData>(key: K, value: LinkAdvancedData[K]) => void}
                    errors={errors}
                    pixels={pixels}
                    defaultUrl={advancedDefaultUrl}
                    hasPassword={linkHasPassword}
                  />
                </div>
              )}
            </div>
          )}

          {/* Content / Style */}
          {attachLink ? (
            <div className="border-line bg-surface rounded-[var(--radius)] border">
              <div className="border-line border-b px-5 py-3">
                <h3 className="text-foreground text-sm font-semibold">{t('qr.editor.style')}</h3>
              </div>
              <div className="p-5">
                <QrStyleForm style={data.style} onChange={(s) => setData('style', s)} />
                {showTemplates && <QrTemplatePanel style={data.style} onApply={(s) => setData('style', s)} />}
              </div>
            </div>
          ) : (
            <div className="border-line bg-surface rounded-[var(--radius)] border">
              <div className="border-line flex border-b">
                {(
                  [
                    ['content', t('qr.editor.tab_content')],
                    ['style', t('qr.editor.tab_style')],
                  ] as const
                ).map(([key, label]) => (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setTab(key)}
                    className={`-mb-px flex-1 border-b-2 py-3 text-sm font-semibold transition-colors ${
                      tab === key ? 'border-accent text-accent-soft-foreground' : 'text-muted hover:text-foreground border-transparent'
                    }`}
                  >
                    {label}
                  </button>
                ))}
              </div>
              <div className="p-5">
                {tab === 'content' ? (
                  <QrContentForm type={data.type} content={data.content} onChange={(c) => setData('content', c)} />
                ) : (
                  <>
                    <QrStyleForm style={data.style} onChange={(s) => setData('style', s)} />
                    {showTemplates && <QrTemplatePanel style={data.style} onApply={(s) => setData('style', s)} />}
                  </>
                )}
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex items-center gap-3">
            <Button type="submit" loading={processing}>
              {submitLabel}
            </Button>
            <Link href={cancelHref} className="text-muted hover:text-foreground text-sm transition-colors">
              {t('common.actions.cancel')}
            </Link>
          </div>
        </div>

        {/* ── Right: sticky preview ── */}
        <div className="lg:sticky lg:top-8 lg:self-start">
          <FormSection title={t('qr.editor.preview')}>
            <QrPreview data={qrContent} style={data.style} name={data.name || 'qr-code'} />
          </FormSection>
        </div>
      </div>
    </form>
  );
}
