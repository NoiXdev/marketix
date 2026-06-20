import QrVersionsPanel, { QrVersionEntry } from '@/Components/QrVersionsPanel';
import AppLayout from '@/Layouts/AppLayout';
import { QrStyle, QrType } from '@/data/qrTypes';
import { confirmAction } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { isRiskyEdit, QrEditState } from '@/lib/qrRisk';
import { PageProps, PixelOption } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FormEvent } from 'react';
import QrEditor, { QrFormData } from './partials/QrEditor';

interface Domain { id: string; name: string }

interface QrData {
  id: string;
  name: string;
  type: QrType;
  is_dynamic: boolean;
  content: Record<string, string>;
  style: QrStyle;
  domain_id: string | null;
  slug: string | null;
  dynamic_url: string | null;
  status: number | null;
  has_password: boolean;
  expired_at: string | null;
  targeting_geo: unknown[];
  targeting_device: unknown[];
  targeting_language: unknown[];
  targeting_ab: unknown[];
  pixel_ids: string[];
}

export default function QrCodesEdit({ qrCode, domains, versions, pixels }: { qrCode: QrData; domains: Domain[]; versions?: QrVersionEntry[]; pixels: PixelOption[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const { data, setData, put, processing, errors } = useForm<QrFormData>({
    name:       qrCode.name,
    type:       qrCode.type,
    is_dynamic: qrCode.is_dynamic,
    domain_id:  qrCode.domain_id ?? (domains[0]?.id ?? ''),
    slug:       qrCode.slug ?? '',
    content:            qrCode.content,
    style:              qrCode.style,
    status:             qrCode.status != null ? String(qrCode.status) : '1',
    password:           '',
    expired_at:         qrCode.expired_at ?? '',
    targeting_geo:      (qrCode.targeting_geo ?? []) as QrFormData['targeting_geo'],
    targeting_device:   (qrCode.targeting_device ?? []) as QrFormData['targeting_device'],
    targeting_language: (qrCode.targeting_language ?? []) as QrFormData['targeting_language'],
    targeting_ab:       (qrCode.targeting_ab ?? []) as QrFormData['targeting_ab'],
    pixel_ids:          qrCode.pixel_ids ?? [],
  });

  const original: QrEditState = {
    type: qrCode.type,
    is_dynamic: qrCode.is_dynamic,
    domain_id: qrCode.domain_id ?? '',
    slug: qrCode.slug ?? '',
    content: qrCode.content,
    style: qrCode.style,
  };

  async function submit(e: FormEvent) {
    e.preventDefault();
    const next: QrEditState = {
      type: data.type,
      is_dynamic: data.is_dynamic,
      domain_id: data.domain_id,
      slug: data.slug,
      content: data.content,
      style: data.style,
    };

    if (isRiskyEdit(original, next)) {
      const ok = await confirmAction({
        title: t('qr.edit.confirm.title'),
        text: t('qr.edit.confirm.text'),
        confirmText: t('qr.edit.confirm.button'),
      });
      if (!ok) return;
    }

    put(route('app.project.qrcodes.update', { project: project!.id, qrCode: qrCode.id }));
  }

  return (
    <AppLayout title="Edit QR code">
      <div className="px-8 py-8">
        <div className="mb-6">
          <Link href={route('app.project.qrcodes.index', { project: project!.id })}
            className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            <ArrowLeft className="h-4 w-4" /> Back to QR codes
          </Link>
          <h1 className="mt-3 text-2xl font-bold text-slate-900 dark:text-white">
            Edit <span className="text-indigo-600 dark:text-indigo-400">{qrCode.name}</span>
          </h1>
        </div>
        <div className="space-y-4">
          <QrEditor
            data={data}
            setData={setData}
            errors={errors as Record<string, string>}
            processing={processing}
            submitLabel="Save changes"
            cancelHref={route('app.project.qrcodes.index', { project: project!.id })}
            domains={domains}
            dynamicUrl={qrCode.dynamic_url ?? undefined}
            pixels={pixels}
            linkHasPassword={qrCode.has_password}
            onSubmit={submit}
          />
          <QrVersionsPanel qrId={qrCode.id} versions={versions} />
        </div>
      </div>
    </AppLayout>
  );
}
