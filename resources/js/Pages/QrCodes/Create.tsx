import AppLayout from '@/Layouts/AppLayout';
import { DEFAULT_STYLE, DYNAMIC_TYPES } from '@/data/qrTypes';
import { PageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import QrEditor, { QrFormData } from './partials/QrEditor';

interface Domain { id: string; name: string }
interface AttachUrl { id: string; domain_id: string; slug: string; domain_name: string | null; target: string }

export default function QrCodesCreate({
  defaultStyle, domains, attachUrl,
}: {
  defaultStyle: Record<string, unknown>;
  domains: Domain[];
  attachUrl: AttachUrl | null;
}) {
  const { project } = usePage<PageProps>().props;
  const first = DYNAMIC_TYPES[0];

  const { data, setData, post, processing, errors } = useForm<QrFormData>({
    name:       attachUrl ? `QR – ${attachUrl.slug}` : '',
    type:       attachUrl ? 'link' : first.value,
    is_dynamic: true,
    domain_id:  attachUrl ? attachUrl.domain_id : (domains[0]?.id ?? ''),
    slug:       attachUrl ? attachUrl.slug : '',
    content:    attachUrl ? { url: attachUrl.target } : { ...first.defaultContent },
    style:      { ...DEFAULT_STYLE, ...(defaultStyle as object) } as typeof DEFAULT_STYLE,
    url_id:     attachUrl ? attachUrl.id : undefined,
  });

  const title = attachUrl ? 'Create QR code for link' : 'Create QR code';

  return (
    <AppLayout title={title}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <Link href={route('app.project.qrcodes.index', { project: project!.id })}
            className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            <ArrowLeft className="h-4 w-4" /> Back to QR codes
          </Link>
          <h1 className="mt-3 text-2xl font-bold text-slate-900 dark:text-white">{title}</h1>
        </div>
        <QrEditor
          data={data}
          setData={setData}
          errors={errors as Record<string, string>}
          processing={processing}
          submitLabel="Create QR code"
          cancelHref={route('app.project.qrcodes.index', { project: project!.id })}
          domains={domains}
          attachLink={attachUrl ? { domainName: attachUrl.domain_name ?? '', slug: attachUrl.slug, target: attachUrl.target } : null}
          onSubmit={e => { e.preventDefault(); post(route('app.project.qrcodes.store', { project: project!.id })); }}
        />
      </div>
    </AppLayout>
  );
}
