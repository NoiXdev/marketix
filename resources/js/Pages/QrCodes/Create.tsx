import { BackLink } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { DEFAULT_STYLE, DYNAMIC_TYPES } from '@/data/qrTypes';
import { useTranslation } from '@/lib/i18n';
import { PageProps, PixelOption } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import QrEditor, { QrFormData } from './partials/QrEditor';

interface Domain { id: string; name: string }
interface AttachUrl {
  id: string; domain_id: string; slug: string; domain_name: string | null; target: string;
  status: number; has_password: boolean; expired_at: string | null;
  targeting_geo: unknown[]; targeting_device: unknown[]; targeting_language: unknown[]; targeting_ab: unknown[];
  pixel_ids: string[];
}

export default function QrCodesCreate({
  defaultStyle, domains, attachUrl, pixels,
}: {
  defaultStyle: Record<string, unknown>;
  domains: Domain[];
  attachUrl: AttachUrl | null;
  pixels: PixelOption[];
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const first = DYNAMIC_TYPES[0];

  const { data, setData, post, processing, errors } = useForm<QrFormData>({
    name:       attachUrl ? `QR – ${attachUrl.slug}` : '',
    type:       attachUrl ? 'link' : first.value,
    is_dynamic: true,
    domain_id:  attachUrl ? attachUrl.domain_id : (domains[0]?.id ?? ''),
    slug:       attachUrl ? attachUrl.slug : '',
    content:    attachUrl ? { url: attachUrl.target } : { ...first.defaultContent },
    style:              { ...DEFAULT_STYLE, ...(defaultStyle as object) } as typeof DEFAULT_STYLE,
    url_id:             attachUrl ? attachUrl.id : undefined,
    status:             attachUrl ? String(attachUrl.status) : '1',
    password:           '',
    expired_at:         attachUrl?.expired_at ?? '',
    targeting_geo:      (attachUrl?.targeting_geo ?? []) as QrFormData['targeting_geo'],
    targeting_device:   (attachUrl?.targeting_device ?? []) as QrFormData['targeting_device'],
    targeting_language: (attachUrl?.targeting_language ?? []) as QrFormData['targeting_language'],
    targeting_ab:       (attachUrl?.targeting_ab ?? []) as QrFormData['targeting_ab'],
    pixel_ids:          attachUrl?.pixel_ids ?? [],
  });

  const title = attachUrl ? t('qr.editor.create_for_link_title') : t('qr.editor.create_title');

  return (
    <AppLayout title={title}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.qrcodes.index', { project: project!.id })}>{t('qr.editor.back')}</BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">{title}</h1>
        </div>
        <QrEditor
          data={data}
          setData={setData}
          errors={errors as Record<string, string>}
          processing={processing}
          submitLabel={t('qr.editor.create_submit')}
          cancelHref={route('app.project.qrcodes.index', { project: project!.id })}
          domains={domains}
          attachLink={attachUrl ? { domainName: attachUrl.domain_name ?? '', slug: attachUrl.slug, target: attachUrl.target } : null}
          pixels={pixels}
          linkHasPassword={attachUrl?.has_password ?? false}
          onSubmit={e => { e.preventDefault(); post(route('app.project.qrcodes.store', { project: project!.id })); }}
        />
      </div>
    </AppLayout>
  );
}
